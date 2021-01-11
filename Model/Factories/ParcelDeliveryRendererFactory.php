<?php

namespace EinsUndEins\TransactionMailExtender\Model\Factories;

use EinsUndEins\SchemaOrgMailBody\Model\ParcelDelivery;
use EinsUndEins\SchemaOrgMailBody\Renderer\ParcelDeliveryRenderer;

class ParcelDeliveryRendererFactory
{
    public function create(ParcelDelivery $parcelDelivery): ParcelDeliveryRenderer
    {
        return new ParcelDeliveryRenderer($parcelDelivery);
    }
}
