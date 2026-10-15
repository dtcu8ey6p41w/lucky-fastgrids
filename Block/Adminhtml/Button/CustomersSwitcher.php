<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Block\Adminhtml\Button;

use Lucky\FastGrids\Model\GridMode;

class CustomersSwitcher extends AbstractSwitcher
{
    protected function grid(): string
    {
        return GridMode::CUSTOMERS;
    }

    protected function fastLabel(): string
    {
        return (string) __('Customers Fast');
    }

    protected function modeRoute(): string
    {
        return 'fastgrids/customers/mode';
    }
}
