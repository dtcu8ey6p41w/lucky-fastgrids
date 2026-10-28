<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

/**
 * Gedeelde zoeklogica: fulltext in boolean mode met prefix-woorden als de tabel
 * een fulltext-index heeft, anders LIKE op een paar kolommen.
 */
class Search
{
    /**
     * @param string[] $fulltextColumns  kolommen van de fulltext-index (zonder alias)
     * @param string[] $likeColumns      fallback-kolommen (zonder alias)
     */
    public function applyText(
        AdapterInterface $conn,
        Select $select,
        string $q,
        array $fulltextColumns,
        array $likeColumns,
        string $alias = 'main_table'
    ): void {
        $condition = $this->condition($conn, $q, $fulltextColumns, $likeColumns, $alias);
        if ($condition !== null) {
            $select->where($condition);
        }
    }

    /**
     * Dezelfde zoekvoorwaarde als losse SQL-string (om te combineren met andere voorwaarden).
     * @return string|null null als er niets te zoeken valt
     */
    public function condition(
        AdapterInterface $conn,
        string $q,
        array $fulltextColumns,
        array $likeColumns,
        string $alias = 'main_table'
    ): ?string {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $terms = [];
        foreach ($words as $w) {
            if (mb_strlen($w) >= 3) {
                $terms[] = '+' . $w . '*';
            }
        }

        if ($terms && $fulltextColumns) {
            $cols = array_map(fn($c) => $alias . '.' . $c, $fulltextColumns);
            return $conn->quoteInto('MATCH(' . implode(', ', $cols) . ') AGAINST (? IN BOOLEAN MODE)', implode(' ', $terms));
        }

        // Geen fulltext-index of alleen korte woorden: LIKE-fallback.
        $parts = [];
        foreach ($likeColumns as $c) {
            $parts[] = $conn->quoteInto($alias . '.' . $c . ' LIKE ?', $this->contains($q));
        }
        return $parts ? '(' . implode(' OR ', $parts) . ')' : null;
    }

    /** LIKE-patroon "bevat $q", met % en _ in de zoekterm als letterlijke tekens. */
    public function contains(string $q): string
    {
        return '%' . addcslashes($q, '\\%_') . '%';
    }
}
