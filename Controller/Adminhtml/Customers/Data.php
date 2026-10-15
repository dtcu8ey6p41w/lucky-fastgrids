<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Controller\Adminhtml\Customers;

use Lucky\FastGrids\Model\Customers\CustomerList;
use Lucky\FastGrids\Model\Customers\RowFormatter;
use Lucky\FastGrids\Model\DateFormatter;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * JSON-endpoint voor de Customers Fast-lijst.
 */
class Data extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Lucky_FastGrids::customers';

    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private CustomerList $customerList,
        private RowFormatter $formatter,
        private DateFormatter $dates
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $start = microtime(true);
        $r = $this->getRequest();

        $filters = [
            'page'         => (int) $r->getParam('page', 1),
            'limit'        => (int) $r->getParam('limit', CustomerList::DEFAULT_LIMIT),
            'sort'         => (string) $r->getParam('sort', 'entity_id'),
            'dir'          => (string) $r->getParam('dir', 'desc'),
            'q'            => (string) $r->getParam('q', ''),
            'group_id'     => array_values(array_filter((array) $r->getParam('group_id', []), 'strlen')),
            'created_from' => $this->dates->localDateToUtc((string) $r->getParam('created_from', ''), false),
            'created_to'   => $this->dates->localDateToUtc((string) $r->getParam('created_to', ''), true),
        ];

        $result = $this->jsonFactory->create();
        try {
            $data = $this->customerList->search($filters);
            $data['items'] = array_map([$this->formatter, 'format'], $data['items']);
            $data['took_ms'] = (int) round((microtime(true) - $start) * 1000);
            $result->setData($data);
        } catch (\Throwable $e) {
            $result->setHttpResponseCode(500);
            $result->setData(['error' => $e->getMessage()]);
        }

        return $result;
    }
}
