<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model;

use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponentInterface;
use Magento\Ui\Component\Listing\Columns;
use Psr\Log\LoggerInterface;

/**
 * "Langzame" kolommen: kolommen die in het standaardgrid door een kolomklasse van een
 * (andere) module per rij worden berekend. We bouwen het UI-component op (zonder data) en
 * laten exact die kolomklassen hun prepareDataSource() over onze rijen draaien. Dat geeft
 * dezelfde uitkomst als het standaardgrid, tegen dezelfde kosten per rij.
 */
class PluginColumns
{
    /** @var array<string, array<string, UiComponentInterface>> component => [kolomnaam => kolomcomponent] */
    private array $columns = [];

    public function __construct(
        private UiComponentFactory $uiComponentFactory,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Laat de kolomklassen van $keys hun waarden toevoegen aan $items.
     * Resultaat per cel: ['html' => string] of ['links' => [[href,label],...]] of ['text' => string].
     */
    public function apply(string $componentName, array $keys, array $items): array
    {
        if (!$keys || !$items) {
            return $items;
        }
        $columns = $this->columns($componentName);
        $ds = ['data' => ['items' => array_values($items), 'totalRecords' => count($items)]];
        foreach ($keys as $key) {
            if (!isset($columns[$key])) {
                continue;
            }
            try {
                $ds = $columns[$key]->prepareDataSource($ds);
            } catch (\Throwable $e) {
                $this->logger->error('Lucky_FastGrids: kolom ' . $key . ' van ' . $componentName . ' faalde: ' . $e->getMessage());
            }
        }
        $out = [];
        foreach ($ds['data']['items'] as $item) {
            foreach ($keys as $key) {
                $item[$key] = $this->normalize($columns[$key] ?? null, $key, $item[$key] ?? null);
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Meet per kolom hoeveel ms prepareDataSource kost op de meegegeven voorbeeldrijen.
     * @return array<string,int> kolomnaam => ms
     */
    public function measure(string $componentName, array $keys, array $sample): array
    {
        $columns = $this->columns($componentName);
        $out = [];
        foreach ($keys as $key) {
            if (!isset($columns[$key])) {
                continue;
            }
            $t = microtime(true);
            try {
                $columns[$key]->prepareDataSource(['data' => ['items' => array_values($sample), 'totalRecords' => count($sample)]]);
            } catch (\Throwable) {
                // telt mee als duur, maar niet als fout
            }
            $out[$key] = (int) round((microtime(true) - $t) * 1000);
        }
        return $out;
    }

    /** @return array<string, UiComponentInterface> */
    private function columns(string $componentName): array
    {
        if (!isset($this->columns[$componentName])) {
            $this->columns[$componentName] = [];
            try {
                $component = $this->uiComponentFactory->create($componentName);
                $this->prepare($component);
                $this->collect($component, $this->columns[$componentName]);
            } catch (\Throwable $e) {
                $this->logger->error('Lucky_FastGrids: UI-component ' . $componentName . ' niet opgebouwd: ' . $e->getMessage());
            }
        }
        return $this->columns[$componentName];
    }

    private function prepare(UiComponentInterface $component): void
    {
        foreach ($component->getChildComponents() as $child) {
            $this->prepare($child);
        }
        try {
            $component->prepare();
        } catch (\Throwable) {
            // kolommen van derden kunnen falen zonder request-context; dan alleen de XML-config
        }
    }

    private function collect(UiComponentInterface $component, array &$out): void
    {
        if ($component instanceof Columns) {
            foreach ($component->getChildComponents() as $name => $column) {
                $out[(string) $name] = $column;
            }
            return;
        }
        foreach ($component->getChildComponents() as $child) {
            $this->collect($child, $out);
        }
    }

    /** Zet de ruwe kolomwaarde om naar een cel-structuur voor het script. */
    private function normalize(?UiComponentInterface $column, string $key, $value): array
    {
        if ($value === null || $value === '') {
            return ['text' => ''];
        }
        // Actie-kolommen: [ 'view' => ['href' => ..., 'label' => ...], ... ]
        if (is_array($value)) {
            $links = [];
            foreach ($value as $v) {
                if (is_array($v) && isset($v['href'])) {
                    $links[] = ['href' => (string) $v['href'], 'label' => (string) ($v['label'] ?? $v['href'])];
                }
            }
            if ($links) {
                return ['links' => $links];
            }
            // Lijst van records (bv. salable quantity per bron): "bron: aantal" per record
            $parts = [];
            foreach ($value as $v) {
                if (is_array($v)) {
                    $name = $v['source_code'] ?? $v['stock_name'] ?? null;
                    if ($name !== null && array_key_exists('qty', $v)) {
                        $parts[] = $name . ': ' . $v['qty']; // MSI: voorraadbron/stock met aantal
                    } else {
                        $parts[] = implode(' ', array_map('strval', array_filter($v, fn($x) => is_scalar($x) && !is_bool($x))));
                    }
                } elseif (is_scalar($v)) {
                    $parts[] = (string) $v;
                }
            }
            return ['text' => implode(', ', array_filter($parts, 'strlen'))];
        }
        // Select-kolommen: optiewaarde naar label
        $options = $column ? $this->options($column) : [];
        if ($options) {
            $labels = [];
            foreach (explode(',', (string) $value) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $labels[] = $options[$part] ?? $part;
                }
            }
            return ['text' => implode(', ', $labels)];
        }
        $str = (string) $value;
        if ($str !== strip_tags($str)) {
            return ['html' => $str];
        }
        return ['text' => $str];
    }

    /** @return array<string,string> */
    private function options(UiComponentInterface $column): array
    {
        $config = (array) $column->getData('config');
        $options = $config['options'] ?? null;
        if (is_object($options) && method_exists($options, 'toOptionArray')) {
            $options = $options->toOptionArray();
        }
        if (!is_array($options)) {
            return [];
        }
        $out = [];
        foreach ($options as $o) {
            if (is_array($o) && isset($o['value']) && !is_array($o['value'])) {
                $out[(string) $o['value']] = (string) ($o['label'] ?? $o['value']);
            }
        }
        return $out;
    }
}
