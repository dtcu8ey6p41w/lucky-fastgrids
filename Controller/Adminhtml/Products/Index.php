<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Controller\Adminhtml\Products;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Lucky_FastGrids::products';

    public function __construct(
        Context $context,
        private PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Lucky_FastGrids::products');
        $page->addBreadcrumb((string) __('Catalog'), (string) __('Catalog'));
        $page->addBreadcrumb((string) __('Products Fast'), (string) __('Products Fast'));
        $page->getConfig()->getTitle()->prepend((string) __('Products Fast'));

        return $page;
    }
}
