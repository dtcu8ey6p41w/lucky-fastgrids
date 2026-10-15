<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Controller\Adminhtml\Customers;

use Lucky\FastGrids\Model\GridMode;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;

/**
 * Switcher: ?fast=1 schakelt Customers > All Customers naar het snelle grid, ?fast=0 terug.
 */
class Mode extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Customer::manage';

    public function __construct(
        Context $context,
        private GridMode $gridMode
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $this->gridMode->setFast(GridMode::CUSTOMERS, (string) $this->getRequest()->getParam('fast') === '1');

        return $this->resultRedirectFactory->create()->setPath('customer/index/index');
    }
}
