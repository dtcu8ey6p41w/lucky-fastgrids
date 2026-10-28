<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

/**
 * Onthoudt per browser (cookie) of een standaardgrid in de snelle stand staat.
 * $grid is 'orders', 'customers' of 'products'. De snelle stand geldt alleen als de beheerder
 * ook het ACL-recht van het snelle grid heeft.
 */
class GridMode
{
    public const ORDERS = 'orders';
    public const CUSTOMERS = 'customers';
    public const PRODUCTS = 'products';
    private const LIFETIME = 365 * 24 * 3600;

    /** ACL-recht van het snelle grid, per grid. */
    public const RESOURCES = [
        self::ORDERS    => 'Lucky_FastGrids::orders',
        self::CUSTOMERS => 'Lucky_FastGrids::customers',
        self::PRODUCTS  => 'Lucky_FastGrids::products',
    ];

    public function __construct(
        private CookieManagerInterface $cookieManager,
        private CookieMetadataFactory $cookieMetadataFactory,
        private AuthorizationInterface $authorization
    ) {
    }

    public function isAllowed(string $grid): bool
    {
        return isset(self::RESOURCES[$grid]) && $this->authorization->isAllowed(self::RESOURCES[$grid]);
    }

    public function isFast(string $grid): bool
    {
        return $this->isAllowed($grid)
            && (string) $this->cookieManager->getCookie($this->cookieName($grid)) === '1';
    }

    public function setFast(string $grid, bool $fast): void
    {
        $meta = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setDuration(self::LIFETIME)
            ->setPath('/')
            ->setHttpOnly(true)
            ->setSameSite('Lax');
        $this->cookieManager->setPublicCookie($this->cookieName($grid), $fast ? '1' : '0', $meta);
    }

    private function cookieName(string $grid): string
    {
        return 'lucky_fastgrids_' . preg_replace('/[^a-z]/', '', strtolower($grid));
    }
}
