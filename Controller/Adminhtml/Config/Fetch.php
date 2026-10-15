<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Controller\Adminhtml\Config;

use Lucky\FastGrids\Model\Catalog\CustomersCatalog;
use Lucky\FastGrids\Model\Catalog\OrdersCatalog;
use Lucky\FastGrids\Model\Catalog\ProductsCatalog;
use Lucky\FastGrids\Model\Catalog\Registry;
use Lucky\FastGrids\Model\Config\GridColumns;
use Lucky\FastGrids\Model\PluginColumns;
use Lucky\FastGrids\Model\UiColumns;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Redirect;

/**
 * Knop "Kolommen ophalen": leest de kolommen van het standaard UI-grid, slaat de labels op en
 * meet van de kolommen die een plugin per rij berekent hoeveel ms ze kosten op 20 echte rijen.
 */
class Fetch extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Lucky_FastGrids::config';
    private const SAMPLE = 20;

    public function __construct(
        Context $context,
        private Registry $registry,
        private UiColumns $uiColumns,
        private GridColumns $gridColumns,
        private PluginColumns $pluginColumns,
        private ResourceConnection $resource
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $grid = (string) $this->getRequest()->getParam('grid');
        try {
            $catalog = $this->registry->get($grid);
            $labels = $this->uiColumns->labels($catalog->uiComponent());

            // Welke UI-kolommen halen wij niet zelf uit de database? Die meten we.
            $native = $catalog->available();
            $pluginKeys = [];
            foreach (array_keys($labels) as $name) {
                if (!isset($native[$name]) || ($native[$name]['src'] ?? '') === 'plugin') {
                    $known = false;
                    foreach ($native as $def) {
                        if (($def['ui'] ?? null) === $name) {
                            $known = true;
                            break;
                        }
                    }
                    if (!$known && $name !== 'ids') {
                        $pluginKeys[] = $name;
                    }
                }
            }
            $impact = $pluginKeys ? $this->pluginColumns->measure($catalog->uiComponent(), $pluginKeys, $this->sample($grid)) : [];

            $this->gridColumns->saveLabels($grid, $labels, $impact);
            $this->messageManager->addSuccessMessage((string) __(
                '%1 kolommen opgehaald uit het standaardgrid "%2", waarvan %3 door een plugin per rij berekend (gemeten op %4 rijen). Vink aan wat je wilt tonen en klik op Save Config.',
                count($labels), $catalog->uiComponent(), count($impact), self::SAMPLE
            ));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage((string) __('Kolommen ophalen mislukt: %1', $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath('adminhtml/system_config/edit', ['section' => 'lucky_fastgrids']);
    }

    /** 20 echte rijen van de gridtabel, zoals het standaardgrid ze aan de kolomklassen geeft. */
    private function sample(string $grid): array
    {
        $conn = $this->resource->getConnection();
        $table = match ($grid) {
            OrdersCatalog::GRID => OrdersCatalog::TABLE,
            CustomersCatalog::GRID => CustomersCatalog::TABLE,
            ProductsCatalog::GRID => ProductsCatalog::TABLE,
            default => null,
        };
        if ($table === null) {
            return [];
        }
        $select = $conn->select()->from($this->resource->getTableName($table))->order('entity_id DESC')->limit(self::SAMPLE);
        return $conn->fetchAll($select);
    }
}
