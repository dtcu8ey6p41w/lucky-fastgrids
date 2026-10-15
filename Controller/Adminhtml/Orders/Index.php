<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Controller\Adminhtml\Orders;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Pagina "Orders Fast": rendert alleen de admin-chrome en het template.
 * De rijen komen via de Data-controller (JSON).
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Lucky_FastGrids::orders';

    public function __construct(
        Context $context,
        private PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Lucky_FastGrids::orders');
        $page->addBreadcrumb((string) __('Sales'), (string) __('Sales'));
        $page->addBreadcrumb((string) __('Orders Fast'), (string) __('Orders Fast'));
        $page->getConfig()->getTitle()->prepend((string) __('Orders Fast'));

        return $page;
    }
}
