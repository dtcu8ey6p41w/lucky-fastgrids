<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Configuratieveld dat een knop "Kolommen ophalen" toont (link naar de Fetch-controller).
 * Het grid wordt afgeleid uit het veld-id: lucky_fastgrids_<grid>_fetch.
 */
class FetchButton extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $grid = $this->gridFromElement($element);
        $url = $this->getUrl('fastgrids/config/fetch', ['grid' => $grid]);
        return sprintf(
            '<a href="%s" class="action-default scalable primary" style="display:inline-block"><span>%s</span></a>'
            . '<p class="note" style="margin-top:.6rem"><span>%s</span></p>',
            $this->escapeUrl($url),
            $this->escapeHtml(__('Kolommen ophalen')),
            $this->escapeHtml(__('Leest alle kolommen van het standaard Magento-grid (ook die van andere modules) en zet ze in de lijst hieronder.'))
        );
    }

    protected function _renderScopeLabel(AbstractElement $element): string
    {
        return '';
    }

    private function gridFromElement(AbstractElement $element): string
    {
        if (preg_match('/^lucky_fastgrids_([a-z]+)_/', (string) $element->getHtmlId(), $m)) {
            return $m[1];
        }
        return 'orders';
    }
}
