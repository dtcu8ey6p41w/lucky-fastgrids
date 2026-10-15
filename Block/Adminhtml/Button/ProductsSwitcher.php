<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Block\Adminhtml\Button;

use Lucky\FastGrids\Model\GridMode;

class ProductsSwitcher extends AbstractSwitcher
{
    protected function grid(): string
    {
        return GridMode::PRODUCTS;
    }

    protected function fastLabel(): string
    {
        return (string) __('Products Fast');
    }

    protected function modeRoute(): string
    {
        return 'fastgrids/products/mode';
    }
}
