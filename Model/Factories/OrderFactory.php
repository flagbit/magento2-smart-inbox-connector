<?php

namespace EinsUndEins\TransactionMailExtender\Model\Factories;

use EinsUndEins\SchemaOrgMailBody\Model\Order;

class OrderFactory
{
    public function create(
        string $orderNumber,
        string $orderStatus,
        string $shopName
    ): Order {
        return new Order($orderNumber, $orderStatus, $shopName);
    }
}
