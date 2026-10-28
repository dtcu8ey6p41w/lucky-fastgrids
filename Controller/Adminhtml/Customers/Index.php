<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Controller\Adminhtml\Customers;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Pagina "Customers Fast": rendert alleen de admin-chrome en het template.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Lucky_FastGrids::customers';
    /** Het snelle grid toont dezelfde gegevens als het standaardgrid: dat recht is ook nodig. */
    private const STANDARD_RESOURCE = 'Magento_Customer::manage';

    public function __construct(
        Context $context,
        private PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Lucky_FastGrids::customers');
        $page->addBreadcrumb((string) __('Customers'), (string) __('Customers'));
        $page->addBreadcrumb((string) __('Customers Fast'), (string) __('Customers Fast'));
        $page->getConfig()->getTitle()->prepend((string) __('Customers Fast'));

        return $page;
    }

    protected function _isAllowed(): bool
    {
        return parent::_isAllowed() && $this->_authorization->isAllowed(self::STANDARD_RESOURCE);
    }
}
