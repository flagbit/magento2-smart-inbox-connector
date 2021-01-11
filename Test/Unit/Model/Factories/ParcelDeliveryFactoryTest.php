<?php

namespace EinsUndEins\TransactionMailExtender\Test\Unit\Model\Factories;

use EinsUndEins\SchemaOrgMailBody\Model\ParcelDelivery;
use EinsUndEins\TransactionMailExtender\Model\Factories\ParcelDeliveryFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track;
use PHPUnit\Framework\TestCase;

class ParcelDeliveryFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $deliveryName = 'deliveryName';
        $trackingNumber = 'trackingNumber';
        $orderNumber = 'orderNumber';
        $orderStatus = 'orderStatus';
        $shopName = 'shop.com';

        $track = $this->createMock(Track::class);
        $track->method('getTitle')
            ->willReturn($deliveryName);
        $track->method('getTrackNumber')
            ->willReturn($trackingNumber);

        $parcelDeliveryFactory = new ParcelDeliveryFactory();
        $parcelDelivery = $parcelDeliveryFactory->create($track, $orderNumber, $orderStatus, $shopName);

        $this->assertInstanceOf(ParcelDelivery::class, $parcelDelivery);

        $this->assertEquals($deliveryName, $parcelDelivery->getDeliveryName());
        $this->assertEquals($trackingNumber, $parcelDelivery->getTrackingNumber());
        $this->assertEquals($orderNumber, $parcelDelivery->getOrderNumber());
        $this->assertEquals($orderStatus, $parcelDelivery->getOrderStatus());
        $this->assertEquals($shopName, $parcelDelivery->getShopName());
    }
}
