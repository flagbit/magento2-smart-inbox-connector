<?php

namespace EinsUndEins\TransactionMailExtender\Model\Factories;

use EinsUndEins\SchemaOrgMailBody\Model\ParcelDelivery;

class ParcelDeliveryFactory
{
    public function create(
        string $deliveryName,
        string $trackingNumber,
        string $orderNumber,
        string $orderStatus,
        string $shopName
    ): ParcelDelivery {
        return new ParcelDelivery(
            $deliveryName,
            $trackingNumber,
            $orderNumber,
            $orderStatus,
            $shopName
        );
    }
}
