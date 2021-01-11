<?php

namespace EinsUndEins\TransactionMailExtender\Test\Unit\Model\Factories;

use EinsUndEins\SchemaOrgMailBody\Model\Order;
use EinsUndEins\TransactionMailExtender\Model\Factories\OrderFactory;
use PHPUnit\Framework\TestCase;

class OrderFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $orderNumber = 'orderNumber';
        $orderStatus = 'orderStatus';
        $shopName    = 'shop.com';

        $orderFactory = new OrderFactory();
        $order        = $orderFactory->create($orderNumber, $orderStatus, $shopName);

        $this->assertInstanceOf(Order::class, $order);

        $this->assertEquals($orderNumber, $order->getOrderNumber());
        $this->assertEquals($orderStatus, $order->getOrderStatus());
        $this->assertEquals($shopName, $order->getShopName());
    }
}
