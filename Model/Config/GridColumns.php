<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Config;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

/**
 * Configuratie per grid (orders, customers, products):
 * - lucky_fastgrids/<grid>/columns : komma-lijst van gekozen kolomsleutels, in volgorde
 * - lucky_fastgrids/<grid>/labels  : JSON {kolomnaam: label} opgehaald uit het standaard UI-grid
 * - lucky_fastgrids/<grid>/impact  : JSON {kolomnaam: ms per 20 rijen} gemeten bij het ophalen
 * - lucky_fastgrids/<grid>/defaults: komma-lijst met de door de beheerder opgeslagen standaardkolommen
 */
class GridColumns
{
    public const PATH = 'lucky_fastgrids/%s/%s';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private WriterInterface $writer,
        private TypeListInterface $cacheTypeList
    ) {
    }

    /** @return string[] gekozen kolomsleutels in volgorde (leeg = standaard) */
    public function selected(string $grid): array
    {
        $raw = (string) $this->scopeConfig->getValue(sprintf(self::PATH, $grid, 'columns'));
        $keys = array_filter(array_map('trim', preg_split('/[,\n]+/', $raw) ?: []));
        return array_values(array_unique($keys));
    }

    /** @return array<string,string> kolomnaam => label uit het standaard UI-grid */
    public function labels(string $grid): array
    {
        return $this->json($grid, 'labels');
    }

    /** @return array<string,int> kolomnaam => gemeten ms per 20 rijen */
    public function impact(string $grid): array
    {
        return array_map('intval', $this->json($grid, 'impact'));
    }

    public function saveLabels(string $grid, array $labels, array $impact): void
    {
        $this->writer->save(sprintf(self::PATH, $grid, 'labels'), json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->writer->save(sprintf(self::PATH, $grid, 'impact'), json_encode($impact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->cacheTypeList->cleanType('config');
    }

    /** @return string[] door de beheerder opgeslagen standaardkolommen (leeg = ingebouwde standaard) */
    public function defaults(string $grid): array
    {
        $raw = (string) $this->scopeConfig->getValue(sprintf(self::PATH, $grid, 'defaults'));
        $keys = array_filter(array_map('trim', preg_split('/[,\n]+/', $raw) ?: []));
        return array_values(array_unique($keys));
    }

    /** Slaat de meegegeven kolommen op als nieuwe standaard voor een grid. */
    public function saveDefaults(string $grid, array $keys): void
    {
        $this->writer->save(sprintf(self::PATH, $grid, 'defaults'), implode(',', array_values(array_unique($keys))));
        $this->cacheTypeList->cleanType('config');
    }

    /** Verwijdert de kolomkeuze van een grid (terug naar standaard). */
    public function resetSelection(string $grid): void
    {
        $this->writer->delete(sprintf(self::PATH, $grid, 'columns'));
        $this->cacheTypeList->cleanType('config');
    }

    private function json(string $grid, string $field): array
    {
        $raw = (string) $this->scopeConfig->getValue(sprintf(self::PATH, $grid, $field));
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
