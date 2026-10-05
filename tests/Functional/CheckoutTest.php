<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Entity\Product;
use App\Service\CartService;

class CheckoutTest extends FunctionalTestCase
{
    public function testCheckoutPageRequiresCart(): void
    {
        $this->login('client@example.com');

        $this->client->request('GET', '/checkout');

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_cart');
    }

    public function testCheckoutCreatesOrder(): void
    {
        $this->login('client@example.com');
        $user = $this->findUser('client@example.com');

        // 1. Ajouter un produit au panier
        $product = $this->repository(Product::class)->findOneBy(['reference' => 'CAT-001']);
        $this->assertNotNull($product);

        $cartService = $this->client->getContainer()->get(CartService::class);
        $cart = $cartService->getOrCreateCart($user);
        $cartService->addProduct($cart, $product, 1);

        // 2. Remplir le formulaire de checkout
        $crawler = $this->client->request('GET', '/checkout');
        $token = $crawler->filter('input[name="checkout_form[_token]"]')->attr('value');

        $this->client->request('POST', '/checkout', [
            'checkout_form' => [
                'addressLine' => '123 Rue Test',
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
                '_token' => $token,
            ],
        ]);

        // 3. Vérifier que l'order est créée
        $this->assertResponseRedirects();
        $order = $this->repository(Order::class)->findOneBy(['user' => $user]);
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->getStatus()->value);
    }

    public function testPaymentCreatesPaidOrder(): void
    {
        $this->login('client@example.com');
        $user = $this->findUser('client@example.com');

        // Setup panier + checkout (comme ci-dessus)
        $product = $this->repository(Product::class)->findOneBy(['reference' => 'CAT-001']);
        $cartService = $this->client->getContainer()->get(CartService::class);
        $cart = $cartService->getOrCreateCart($user);
        $cartService->addProduct($cart, $product, 1);

        $crawler = $this->client->request('GET', '/checkout');
        $token = $crawler->filter('input[name="checkout_form[_token]"]')->attr('value');

        $this->client->request('POST', '/checkout', [
            'checkout_form' => [
                'addressLine' => '123 Rue Test',
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
                '_token' => $token,
            ],
        ]);

        $order = $this->repository(Order::class)->findOneBy(['user' => $user]);

        // 4. Payer la commande
        $this->client->request('POST', '/orders/'.$order->getId().'/pay');

        $this->assertResponseRedirects();
        $this->entityManager()->clear();

        $paidOrder = $this->repository(Order::class)->find($order->getId());
        $this->assertSame('paid', $paidOrder->getStatus()->value);
    }

    public function testConfirmationPageIsDisplayed(): void
    {
        $order = $this->createPendingOrder();
        $orderNumber = $order->getNumber();

        $this->client->request('POST', '/orders/'.$order->getId().'/pay');

        $this->assertResponseRedirects();
        $this->client->followRedirect();

        $this->assertRouteSame('app_order_show');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', $orderNumber);
    }

    private function createPendingOrder(): Order
    {
        $this->login('client@example.com');
        $user = $this->findUser('client@example.com');

        $product = $this->repository(Product::class)->findOneBy(['reference' => 'CAT-001']);
        $this->assertNotNull($product);

        $cartService = $this->client->getContainer()->get(CartService::class);
        $cart = $cartService->getOrCreateCart($user);
        $cartService->addProduct($cart, $product, 1);

        $crawler = $this->client->request('GET', '/checkout');
        $token = $crawler->filter('input[name="checkout_form[_token]"]')->attr('value');

        $this->client->request('POST', '/checkout', [
            'checkout_form' => [
                'addressLine' => '123 Rue Test',
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
                '_token' => $token,
            ],
        ]);

        $order = $this->repository(Order::class)->findOneBy(['user' => $user]);
        $this->assertNotNull($order);

        return $order;
    }
}
