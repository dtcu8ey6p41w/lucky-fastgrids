<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Products;

use Lucky\FastGrids\Model\Catalog\ProductsCatalog;
use Lucky\FastGrids\Model\PluginColumns;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * Directe queries op catalog_product_entity. Elk gekozen EAV-attribuut is één LEFT JOIN op zijn
 * backend-tabel (store 0); voorraad via cataloginventory_stock_item; websites via een subquery.
 * Geen collection, geen plugins. Magento Open Source (entity_id-koppeling).
 */
class ProductList
{
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT = 500;

    /** @var array<string,string[]> per select-object: welke EAV-aliassen al gejoind zijn */
    private array $joined = [];

    public function __construct(
        private ResourceConnection $resource,
        private ProductsCatalog $catalog,
        private PluginColumns $pluginColumns
    ) {
    }

    /** @return array{items: array, total: int, page: int, pages: int, limit: int} */
    public function search(array $f): array
    {
        $conn = $this->resource->getConnection();
        $entity = $this->resource->getTableName(ProductsCatalog::TABLE);
        $defs = $this->catalog->selected();

        $limit = max(1, min((int) ($f['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT));
        $page = max(1, (int) ($f['page'] ?? 1));
        $dir = strtolower((string) ($f['dir'] ?? 'desc')) === 'asc' ? Select::SQL_ASC : Select::SQL_DESC;

        $rows = $conn->select()->from(['e' => $entity], ['entity_id', 'sku', 'type_id']);
        foreach ($defs as $key => $def) {
            if ($def['src'] === 'grid') {
                $rows->columns([$key => 'e.' . $def['col']]);
            } elseif ($def['src'] === 'join') {
                $this->joinFor($rows, $def, $key);
            }
        }
        // Plugin-kolommen van het productgrid verwachten de kernvelden
        if ($this->catalog->selectedPluginKeys()) {
            $rows->columns(['attribute_set_id' => 'e.attribute_set_id', 'created_at' => 'e.created_at', 'updated_at' => 'e.updated_at']);
            foreach (['name', 'price', 'status', 'visibility', 'thumbnail', 'small_image', 'image'] as $code) {
                $this->ensureEav($rows, $code, $code);
            }
        }
        $this->ensureEav($rows, 'name', 'name'); // altijd: rijtitel en zoeken

        $sortKey = (string) ($f['sort'] ?? 'entity_id');
        $sortExpr = 'e.entity_id';
        if (isset($defs[$sortKey]) && $defs[$sortKey]['sort']) {
            $sortExpr = $this->sortExpr($rows, $defs[$sortKey], $sortKey);
        } elseif (in_array($sortKey, ['entity_id', 'sku', 'type_id', 'created_at', 'updated_at'], true)) {
            $sortExpr = 'e.' . $sortKey;
        }
        $rows->order($sortExpr . ' ' . $dir)->order('e.entity_id ' . $dir)->limit($limit, ($page - 1) * $limit);
        $this->applyFilters($rows, $f);

        $count = $conn->select()->from(['e' => $entity], ['cnt' => new \Zend_Db_Expr('COUNT(*)')]);
        $this->applyFilters($count, $f);

        $items = $conn->fetchAll($rows);
        $pluginKeys = $this->catalog->selectedPluginKeys();
        if ($pluginKeys) {
            $items = $this->pluginColumns->apply($this->catalog->uiComponent(), $pluginKeys, $items);
        }
        $total = (int) $conn->fetchOne($count);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $limit)), 'limit' => $limit];
    }

    private function joinFor(Select $select, array $def, string $key): void
    {
        $join = (string) $def['join'];
        if (str_starts_with($join, 'eav:')) {
            $this->ensureEav($select, substr($join, 4), $key);
        } elseif ($join === 'stock') {
            $this->ensureStock($select);
            $select->columns([$key => 'si.' . $def['col']]);
        } elseif ($join === 'websites') {
            $pw = $this->resource->getTableName('catalog_product_website');
            $sw = $this->resource->getTableName('store_website');
            $select->columns([$key => new \Zend_Db_Expr(
                "(SELECT GROUP_CONCAT(w.name ORDER BY w.name SEPARATOR ', ') FROM `$pw` pw JOIN `$sw` w ON w.website_id = pw.website_id WHERE pw.product_id = e.entity_id)"
            )]);
        }
    }

    private function sortExpr(Select $select, array $def, string $key): string
    {
        $join = (string) ($def['join'] ?? '');
        if ($def['src'] === 'grid') {
            return 'e.' . $def['col'];
        }
        if (str_starts_with($join, 'eav:')) {
            return $this->ensureEav($select, substr($join, 4), $key) . '.value';
        }
        if ($join === 'stock') {
            $this->ensureStock($select);
            return 'si.' . $def['col'];
        }
        return 'e.entity_id';
    }

    /** LEFT JOIN op de backend-tabel van een attribuut (eenmalig per select); geeft de alias terug. */
    private function ensureEav(Select $select, string $code, ?string $columnAlias): string
    {
        $attrs = $this->catalog->attributes();
        if (!isset($attrs[$code])) {
            return 'e';
        }
        $alias = 'a_' . preg_replace('/[^a-z0-9_]/', '', $code);
        $id = spl_object_id($select);
        if (!isset($this->joined[$id][$alias])) {
            $table = $this->resource->getTableName('catalog_product_entity_' . $attrs[$code]['backend']);
            $select->joinLeft(
                [$alias => $table],
                sprintf('%1$s.entity_id = e.entity_id AND %1$s.attribute_id = %2$d AND %1$s.store_id = 0', $alias, $attrs[$code]['id']),
                []
            );
            $this->joined[$id][$alias] = true;
        }
        if ($columnAlias !== null && !isset($this->joined[$id]['col:' . $columnAlias])) {
            $select->columns([$columnAlias => $alias . '.value']);
            $this->joined[$id]['col:' . $columnAlias] = true;
        }
        return $alias;
    }

    private function ensureStock(Select $select): void
    {
        $id = spl_object_id($select);
        if (!isset($this->joined[$id]['si'])) {
            $select->joinLeft(
                ['si' => $this->resource->getTableName('cataloginventory_stock_item')],
                'si.product_id = e.entity_id AND si.stock_id = 1',
                []
            );
            $this->joined[$id]['si'] = true;
        }
    }

    private function applyFilters(Select $select, array $f): void
    {
        $conn = $this->resource->getConnection();
        foreach (['status', 'visibility'] as $attr) {
            if (!empty($f[$attr])) {
                $alias = $this->ensureEav($select, $attr, null);
                $select->where($alias . '.value IN (?)', array_map('intval', (array) $f[$attr]));
            }
        }
        if (!empty($f['type_id'])) {
            $select->where('e.type_id IN (?)', array_values((array) $f['type_id']));
        }
        if (!empty($f['attribute_set_id'])) {
            $select->where('e.attribute_set_id = ?', (int) $f['attribute_set_id']);
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q === '') {
            return;
        }
        if (preg_match('/^\d+$/', $q)) {
            $select->where(implode(' OR ', [
                $conn->quoteInto('e.entity_id = ?', (int) $q),
                $conn->quoteInto('e.sku LIKE ?', $q . '%'),
            ]));
            return;
        }
        $nameAlias = $this->ensureEav($select, 'name', null);
        $select->where(implode(' OR ', [
            $conn->quoteInto('e.sku LIKE ?', '%' . $q . '%'),
            $conn->quoteInto($nameAlias . '.value LIKE ?', '%' . $q . '%'),
        ]));
    }
}
