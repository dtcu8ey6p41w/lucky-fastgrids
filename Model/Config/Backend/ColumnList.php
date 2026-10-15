<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

/**
 * De kolomkiezer post een array van aangevinkte sleutels; opslaan als komma-lijst in volgorde.
 */
class ColumnList extends Value
{
    public function beforeSave()
    {
        $value = $this->getValue();
        if (is_array($value)) {
            $value = implode(',', array_filter(array_map('trim', array_map('strval', $value)), 'strlen'));
        }
        $this->setValue((string) $value);
        return parent::beforeSave();
    }
}
