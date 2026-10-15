<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model;

/**
 * Maakt een rij weergaveklaar op basis van de kolomdefinities uit de catalogus.
 */
class GridFormatter
{
    public function __construct(
        private ValueFormatter $valueFormatter
    ) {
    }

    /**
     * @param array<string,array> $defs  gekozen kolommen (sleutel => definitie)
     * @param array $row                 ruwe rij (aliassen = sleutels)
     * @param array<string,string> $raw  extra velden die ongewijzigd meegaan (uitvoer => rijveld)
     */
    public function format(array $defs, array $row, array $raw = []): array
    {
        $out = [];
        foreach ($raw as $outKey => $rowKey) {
            $out[$outKey] = $row[$rowKey] ?? null;
        }
        foreach ($defs as $key => $def) {
            $src = $def['src'] ?? '';
            if ($src === 'pseudo') {
                continue;
            }
            if ($src === 'plugin') {
                // Al berekend door de kolomklasse van de plugin (PluginColumns): structuur doorgeven.
                $out[$key] = $row[$key] ?? ['text' => ''];
                continue;
            }
            $out[$key] = $this->valueFormatter->format((string) $def['type'], $row[$key] ?? null, $row);
        }
        return $out;
    }
}
