<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Observer;

use Lucky\FastGrids\Model\Catalog\Registry;
use Lucky\FastGrids\Model\Config\GridColumns;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Knop "Standaardinstellingen opslaan" in Stores > Configuration > Lucky > Fast Grids.
 * De knop verstuurt het configuratieformulier (de vinkjes worden dus eerst gewoon opgeslagen);
 * daarna slaan we dezelfde keuze op als nieuwe standaard voor "Terug naar standaard".
 */
class SaveDefaults implements ObserverInterface
{
    public const PARAM = 'lucky_fastgrids_save_defaults';

    public function __construct(
        private RequestInterface $request,
        private Registry $registry,
        private GridColumns $gridColumns,
        private ManagerInterface $messageManager
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ((string) $this->request->getParam(self::PARAM) !== '1') {
            return;
        }
        $groups = (array) $this->request->getParam('groups', []);
        foreach ($this->registry->grids() as $grid) {
            $keys = array_values(array_filter(
                array_map('strval', (array) ($groups[$grid]['fields']['columns']['value'] ?? [])),
                'strlen'
            ));
            if (!$keys) {
                // niets aangevinkt: huidige (standaard)keuze van dit grid vastleggen
                $keys = array_keys($this->registry->get($grid)->selected());
            }
            $this->gridColumns->saveDefaults($grid, $keys);
        }
        $this->messageManager->addSuccessMessage((string) __('De huidige kolomkeuze is opgeslagen als standaardinstelling voor Orders Fast, Customers Fast en Products Fast.'));
    }
}
