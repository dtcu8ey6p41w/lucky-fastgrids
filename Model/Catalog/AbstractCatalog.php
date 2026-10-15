<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Catalog;

use Lucky\FastGrids\Model\Config\GridColumns;

abstract class AbstractCatalog implements CatalogInterface
{
    /** UI-kolommen die geen gegevens zijn (selectievakjes e.d.). */
    private const SKIP_UI = ['ids'];

    private ?array $available = null;

    public function __construct(
        protected GridColumns $gridColumns
    ) {
    }

    abstract protected function build(): array;

    /** @return string[] ingebouwde standaardkolommen van dit grid */
    abstract protected function builtinDefaults(): array;

    public function defaults(): array
    {
        $saved = $this->gridColumns->defaults(static::GRID);
        return $saved ?: $this->builtinDefaults();
    }

    public function available(): array
    {
        if ($this->available === null) {
            $items = $this->build();
            $uiLabels = $this->gridColumns->labels(static::GRID);
            $impact = $this->gridColumns->impact(static::GRID);
            foreach ($items as $key => &$def) {
                $uiName = $def['ui'] ?? $key;
                if (isset($uiLabels[$uiName]) && $uiLabels[$uiName] !== '') {
                    $def['label'] = $uiLabels[$uiName];
                }
                $def += ['fast' => true, 'sort' => false, 'src' => 'grid', 'col' => null, 'join' => null, 'reason' => null, 'impact' => null];
            }
            unset($def);
            // Kolommen van het standaardgrid die wij niet zelf uit de database halen:
            // kiesbaar, maar langzaam (berekend door de kolomklasse van de module die ze toevoegt).
            foreach ($uiLabels as $name => $label) {
                if (isset($items[$name]) || $this->isKnownUiName($name, $items) || in_array($name, self::SKIP_UI, true)) {
                    continue;
                }
                $ms = $impact[$name] ?? null;
                $items[$name] = [
                    'label' => $label, 'type' => 'plugin', 'fast' => false, 'sort' => false,
                    'src' => 'plugin', 'col' => null, 'join' => null, 'impact' => $ms,
                    'reason' => match (true) {
                        $ms === null => (string) __('langzaam: wordt per rij berekend door een plugin (nog niet gemeten)'),
                        $ms < 1 => (string) __('per rij berekend door een plugin, gemeten < 1 ms per 20 rijen'),
                        $ms < 20 => (string) __('per rij berekend door een plugin, gemeten ≈ %1 ms per 20 rijen', $ms),
                        default => (string) __('LANGZAAM: per rij berekend door een plugin, gemeten ≈ %1 ms per 20 rijen', $ms),
                    },
                ];
            }
            $this->available = $items;
        }
        return $this->available;
    }

    public function selected(): array
    {
        $available = $this->available();
        $keys = $this->gridColumns->selected(static::GRID);
        if (!$keys) {
            $keys = $this->defaults();
        }
        $out = [];
        foreach ($keys as $key) {
            if (isset($available[$key])) {
                $out[$key] = $available[$key];
            }
        }
        if (!$out) {
            foreach ($this->defaults() as $key) {
                if (isset($available[$key])) {
                    $out[$key] = $available[$key];
                }
            }
        }
        return $out;
    }

    /** @return string[] sleutels van gekozen plugin-kolommen (langzaam) */
    public function selectedPluginKeys(): array
    {
        return array_keys(array_filter($this->selected(), fn($d) => $d['src'] === 'plugin'));
    }

    private function isKnownUiName(string $name, array $items): bool
    {
        foreach ($items as $def) {
            if (($def['ui'] ?? null) === $name) {
                return true;
            }
        }
        return false;
    }

    /** "billing_postcode" -> "Billing postcode" */
    protected function humanize(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
