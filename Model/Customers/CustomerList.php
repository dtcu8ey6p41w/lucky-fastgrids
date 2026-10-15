<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Customers;

use Lucky\FastGrids\Model\Catalog\CustomersCatalog;
use Lucky\FastGrids\Model\PluginColumns;
use Lucky\FastGrids\Model\SchemaInfo;
use Lucky\FastGrids\Model\Search;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * Directe, geïndexeerde queries op customer_grid_flat; alleen de gekozen kolommen.
 * Gekozen plugin-kolommen worden door hun eigen kolomklasse berekend (langzaam).
 */
class CustomerList
{
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT = 500;

    private const LIKE_FALLBACK = ['name', 'email', 'billing_telephone', 'billing_postcode', 'billing_city'];

    public function __construct(
        private ResourceConnection $resource,
        private ScopeConfigInterface $scopeConfig,
        private SchemaInfo $schema,
        private Search $search,
        private CustomersCatalog $catalog,
        private PluginColumns $pluginColumns
    ) {
    }

    /** @return array{items: array, total: int, page: int, pages: int, limit: int} */
    public function search(array $f): array
    {
        $conn = $this->resource->getConnection();
        $grid = $this->resource->getTableName(CustomersCatalog::TABLE);
        $defs = $this->catalog->selected();
        $pluginKeys = $this->catalog->selectedPluginKeys();

        $limit = max(1, min((int) ($f['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT));
        $page = max(1, (int) ($f['page'] ?? 1));
        $dir = strtolower((string) ($f['dir'] ?? 'desc')) === 'asc' ? Select::SQL_ASC : Select::SQL_DESC;

        $columns = $pluginKeys ? ['*'] : ['entity_id' => 'entity_id', 'name' => 'name', 'email' => 'email'];
        $needMailchimp = false;
        foreach ($defs as $key => $def) {
            if ($def['src'] === 'grid' && !$pluginKeys) {
                $columns[$key] = $def['col'];
            } elseif (($def['join'] ?? null) === 'mailchimp') {
                $needMailchimp = true;
            }
        }

        $sortKey = (string) ($f['sort'] ?? 'entity_id');
        $sortExpr = 'main_table.entity_id';
        if (isset($defs[$sortKey]) && $defs[$sortKey]['sort']) {
            $sortExpr = ($defs[$sortKey]['join'] ?? null) === 'mailchimp' ? 'mc.mailchimp_sent' : 'main_table.' . $defs[$sortKey]['col'];
        } elseif (in_array($sortKey, ['entity_id', 'name', 'email'], true)) {
            $sortExpr = 'main_table.' . $sortKey;
        }

        $rows = $conn->select()
            ->from(['main_table' => $grid], $columns)
            ->order($sortExpr . ' ' . $dir)
            ->order('main_table.entity_id ' . $dir)
            ->limit($limit, ($page - 1) * $limit);
        if ($needMailchimp || $sortExpr === 'mc.mailchimp_sent') {
            $storeId = (string) $this->scopeConfig->getValue('mailchimp/general/monkeystore');
            $rows->joinLeft(
                ['mc' => $this->resource->getTableName(CustomersCatalog::MAILCHIMP_TABLE)],
                "mc.related_id = main_table.entity_id AND mc.type = 'CUS' AND mc.mailchimp_store_id = " . $conn->quote($storeId),
                ['mailchimp' => 'mc.mailchimp_sent', 'mailchimp_error' => 'mc.mailchimp_sync_error']
            );
        }
        $this->applyFilters($rows, $f);

        $count = $conn->select()->from(['main_table' => $grid], ['cnt' => new \Zend_Db_Expr('COUNT(*)')]);
        $this->applyFilters($count, $f);

        $items = $conn->fetchAll($rows);
        if ($pluginKeys) {
            $items = $this->pluginColumns->apply($this->catalog->uiComponent(), $pluginKeys, $items);
        }
        $total = (int) $conn->fetchOne($count);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $limit)), 'limit' => $limit];
    }

    private function applyFilters(Select $select, array $f): void
    {
        $conn = $this->resource->getConnection();
        if (!empty($f['group_id'])) {
            $select->where('main_table.group_id IN (?)', array_map('intval', array_values((array) $f['group_id'])));
        }
        if (!empty($f['created_from'])) {
            $select->where('main_table.created_at >= ?', $f['created_from']);
        }
        if (!empty($f['created_to'])) {
            $select->where('main_table.created_at <= ?', $f['created_to']);
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q === '') {
            return;
        }
        if (preg_match('/^\d+$/', $q)) {
            $select->where(implode(' OR ', [
                $conn->quoteInto('main_table.entity_id = ?', (int) $q),
                $conn->quoteInto('main_table.billing_telephone LIKE ?', $q . '%'),
                $conn->quoteInto('main_table.billing_postcode LIKE ?', $q . '%'),
            ]));
            return;
        }
        if (filter_var($q, FILTER_VALIDATE_EMAIL)) {
            $select->where('main_table.email = ?', $q);
            return;
        }
        $this->search->applyText(
            $conn, $select, $q,
            $this->schema->fulltextColumns(CustomersCatalog::TABLE),
            array_values(array_intersect(self::LIKE_FALLBACK, $this->schema->columns(CustomersCatalog::TABLE)))
        );
    }
}
