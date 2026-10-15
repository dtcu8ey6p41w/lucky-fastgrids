<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * Detecteert per request wat er in de database aanwezig is (tabellen, kolommen,
 * fulltext-indexen), zodat de module zich aanpast aan de installatie en nergens
 * een harde afhankelijkheid op een andere module heeft.
 */
class SchemaInfo
{
    /** @var array<string, string[]> */
    private array $columns = [];
    /** @var array<string, string[]> */
    private array $fulltext = [];
    /** @var array<string, bool> */
    private array $exists = [];

    public function __construct(
        private ResourceConnection $resource
    ) {
    }

    public function tableExists(string $table): bool
    {
        if (!isset($this->exists[$table])) {
            $this->exists[$table] = $this->resource->getConnection()
                ->isTableExists($this->resource->getTableName($table));
        }
        return $this->exists[$table];
    }

    /** @return string[] kolomnamen, leeg als de tabel niet bestaat */
    public function columns(string $table): array
    {
        if (!isset($this->columns[$table])) {
            $this->columns[$table] = $this->tableExists($table)
                ? array_keys($this->resource->getConnection()->describeTable($this->resource->getTableName($table)))
                : [];
        }
        return $this->columns[$table];
    }

    public function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    public function hasColumns(string $table, array $columns): bool
    {
        return !array_diff($columns, $this->columns($table));
    }

    /** @return string[] kolommen van de (eerste) fulltext-index op de tabel, leeg als er geen is */
    public function fulltextColumns(string $table): array
    {
        if (!isset($this->fulltext[$table])) {
            $this->fulltext[$table] = [];
            if ($this->tableExists($table)) {
                $indexes = $this->resource->getConnection()->getIndexList($this->resource->getTableName($table));
                foreach ($indexes as $index) {
                    if (strtolower((string) ($index['INDEX_TYPE'] ?? '')) === 'fulltext') {
                        $this->fulltext[$table] = (array) $index['COLUMNS_LIST'];
                        break;
                    }
                }
            }
        }
        return $this->fulltext[$table];
    }
}
