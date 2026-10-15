<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Block\Adminhtml\Button;

use Lucky\FastGrids\Model\GridMode;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Toolbar-knop om te wisselen tussen het standaard UI-grid en het snelle grid.
 * Gedefinieerd als UI-component-knop, zodat hij exact zo rendert als de andere knoppen.
 */
abstract class AbstractSwitcher implements ButtonProviderInterface
{
    public function __construct(
        protected UrlInterface $urlBuilder,
        protected GridMode $gridMode
    ) {
    }

    abstract protected function grid(): string;

    abstract protected function fastLabel(): string;

    abstract protected function modeRoute(): string;

    public function getButtonData(): array
    {
        $fast = $this->gridMode->isFast($this->grid());
        $url = $this->urlBuilder->getUrl($this->modeRoute(), ['fast' => $fast ? 0 : 1]);

        return [
            'label'      => $fast ? __('Standaard grid') : $this->fastLabel(),
            'on_click'   => sprintf("location.href = '%s';", $url),
            'class'      => 'action-secondary',
            'sort_order' => 30,
        ];
    }
}
