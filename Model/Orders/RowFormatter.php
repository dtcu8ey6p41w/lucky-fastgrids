<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Orders;

use Lucky\FastGrids\Model\Catalog\OrdersCatalog;
use Lucky\FastGrids\Model\GridFormatter;
use Lucky\FastGrids\Model\Lookups;
use Lucky\FastGrids\Model\ValueFormatter;

class RowFormatter
{
    public function __construct(
        private GridFormatter $gridFormatter,
        private ValueFormatter $valueFormatter,
        private OrdersCatalog $catalog,
        private Lookups $lookups
    ) {
    }

    public function format(array $row): array
    {
        return $this->gridFormatter->format(
            $this->catalog->selected(),
            $row,
            ['entity_id' => 'entity_id', '_increment_id' => 'increment_id', '_status' => 'status']
        );
    }

    public function money(float $amount, string $currency = 'EUR'): string
    {
        return $this->valueFormatter->money($amount, $currency);
    }

    /** @return array<string,string> group_id => naam */
    public function groupNames(): array
    {
        return $this->lookups->customerGroups();
    }
}
