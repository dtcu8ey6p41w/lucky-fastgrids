<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Block\Adminhtml\Config;

use Lucky\FastGrids\Observer\SaveDefaults;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Configuratieveld met twee knoppen:
 * - "Standaardinstellingen opslaan": verstuurt het formulier en legt de huidige vinkjes vast als standaard;
 * - "Terug naar standaard": zet de vinkjes van alle grids terug naar die standaard.
 */
class ResetButton extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        return sprintf(
            '<button type="submit" name="%1$s" value="1" class="action-default scalable primary" style="margin-right:1rem"><span>%2$s</span></button>'
            . '<a href="%3$s" class="action-default scalable" style="display:inline-block"><span>%4$s</span></a>'
            . '<p class="note" style="margin-top:.6rem"><span>%5$s</span></p>',
            $this->escapeHtmlAttr(SaveDefaults::PARAM),
            $this->escapeHtml(__('Standaardinstellingen opslaan')),
            $this->escapeUrl($this->getUrl('fastgrids/config/reset')),
            $this->escapeHtml(__('Terug naar standaard')),
            $this->escapeHtml(__('"Standaardinstellingen opslaan" slaat de vinkjes hieronder op én maakt ze de nieuwe standaard. "Terug naar standaard" zet de vinkjes van alle drie de grids terug naar die standaard; de opgehaalde kolomlijsten blijven bewaard.'))
        );
    }

    protected function _renderScopeLabel(AbstractElement $element): string
    {
        return '';
    }
}
