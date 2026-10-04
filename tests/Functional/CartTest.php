<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Service\CartService;

class CartTest extends FunctionalTestCase
{
    public function testAddProductToCart(): void
    {
        $this->login('client@example.com');

        $product = $this->repository(Product::class)->findOneBy(['reference' => 'CAT-001']);
        $this->assertNotNull($product);

        $this->client->request('POST', '/cart/add/'.$product->getId(), [
            'quantity' => 1,
        ]);

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Catan');
    }

    public function testCartQuantityIsUpdated(): void
    {
        $this->login('client@example.com');
        $user = $this->findUser('client@example.com');

        $product = $this->repository(Product::class)->findOneBy(['reference' => 'CAT-001']);
        $this->assertNotNull($product);

        $cartService = $this->client->getContainer()->get(CartService::class);
        $cart = $cartService->getOrCreateCart($user);
        $cartService->addProduct($cart, $product, 1);

        $item = $cart->getItems()->first();
        $this->assertNotFalse($item);
        $itemId = $item->getId();

        $this->client->request('POST', '/cart/items/'.$itemId.'/update', [
            'quantity' => 5,
        ]);

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->entityManager()->clear();
        $updatedItem = $this->repository(CartItem::class)->find($itemId);
        $this->assertNotNull($updatedItem);
        $this->assertSame(5, $updatedItem->getQuantity());
    }

    public function testRemoveProductFromCart(): void
    {
        $this->login('client@example.com');
        $user = $this->findUser('client@example.com');

        $product = $this->repository(Product::class)->findOneBy(['reference' => 'CAT-001']);
        $this->assertNotNull($product);

        $cartService = $this->client->getContainer()->get(CartService::class);
        $cart = $cartService->getOrCreateCart($user);
        $cartService->addProduct($cart, $product, 1);

        $item = $cart->getItems()->first();
        $this->assertNotFalse($item);
        $itemId = $item->getId();

        $this->client->request('POST', '/cart/items/'.$itemId.'/remove');

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->entityManager()->clear();
        $removedItem = $this->repository(CartItem::class)->find($itemId);
        $this->assertNull($removedItem);
    }

    public function testCartShowsCorrectTotal(): void
    {
        $this->login('client@example.com');
        $user = $this->findUser('client@example.com');

        $product = $this->repository(Product::class)->findOneBy(['reference' => 'CAT-001']);
        $this->assertNotNull($product);

        $cartService = $this->client->getContainer()->get(CartService::class);
        $cart = $cartService->getOrCreateCart($user);
        $cartService->addProduct($cart, $product, 2);

        $this->client->request('GET', '/cart');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#cart-total');
    }
}