<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model;

use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Directory\Model\Config\Source\Country as CountrySource;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as StatusCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Opzoektabellen (labels) die één keer per request lui worden opgebouwd.
 */
class Lookups
{
    private array $cache = [];

    public function __construct(
        private GroupCollectionFactory $groupCollectionFactory,
        private StatusCollectionFactory $statusCollectionFactory,
        private PaymentHelper $paymentHelper,
        private CountrySource $countrySource,
        private StoreManagerInterface $storeManager,
        private EavConfig $eavConfig,
        private AttributeSetCollectionFactory $attributeSetCollectionFactory,
        private ProductType $productType
    ) {
    }

    /** @return array<string,string> group_id => code, '0' => Gast */
    public function customerGroups(): array
    {
        return $this->cache['groups'] ??= (function () {
            $out = ['0' => (string) __('Gast')];
            foreach ($this->groupCollectionFactory->create() as $g) {
                if ((int) $g->getId() !== 0) {
                    $out[(string) $g->getId()] = (string) $g->getCode();
                }
            }
            return $out;
        })();
    }

    /** @return array<string,string> status => label */
    public function orderStatuses(): array
    {
        return $this->cache['statuses'] ??= $this->statusCollectionFactory->create()->toOptionHash();
    }

    /** @return array<string,string> code => titel */
    public function paymentTitles(): array
    {
        return $this->cache['payments'] ??= (array) $this->paymentHelper->getPaymentMethodList();
    }

    /** @return array<string,string> landcode => naam */
    public function countries(): array
    {
        return $this->cache['countries'] ??= (function () {
            $out = [];
            foreach ($this->countrySource->toOptionArray() as $o) {
                if ($o['value'] !== '') {
                    $out[(string) $o['value']] = (string) $o['label'];
                }
            }
            return $out;
        })();
    }

    /** @return array<int,string> website_id => naam */
    public function websites(): array
    {
        return $this->cache['websites'] ??= (function () {
            $out = [];
            foreach ($this->storeManager->getWebsites() as $w) {
                $out[(int) $w->getId()] = (string) $w->getName();
            }
            return $out;
        })();
    }

    /** @return array<int,string> store_id => naam */
    public function stores(): array
    {
        return $this->cache['stores'] ??= (function () {
            $out = [];
            foreach ($this->storeManager->getStores(true) as $s) {
                $out[(int) $s->getId()] = (string) $s->getName();
            }
            return $out;
        })();
    }

    /** Optielabels van een select/multiselect-attribuut. @return array<string,string> */
    public function attributeOptions(string $entityType, string $code): array
    {
        $k = 'opt:' . $entityType . ':' . $code;
        if (!isset($this->cache[$k])) {
            $this->cache[$k] = [];
            try {
                $attr = $this->eavConfig->getAttribute($entityType, $code);
                if ($attr && $attr->getId() && $attr->usesSource()) {
                    foreach ($attr->getSource()->getAllOptions() as $o) {
                        if (isset($o['value']) && $o['value'] !== '' && $o['value'] !== null && !is_array($o['value'])) {
                            $this->cache[$k][(string) $o['value']] = (string) $o['label'];
                        }
                    }
                }
            } catch (\Throwable) {
                // attribuut zonder bron: lege lijst
            }
        }
        return $this->cache[$k];
    }

    /** @return array<int,string> attribute_set_id => naam (producten) */
    public function attributeSets(): array
    {
        return $this->cache['attrsets'] ??= (function () {
            $out = [];
            $typeId = (int) $this->eavConfig->getEntityType('catalog_product')->getId();
            foreach ($this->attributeSetCollectionFactory->create()->setEntityTypeFilter($typeId) as $set) {
                $out[(int) $set->getId()] = (string) $set->getAttributeSetName();
            }
            return $out;
        })();
    }

    /** @return array<string,string> type_id => label */
    public function productTypes(): array
    {
        return $this->cache['ptypes'] ??= (function () {
            $out = [];
            foreach ($this->productType->getOptionArray() as $k => $v) {
                $out[(string) $k] = (string) $v;
            }
            return $out;
        })();
    }

    public function mediaBaseUrl(): string
    {
        try {
            return (string) $this->storeManager->getDefaultStoreView()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        } catch (\Throwable) {
            return '/media/';
        }
    }
}
