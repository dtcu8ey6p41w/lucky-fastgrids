<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Orders;

use Lucky\FastGrids\Model\Catalog\OrdersCatalog;
use Lucky\FastGrids\Model\SchemaInfo;
use Magento\Framework\App\ResourceConnection;

/**
 * Waarden die daadwerkelijk in orders voorkomen (voor de filterdropdowns):
 * één DISTINCT-query per veld op sales_order_grid.
 */
class UsedValues
{
    private array $cache = [];

    public function __construct(
        private ResourceConnection $resource,
        private SchemaInfo $schema
    ) {
    }

    /** @return string[] statuscodes die in orders voorkomen */
    public function statuses(): array
    {
        return $this->distinct('status');
    }

    /** @return string[] betaalmethode-codes die in orders voorkomen */
    public function paymentMethods(): array
    {
        return $this->distinct('payment_method');
    }

    private function distinct(string $column): array
    {
        if (!isset($this->cache[$column])) {
            $this->cache[$column] = [];
            if ($this->schema->hasColumn(OrdersCatalog::TABLE, $column)) {
                $conn = $this->resource->getConnection();
                $select = $conn->select()
                    ->distinct()
                    ->from($this->resource->getTableName(OrdersCatalog::TABLE), [$column])
                    ->where($column . ' IS NOT NULL')
                    ->where($column . ' <> ?', '');
                $this->cache[$column] = array_map('strval', $conn->fetchCol($select));
            }
        }
        return $this->cache[$column];
    }
}
