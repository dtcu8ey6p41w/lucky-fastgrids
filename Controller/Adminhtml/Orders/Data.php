<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Controller\Adminhtml\Orders;

use Lucky\FastGrids\Model\DateFormatter;
use Lucky\FastGrids\Model\Orders\OrderList;
use Lucky\FastGrids\Model\Orders\RowFormatter;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

/**
 * JSON-endpoint voor de Orders Fast-lijst.
 */
class Data extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Lucky_FastGrids::orders';
    /** Het snelle grid toont dezelfde gegevens als het standaardgrid: dat recht is ook nodig. */
    private const STANDARD_RESOURCE = 'Magento_Sales::sales_order';

    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private OrderList $orderList,
        private RowFormatter $formatter,
        private DateFormatter $dates,
        private LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $start = microtime(true);
        $r = $this->getRequest();

        $filters = [
            'page'           => (int) $r->getParam('page', 1),
            'limit'          => (int) $r->getParam('limit', OrderList::DEFAULT_LIMIT),
            'sort'           => (string) $r->getParam('sort', 'created_at'),
            'dir'            => (string) $r->getParam('dir', 'desc'),
            'q'              => (string) $r->getParam('q', ''),
            'status'         => array_values(array_filter((array) $r->getParam('status', []), 'strlen')),
            'customer_group' => array_values(array_filter((array) $r->getParam('customer_group', []), 'strlen')),
            'payment_method' => array_values(array_filter((array) $r->getParam('payment_method', []), 'strlen')),
            'created_from'   => $this->dates->localDateToUtc((string) $r->getParam('created_from', ''), false),
            'created_to'     => $this->dates->localDateToUtc((string) $r->getParam('created_to', ''), true),
            'delivery_from'  => $this->dates->plainDate((string) $r->getParam('delivery_from', '')),
            'delivery_to'    => $this->dates->plainDate((string) $r->getParam('delivery_to', '')),
        ];

        $result = $this->jsonFactory->create();
        try {
            $data = $this->orderList->search($filters);
            $data['items'] = array_map([$this->formatter, 'format'], $data['items']);
            $data['sum_formatted'] = $this->formatter->money((float) $data['sum'], 'EUR');
            $data['took_ms'] = (int) round((microtime(true) - $start) * 1000);
            $result->setData($data);
        } catch (\Throwable $e) {
            // Volledige fout naar het log (en Sentry); de browser krijgt geen SQL-/stackdetails
            $this->logger->error('Lucky_FastGrids: laden van orders mislukt: ' . $e->getMessage(), ['exception' => $e]);
            $result->setHttpResponseCode(500);
            $result->setData(['error' => (string) __('Er ging iets mis bij het laden; de fout staat in het log.')]);
        }

        return $result;
    }

    protected function _isAllowed(): bool
    {
        return parent::_isAllowed() && $this->_authorization->isAllowed(self::STANDARD_RESOURCE);
    }
}
