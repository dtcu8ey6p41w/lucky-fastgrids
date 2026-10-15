<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\View\Element\UiComponentFactory;

/**
 * Snelle stand van een standaardpagina: zet de toolbar-knoppen van het UI-grid
 * (bv. "Create New Order", "Add New Customer", knoppen van andere modules en de switch)
 * in de pagina-toolbar zonder het grid zelf te renderen. Gebruikt dezelfde code
 * als het UI-grid (Context::addButtons), dus stijl, volgorde en ACL-checks zijn identiek.
 *
 * Layout-argument "grid": naam van het UI-component (sales_order_grid, customer_listing).
 */
class GridButtons extends Template
{
    public function __construct(
        Context $context,
        private UiComponentFactory $uiComponentFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _beforeToHtml()
    {
        $grid = (string) $this->getData('grid');
        if ($grid !== '') {
            try {
                // Alleen het componentobject bouwen (geen data laden) om de knopconfiguratie te krijgen.
                $component = $this->uiComponentFactory->create($grid);
                $buttons = (array) $component->getData('buttons');
                if ($buttons) {
                    $component->getContext()->addButtons($buttons, $component);
                }
            } catch (\Throwable $e) {
                $this->_logger->error('Lucky_FastGrids: toolbar-knoppen van ' . $grid . ' niet geladen: ' . $e->getMessage());
            }
        }
        return parent::_beforeToHtml();
    }

    protected function _toHtml()
    {
        return '';
    }
}
