<?php

namespace EinsUndEins\TransactionMailExtender\Model\Factories;

use EinsUndEins\SchemaOrgMailBody\Model\Order;
use EinsUndEins\SchemaOrgMailBody\Renderer\OrderRenderer;

class OrderRendererFactory
{
    public function create(Order $order): OrderRenderer
    {
        return new OrderRenderer($order);
    }
}
