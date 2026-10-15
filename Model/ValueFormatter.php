<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model;

/**
 * Maakt een ruwe databasewaarde weergaveklaar op basis van het kolomtype uit de catalogus.
 *
 * Types: text, int, bool, datetime, date, money, address, order_status, customer_group,
 * store, payment, country, website, websites, product_type, attribute_set, option:<entity>:<code>,
 * multioption:<entity>:<code>, thumbnail, confirmation, mailchimp.
 */
class ValueFormatter
{
    private const MAILCHIMP_LABELS = [
        0 => 'Niet verzonden', 1 => 'Gesynchroniseerd', 2 => 'Opnieuw synchroniseren',
        3 => 'Wacht op synchronisatie', 4 => 'Fout', 5 => 'Niet gesynchroniseerd',
    ];

    public function __construct(
        private DateFormatter $dates,
        private Lookups $lookups
    ) {
    }

    public function format(string $type, $value, array $row = []): string
    {
        if ($type === 'confirmation') {
            return $value === null ? (string) __('Bevestigd') : (string) __('Niet bevestigd');
        }
        if ($value === null || $value === '') {
            return '';
        }
        $v = (string) $value;
        switch ($type) {
            case 'int':
                return (string) (int) $value;
            case 'bool':
                return (int) $value ? (string) __('Ja') : (string) __('Nee');
            case 'datetime':
                return $this->dates->dateTime($v);
            case 'date':
                return $this->dates->date($v);
            case 'money':
                return $this->money((float) $value, (string) ($row['order_currency_code'] ?? 'EUR'));
            case 'address':
                return $this->address($v);
            case 'order_status':
                return $this->lookups->orderStatuses()[$v] ?? $v;
            case 'customer_group':
                return $this->lookups->customerGroups()[(string) (int) $value] ?? $v;
            case 'store':
                return $this->lookups->stores()[(int) $value] ?? $v;
            case 'payment':
                return $this->lookups->paymentTitles()[$v] ?? $v;
            case 'country':
                return $this->lookups->countries()[$v] ?? $v;
            case 'website':
                return $this->lookups->websites()[(int) $value] ?? $v;
            case 'product_type':
                return $this->lookups->productTypes()[$v] ?? $v;
            case 'attribute_set':
                return $this->lookups->attributeSets()[(int) $value] ?? $v;
            case 'mailchimp':
                return (string) __(self::MAILCHIMP_LABELS[(int) $value] ?? $v);
            case 'thumbnail':
            case 'text':
            case 'websites':
                return $v;
        }
        if (str_starts_with($type, 'option:')) {
            [, $entity, $code] = explode(':', $type, 3);
            return $this->lookups->attributeOptions($entity, $code)[$v] ?? $v;
        }
        if (str_starts_with($type, 'multioption:')) {
            [, $entity, $code] = explode(':', $type, 3);
            $opts = $this->lookups->attributeOptions($entity, $code);
            $labels = [];
            foreach (explode(',', $v) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $labels[] = $opts[$part] ?? $part;
                }
            }
            return implode(', ', $labels);
        }
        return $v;
    }

    public function money(float $amount, string $currency = 'EUR'): string
    {
        $symbol = $currency === 'EUR' || $currency === '' ? '€ ' : $currency . ' ';
        return $symbol . number_format($amount, 2, ',', '.');
    }

    /**
     * Gridadres "straat\nhuisnummer,plaats[,regio],postcode" (Magento's CONCAT_WS)
     * -> "straat huisnummer toevoeging postcode plaats" op één regel.
     */
    public function address(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $parts = array_map('trim', explode(',', $raw));
        if (count($parts) < 3) {
            return preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $raw));
        }
        $postcode = array_pop($parts);
        $street = array_shift($parts);
        $city = array_shift($parts) ?? '';
        $streetLine = preg_replace('/\s+/', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $street));
        return trim($streetLine . ' ' . $postcode . ' ' . $city);
    }
}
