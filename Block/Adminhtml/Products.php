<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Block\Adminhtml;

use Lucky\FastGrids\Model\Catalog\ProductsCatalog;
use Lucky\FastGrids\Model\Lookups;
use Lucky\FastGrids\Model\Products\ProductList;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Levert de template de kolomdefinities (uit de configuratie), filteropties en een JSON-config.
 */
class Products extends Template
{
    public function __construct(
        Context $context,
        private ProductsCatalog $catalog,
        private Lookups $lookups,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array<string, array{0:string,1:?string,2:bool}> */
    public function getColumns(): array
    {
        $out = [];
        foreach ($this->catalog->selected() as $key => $def) {
            $out[$key] = [(string) $def['label'], $def['sort'] ? $key : null, true];
        }
        return $out;
    }

    public function getStatusOptions(): array
    {
        return $this->lookups->attributeOptions('catalog_product', 'status');
    }

    public function getVisibilityOptions(): array
    {
        return $this->lookups->attributeOptions('catalog_product', 'visibility');
    }

    public function getTypeOptions(): array
    {
        return $this->lookups->productTypes();
    }

    public function getAttributeSetOptions(): array
    {
        $sets = $this->lookups->attributeSets();
        asort($sets);
        return $sets;
    }

    public function getConfigJson(): string
    {
        $types = [];
        foreach ($this->catalog->selected() as $key => $def) {
            $types[$key] = $def['type'];
        }
        return json_encode([
            'dataUrl'      => $this->getUrl('fastgrids/products/data'),
            'editUrl'      => $this->getUrl('catalog/product/edit', ['id' => '__ID__']),
            'mediaUrl'     => rtrim($this->lookups->mediaBaseUrl(), '/') . '/catalog/product',
            'defaultLimit' => ProductList::DEFAULT_LIMIT,
            'formKey'      => $this->getFormKey(),
            'colTypes'     => $types,
            'descByDefault'=> ['entity_id', 'created_at', 'updated_at', 'qty', 'price'],
            'labels'       => [
                'rows' => (string) __('producten'), 'shown' => (string) __('getoond'), 'openRow' => (string) __('Open product'),
                'edit' => (string) __('Bewerken'), 'loadError' => (string) __('Fout bij laden'), 'reload' => (string) __('Sessie verlopen of geen rechten; herlaad de pagina.'), 'chosen' => (string) __('gekozen'),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
