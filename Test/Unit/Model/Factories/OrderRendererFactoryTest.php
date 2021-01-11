<?php

namespace EinsUndEins\TransactionMailExtender\Test\Unit\Model\Factories;

use EinsUndEins\SchemaOrgMailBody\Model\Order;
use EinsUndEins\SchemaOrgMailBody\Renderer\OrderRenderer;
use EinsUndEins\TransactionMailExtender\Model\Factories\OrderRendererFactory;
use PHPUnit\Framework\TestCase;

class OrderRendererFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $order = $this->createMock(Order::class);
        $orderRendererFactory = new OrderRendererFactory();
        $orderRenderer = $orderRendererFactory->create($order);

        $this->assertInstanceOf(OrderRenderer::class, $orderRenderer);
    }
}
