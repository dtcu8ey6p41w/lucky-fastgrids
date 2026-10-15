<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Block\Adminhtml\Button;

use Lucky\FastGrids\Model\GridMode;

class OrdersSwitcher extends AbstractSwitcher
{
    protected function grid(): string
    {
        return GridMode::ORDERS;
    }

    protected function fastLabel(): string
    {
        return (string) __('Orders Fast');
    }

    protected function modeRoute(): string
    {
        return 'fastgrids/orders/mode';
    }
}
