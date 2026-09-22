<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Product;
use App\Service\PromotionService;
use PHPUnit\Framework\TestCase;

class PromotionServiceTest extends TestCase
{
    private PromotionService $service;

    protected function setUp(): void
    {
        $this->service = new PromotionService();
    }

    public function testReturnsNormalPriceWhenNoPromotion(): void
    {
        $product = $this->createProduct(50.00);

        $this->assertSame(50.00, $this->service->getCurrentPrice($product));
        $this->assertFalse($this->service->isOnPromotion($product));
    }

    public function testReturnsPromoPriceDuringPeriod(): void
    {
        $product = $this->createProduct(50.00, 35.00);
        $now = new \DateTimeImmutable('2026-08-15 12:00:00');

        $this->assertTrue($this->service->isOnPromotion($product, $now));
        $this->assertSame(35.00, $this->service->getCurrentPrice($product, $now));
    }

    public function testReturnsNormalPriceBeforePromotionPeriod(): void
    {
        $product = $this->createProduct(50.00, 35.00);
        $now = new \DateTimeImmutable('2026-07-31 23:59:59');

        $this->assertFalse($this->service->isOnPromotion($product, $now));
        $this->assertSame(50.00, $this->service->getCurrentPrice($product, $now));
    }

    public function testReturnsNormalPriceAfterPromotionPeriod(): void
    {
        $product = $this->createProduct(50.00, 35.00);
        $now = new \DateTimeImmutable('2026-09-01 00:00:00');

        $this->assertFalse($this->service->isOnPromotion($product, $now));
        $this->assertSame(50.00, $this->service->getCurrentPrice($product, $now));
    }

    public function testPromoPriceEqualToNormalIsNotActive(): void
    {
        $product = $this->createProduct(50.00, 50.00);
        $now = new \DateTimeImmutable('2026-08-15 12:00:00');

        $this->assertFalse($this->service->isOnPromotion($product, $now));
        $this->assertSame(50.00, $this->service->getCurrentPrice($product, $now));
    }

    public function testPromoPriceGreaterThanNormalIsNotActive(): void
    {
        $product = $this->createProduct(50.00, 60.00);
        $now = new \DateTimeImmutable('2026-08-15 12:00:00');

        $this->assertFalse($this->service->isOnPromotion($product, $now));
        $this->assertSame(50.00, $this->service->getCurrentPrice($product, $now));
    }

    public function testInvertedDatesAreNotActive(): void
    {
        $product = new Product();
        $product->setName('Test Product')->setPrice(50.00);
        $product->setPromoPrice(35.00);
        $product->setPromoStartsAt(new \DateTimeImmutable('2026-08-31 23:59:59'));
        $product->setPromoEndsAt(new \DateTimeImmutable('2026-08-01 00:00:00'));

        $now = new \DateTimeImmutable('2026-08-15 12:00:00');

        $this->assertFalse($this->service->isOnPromotion($product, $now));
    }

    public function testBoundaryStartIsIncluded(): void
    {
        $product = $this->createProduct(50.00, 35.00);
        $now = new \DateTimeImmutable('2026-08-01 00:00:00');

        $this->assertTrue($this->service->isOnPromotion($product, $now));
    }

    public function testBoundaryEndIsIncluded(): void
    {
        $product = $this->createProduct(50.00, 35.00);
        $now = new \DateTimeImmutable('2026-08-31 23:59:59');

        $this->assertTrue($this->service->isOnPromotion($product, $now));
    }

    private function createProduct(float $price, ?float $promoPrice = null): Product
    {
        $product = new Product();
        $product->setName('Test Product')->setPrice($price);

        if (null !== $promoPrice) {
            $product->setPromoPrice($promoPrice);
            $product->setPromoStartsAt(new \DateTimeImmutable('2026-08-01 00:00:00'));
            $product->setPromoEndsAt(new \DateTimeImmutable('2026-08-31 23:59:59'));
        }

        return $product;
    }
}
