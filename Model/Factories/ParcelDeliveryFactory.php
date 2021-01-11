<?php

namespace EinsUndEins\TransactionMailExtender\Model\Factories;

use EinsUndEins\SchemaOrgMailBody\Model\ParcelDelivery;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track;

class ParcelDeliveryFactory
{
    public function create(
        Track $track,
        string $orderNumber,
        string $orderStatus,
        string $shopName
    ): ParcelDelivery {
        $deliveryName = $track->getTitle();
        $trackingNumber = $track->getTrackNumber();
        return new ParcelDelivery(
            $deliveryName,
            $trackingNumber,
            $orderNumber,
            $orderStatus,
            $shopName
        );
    }
}
