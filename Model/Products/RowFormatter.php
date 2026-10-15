<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Products;

use Lucky\FastGrids\Model\Catalog\ProductsCatalog;
use Lucky\FastGrids\Model\GridFormatter;

class RowFormatter
{
    public function __construct(
        private GridFormatter $gridFormatter,
        private ProductsCatalog $catalog
    ) {
    }

    public function format(array $row): array
    {
        $out = $this->gridFormatter->format(
            $this->catalog->selected(),
            $row,
            ['entity_id' => 'entity_id', '_sku' => 'sku', '_name' => 'name']
        );
        if (array_key_exists('status', $row)) {
            $out['_status'] = (int) $row['status'];
        }
        return $out;
    }
}
