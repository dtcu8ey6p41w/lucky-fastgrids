<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model;

use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponentInterface;
use Magento\Ui\Component\Listing\Columns;

/**
 * Leest de kolommen (naam => label) van een standaard UI-grid, inclusief kolommen die
 * andere modules via ui_component-XML of plugins hebben toegevoegd. Het component wordt
 * alleen opgebouwd en voorbereid; er wordt geen data geladen.
 */
class UiColumns
{
    public function __construct(
        private UiComponentFactory $uiComponentFactory
    ) {
    }

    /** @return array<string,string> kolomnaam => label */
    public function labels(string $componentName): array
    {
        $component = $this->uiComponentFactory->create($componentName);
        $this->prepare($component);
        $out = [];
        $this->collect($component, $out);
        return $out;
    }

    private function prepare(UiComponentInterface $component): void
    {
        foreach ($component->getChildComponents() as $child) {
            $this->prepare($child);
        }
        try {
            $component->prepare();
        } catch (\Throwable) {
            // sommige kolommen van derden falen bij prepare zonder request-context; dan alleen de config
        }
    }

    private function collect(UiComponentInterface $component, array &$out): void
    {
        if ($component instanceof Columns) {
            foreach ($component->getChildComponents() as $name => $column) {
                $config = (array) $column->getData('config');
                $label = (string) ($config['label'] ?? '');
                if ($label === '' || in_array($name, ['ids'], true)) {
                    continue;
                }
                $out[(string) $name] = $label;
            }
            return;
        }
        foreach ($component->getChildComponents() as $child) {
            $this->collect($child, $out);
        }
    }
}
