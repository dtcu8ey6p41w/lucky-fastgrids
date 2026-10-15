<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Observer;

use Lucky\FastGrids\Model\GridMode;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Voegt op de standaardpagina's een extra layout-handle toe die het UI-grid vervangt door
 * het snelle grid, zolang de switch-cookie van dat grid aanstaat.
 */
class AddFastGridHandle implements ObserverInterface
{
    private const HANDLES = [
        'sales_order_index'     => [GridMode::ORDERS, 'sales_order_index_fastgrids'],
        'customer_index_index'  => [GridMode::CUSTOMERS, 'customer_index_index_fastgrids'],
        'catalog_product_index' => [GridMode::PRODUCTS, 'catalog_product_index_fastgrids'],
    ];

    public function __construct(
        private GridMode $gridMode
    ) {
    }

    public function execute(Observer $observer): void
    {
        $action = (string) $observer->getData('full_action_name');
        if (!isset(self::HANDLES[$action])) {
            return;
        }
        [$grid, $handle] = self::HANDLES[$action];
        if (!$this->gridMode->isFast($grid)) {
            return;
        }
        $layout = $observer->getData('layout');
        if ($layout) {
            $layout->getUpdate()->addHandle($handle);
        }
    }
}
