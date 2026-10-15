<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Controller\Adminhtml\Config;

use Lucky\FastGrids\Model\Catalog\Registry;
use Lucky\FastGrids\Model\Config\GridColumns;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;

/**
 * Knop "Standaardinstellingen": zet de kolomkeuze van alle grids terug naar de standaard.
 */
class Reset extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Lucky_FastGrids::config';

    public function __construct(
        Context $context,
        private Registry $registry,
        private GridColumns $gridColumns
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        foreach ($this->registry->grids() as $grid) {
            $this->gridColumns->resetSelection($grid);
        }
        $this->messageManager->addSuccessMessage((string) __('De kolommen van Orders Fast, Customers Fast en Products Fast staan weer op de standaardinstelling.'));

        return $this->resultRedirectFactory->create()->setPath('adminhtml/system_config/edit', ['section' => 'lucky_fastgrids']);
    }
}
