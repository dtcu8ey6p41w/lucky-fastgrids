<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Catalog;

/**
 * Vindt de catalogus bij een gridnaam (orders, customers, products).
 */
class Registry
{
    public function __construct(
        private OrdersCatalog $orders,
        private CustomersCatalog $customers,
        private ProductsCatalog $products
    ) {
    }

    public function get(string $grid): CatalogInterface
    {
        return match ($grid) {
            OrdersCatalog::GRID => $this->orders,
            CustomersCatalog::GRID => $this->customers,
            ProductsCatalog::GRID => $this->products,
            default => throw new \InvalidArgumentException('Onbekend grid: ' . $grid),
        };
    }

    /** @return string[] */
    public function grids(): array
    {
        return [OrdersCatalog::GRID, CustomersCatalog::GRID, ProductsCatalog::GRID];
    }
}
