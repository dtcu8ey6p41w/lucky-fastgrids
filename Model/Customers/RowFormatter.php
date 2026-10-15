<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Customers;

use Lucky\FastGrids\Model\Catalog\CustomersCatalog;
use Lucky\FastGrids\Model\GridFormatter;

class RowFormatter
{
    public function __construct(
        private GridFormatter $gridFormatter,
        private CustomersCatalog $catalog
    ) {
    }

    public function format(array $row): array
    {
        $out = $this->gridFormatter->format(
            $this->catalog->selected(),
            $row,
            ['entity_id' => 'entity_id', '_name' => 'name', '_email' => 'email']
        );
        if (array_key_exists('confirmation', $row)) {
            $out['_confirmed'] = $row['confirmation'] === null;
        }
        if (array_key_exists('mailchimp', $row)) {
            $out['_mailchimp_code'] = $row['mailchimp'] === null ? null : (int) $row['mailchimp'];
            $out['_mailchimp_error'] = (string) ($row['mailchimp_error'] ?? '');
        }
        return $out;
    }
}
