<?php

namespace Flagbit\TransactionMailExtender\Model;

use Magento\Email\Model\Template as MageTemplate;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;

class Template extends MageTemplate
{
    /** @var OrderInterface */
    private $order;
    /** @var Shipmentinterface */
    private $shipment;

    public function processTemplate()
    {
        $text = parent::processTemplate();

        // @TODO extend email body if needed

        return $text;
    }

    /**
     * Fetch the order and the shipment from the from the vars
     */
    private function fetchOrderAndShipment()
    {
        $this->order = null;
        $this->shipment = null;
        foreach($this->_vars as $var) {
            if ($var instanceof OrderInterface) {
                $this->order = $var;
            }
            if ($var instanceof ShipmentInterface) {
                $this->shipment = $var;
            }
        }
    }

    /**
     * Replace the last found entry in a string
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     *
     * @return string
     */
    static private function replaceLast(string $search, string $replace, string $subject): string
    {
        $pos = strrpos($subject, $search);
        if($pos !== false)
        {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }
}
