<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Datums tussen UTC (database) en de shop-tijdzone.
 */
class DateFormatter
{
    private \DateTimeZone $utc;
    private \DateTimeZone $local;

    public function __construct(TimezoneInterface $timezone)
    {
        $this->utc = new \DateTimeZone('UTC');
        $this->local = new \DateTimeZone($timezone->getConfigTimezone());
    }

    /** UTC-timestamp uit de database -> "dd-mm-jjjj uu:mm" in de shop-tijdzone. */
    public function dateTime(?string $utc): string
    {
        if (!$utc) {
            return '';
        }
        try {
            return (new \DateTime($utc, $this->utc))->setTimezone($this->local)->format('d-m-Y H:i');
        } catch (\Exception) {
            return (string) $utc;
        }
    }

    /** Kale datum (geen tijdzone) -> "dd-mm-jjjj". */
    public function date(?string $date): string
    {
        if (!$date) {
            return '';
        }
        try {
            return (new \DateTime($date, $this->local))->format('d-m-Y');
        } catch (\Exception) {
            return (string) $date;
        }
    }

    /** Lokale datum (jjjj-mm-dd) uit een filter -> UTC-datetime; leeg bij ongeldige invoer. */
    public function localDateToUtc(string $localDate, bool $endOfDay): string
    {
        $d = $this->plainDate($localDate);
        if ($d === '') {
            return '';
        }
        $dt = new \DateTime($d . ($endOfDay ? ' 23:59:59' : ' 00:00:00'), $this->local);
        return $dt->setTimezone($this->utc)->format('Y-m-d H:i:s');
    }

    /** Valideert jjjj-mm-dd; alles anders wordt leeg. */
    public function plainDate(string $date): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }
}
