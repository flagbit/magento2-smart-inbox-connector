<?php

namespace EinsUndEins\TransactionMailExtender\Test\Unit\Model\Factories;

use EinsUndEins\SchemaOrgMailBody\Model\ParcelDelivery;
use EinsUndEins\SchemaOrgMailBody\Renderer\ParcelDeliveryRenderer;
use EinsUndEins\TransactionMailExtender\Model\Factories\ParcelDeliveryRendererFactory;
use PHPUnit\Framework\TestCase;

class ParcelDeliveryRendererFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $parcelDelivery = $this->createMock(ParcelDelivery::class);

        $parcelDeliveryRendererFactory = new ParcelDeliveryRendererFactory();

        $parcelDeliveryRenderer = $parcelDeliveryRendererFactory->create($parcelDelivery);

        $this->assertInstanceOf(ParcelDeliveryRenderer::class, $parcelDeliveryRenderer);
    }
}
