<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Block\Adminhtml\Config;

use Lucky\FastGrids\Model\Catalog\Registry;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Configuratieveld met vinkjes voor alle beschikbare kolommen van een grid.
 * Aangevinkte kolommen worden (in deze volgorde) opgeslagen als komma-lijst.
 * Langzame kolommen (per rij door een plugin berekend) zijn kiesbaar, met de gemeten impact erbij.
 */
class ColumnsField extends Field
{
    public function __construct(
        Context $context,
        private Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $grid = $this->gridFromElement($element);
        $catalog = $this->registry->get($grid);
        $available = $catalog->available();

        $current = array_keys($catalog->selected());
        $order = $current;
        foreach (array_keys($available) as $key) {
            if (!in_array($key, $order, true)) {
                $order[] = $key;
            }
        }

        $name = $element->getName() . '[]';
        $html = '<div class="lucky-fastgrids-columns" style="columns:2;column-gap:3rem;max-width:90rem">';
        $slow = 0;
        foreach ($order as $key) {
            $def = $available[$key];
            $checked = in_array($key, $current, true) ? ' checked' : '';
            $id = $element->getHtmlId() . '_' . preg_replace('/[^a-z0-9_]/i', '_', $key);
            if ($def['fast']) {
                $html .= sprintf(
                    '<label for="%1$s" style="display:block;break-inside:avoid;padding:.2rem 0"><input type="checkbox" id="%1$s" name="%2$s" value="%3$s"%4$s> %5$s <span style="color:#999">(%3$s)</span></label>',
                    $this->escapeHtmlAttr($id), $this->escapeHtmlAttr($name), $this->escapeHtmlAttr($key), $checked, $this->escapeHtml($def['label'])
                );
            } else {
                $slow++;
                $html .= sprintf(
                    '<label for="%1$s" style="display:block;break-inside:avoid;padding:.2rem 0" title="%6$s"><input type="checkbox" id="%1$s" name="%2$s" value="%3$s"%4$s> %5$s <span style="color:#999">(%3$s)</span> <span style="color:#e22626;font-weight:600" title="%6$s">&#9888;</span></label>',
                    $this->escapeHtmlAttr($id), $this->escapeHtmlAttr($name), $this->escapeHtmlAttr($key), $checked, $this->escapeHtml($def['label']),
                    $this->escapeHtml((string) ($def['reason'] ?? __('langzaam')))
                );
            }
        }
        $html .= '</div>';
        $html .= sprintf('<input type="hidden" name="%s" value="">', $this->escapeHtmlAttr($name));
        $note = (string) __('Alleen aangevinkte kolommen worden geladen en getoond, in deze volgorde (in het grid zelf kun je ze nog verslepen).');
        if ($slow) {
            $note .= ' ' . (string) __('&#9888; = wordt door een plugin per rij berekend (langzamer); de gemeten tijd zie je als je met de muis op het icoon staat.');
        }
        $html .= '<p class="note" style="margin-top:.6rem"><span>' . $note . '</span></p>';
        return $html;
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
