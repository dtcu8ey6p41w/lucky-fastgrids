<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Controller\Adminhtml\Products;

use Lucky\FastGrids\Model\Products\ProductList;
use Lucky\FastGrids\Model\Products\RowFormatter;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class Data extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Lucky_FastGrids::products';

    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private ProductList $productList,
        private RowFormatter $formatter
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $start = microtime(true);
        $r = $this->getRequest();

        $filters = [
            'page'             => (int) $r->getParam('page', 1),
            'limit'            => (int) $r->getParam('limit', ProductList::DEFAULT_LIMIT),
            'sort'             => (string) $r->getParam('sort', 'entity_id'),
            'dir'              => (string) $r->getParam('dir', 'desc'),
            'q'                => (string) $r->getParam('q', ''),
            'status'           => array_values(array_filter((array) $r->getParam('status', []), 'strlen')),
            'visibility'       => array_values(array_filter((array) $r->getParam('visibility', []), 'strlen')),
            'type_id'          => array_values(array_filter((array) $r->getParam('type_id', []), 'strlen')),
            'attribute_set_id' => (int) $r->getParam('attribute_set_id', 0),
        ];

        $result = $this->jsonFactory->create();
        try {
            $data = $this->productList->search($filters);
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
