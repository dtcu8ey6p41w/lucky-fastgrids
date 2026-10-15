<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Orders;

use Lucky\FastGrids\Model\Catalog\OrdersCatalog;
use Lucky\FastGrids\Model\PluginColumns;
use Lucky\FastGrids\Model\SchemaInfo;
use Lucky\FastGrids\Model\Search;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * Directe, geïndexeerde queries op sales_order_grid; alleen de gekozen kolommen.
 * Joins (bezorgdatum, verzendmethode, klantattributen) alleen als een gekozen kolom ze nodig heeft.
 * Gekozen plugin-kolommen worden daarna door hun eigen kolomklasse berekend (langzaam).
 */
class OrderList
{
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT = 500;

    private const LIKE_FALLBACK = ['increment_id', 'billing_name', 'shipping_name', 'customer_email'];

    public function __construct(
        private ResourceConnection $resource,
        private SchemaInfo $schema,
        private Search $search,
        private OrdersCatalog $catalog,
        private PluginColumns $pluginColumns
    ) {
    }

    public function hasDeliveryDate(): bool
    {
        return $this->catalog->hasDeliveryDate();
    }

    /** @return array{items: array, total: int, sum: float, page: int, pages: int, limit: int} */
    public function search(array $f): array
    {
        $conn = $this->resource->getConnection();
        $grid = $this->resource->getTableName(OrdersCatalog::TABLE);
        $defs = $this->catalog->selected();
        $pluginKeys = $this->catalog->selectedPluginKeys();
        $withDelivery = $this->hasDeliveryDate();

        $limit = max(1, min((int) ($f['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT));
        $page = max(1, (int) ($f['page'] ?? 1));
        $dir = strtolower((string) ($f['dir'] ?? 'desc')) === 'asc' ? Select::SQL_ASC : Select::SQL_DESC;

        // Plugin-kolommen verwachten de volledige gridrij; anders alleen wat nodig is.
        $columns = $pluginKeys ? ['*'] : ['entity_id', 'increment_id', 'status'];
        if (!$pluginKeys && $this->schema->hasColumn(OrdersCatalog::TABLE, 'order_currency_code')) {
            $columns[] = 'order_currency_code';
        }
        $deliveryCols = [];
        $customerCols = [];
        $orderCols = [];
        foreach ($defs as $key => $def) {
            if ($def['src'] === 'grid') {
                if (!$pluginKeys) {
                    $columns[$key] = $def['col'];
                }
            } elseif ($def['join'] === 'delivery') {
                $deliveryCols[$key] = $def['col'];
            } elseif ($def['join'] === 'customer') {
                $customerCols[$key] = $def['col'];
            } elseif ($def['join'] === 'order') {
                $orderCols[$key] = $def['col'];
            }
        }
        $columns = $this->uniqueColumns($columns);

        $sortKey = (string) ($f['sort'] ?? 'created_at');
        $sortExpr = 'main_table.created_at';
        if (isset($defs[$sortKey]) && $defs[$sortKey]['sort']) {
            $sortExpr = match ($defs[$sortKey]['join'] ?? null) {
                'delivery' => 'ad.' . $defs[$sortKey]['col'],
                'order' => 'so.' . $defs[$sortKey]['col'],
                default => 'main_table.' . $defs[$sortKey]['col'],
            };
        } elseif ($sortKey === 'increment_id' || $sortKey === 'status') {
            $sortExpr = 'main_table.' . $sortKey;
        }

        $rows = $conn->select()
            ->from(['main_table' => $grid], $columns)
            ->order($sortExpr . ' ' . $dir)
            ->order('main_table.entity_id ' . $dir)
            ->limit($limit, ($page - 1) * $limit);
        if ($withDelivery && ($deliveryCols || str_starts_with($sortExpr, 'ad.'))) {
            $rows->joinLeft(['ad' => $this->resource->getTableName(OrdersCatalog::DELIVERY_TABLE)], 'ad.order_id = main_table.entity_id', $deliveryCols);
        }
        if ($customerCols) {
            $rows->joinLeft(['cg' => $this->resource->getTableName(OrdersCatalog::CUSTOMER_TABLE)], 'cg.entity_id = main_table.customer_id', $customerCols);
        }
        if ($orderCols || str_starts_with($sortExpr, 'so.')) {
            $rows->joinLeft(['so' => $this->resource->getTableName(OrdersCatalog::ORDER_TABLE)], 'so.entity_id = main_table.entity_id', $orderCols);
        }
        $this->applyFilters($rows, $f, $withDelivery);

        $totals = $conn->select()
            ->from(['main_table' => $grid], ['cnt' => new \Zend_Db_Expr('COUNT(*)'), 'sum' => new \Zend_Db_Expr('SUM(main_table.grand_total)')]);
        if ($withDelivery && (!empty($f['delivery_from']) || !empty($f['delivery_to']))) {
            $totals->joinLeft(['ad' => $this->resource->getTableName(OrdersCatalog::DELIVERY_TABLE)], 'ad.order_id = main_table.entity_id', []);
        }
        $this->applyFilters($totals, $f, $withDelivery);

        $items = $conn->fetchAll($rows);
        if ($pluginKeys) {
            $items = $this->pluginColumns->apply($this->catalog->uiComponent(), $pluginKeys, $items);
        }
        $t = $conn->fetchRow($totals) ?: ['cnt' => 0, 'sum' => 0];
        $total = (int) $t['cnt'];

        return [
            'items' => $items, 'total' => $total, 'sum' => (float) $t['sum'],
            'page' => $page, 'pages' => max(1, (int) ceil($total / $limit)), 'limit' => $limit,
        ];
    }

    private function uniqueColumns(array $columns): array
    {
        $out = [];
        foreach ($columns as $alias => $col) {
            $alias = is_int($alias) ? $col : $alias;
            $out[$alias] = $col;
        }
        return $out;
    }

    private function applyFilters(Select $select, array $f, bool $withDelivery): void
    {
        $conn = $this->resource->getConnection();
        if (!empty($f['status'])) {
            $select->where('main_table.status IN (?)', array_values((array) $f['status']));
        }
        if (!empty($f['payment_method'])) {
            $select->where('main_table.payment_method IN (?)', array_values((array) $f['payment_method']));
        }
        if (isset($f['customer_group']) && $f['customer_group'] !== []) {
            $select->where('main_table.customer_group IN (?)', array_map('strval', array_map('intval', (array) $f['customer_group'])));
        }
        if (!empty($f['created_from'])) {
            $select->where('main_table.created_at >= ?', $f['created_from']);
        }
        if (!empty($f['created_to'])) {
            $select->where('main_table.created_at <= ?', $f['created_to']);
        }
        if ($withDelivery) {
            if (!empty($f['delivery_from'])) {
                $select->where('ad.date >= ?', $f['delivery_from']);
            }
            if (!empty($f['delivery_to'])) {
                $select->where('ad.date <= ?', $f['delivery_to']);
            }
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q === '') {
            return;
        }
        if (preg_match('/^\d{3,}$/', $q)) {
            $select->where('main_table.increment_id LIKE ?', $q . '%');
            return;
        }
        if (filter_var($q, FILTER_VALIDATE_EMAIL)) {
            $select->where('main_table.customer_email = ?', $q);
            return;
        }
        $this->search->applyText(
            $conn, $select, $q,
            $this->schema->fulltextColumns(OrdersCatalog::TABLE),
            array_values(array_intersect(self::LIKE_FALLBACK, $this->schema->columns(OrdersCatalog::TABLE)))
        );
    }
}
