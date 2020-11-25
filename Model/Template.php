<?php

namespace Flagbit\TransactionMailExtender\Model;

use Magento\Email\Model\Template as MageTemplate;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;

class Template extends MageTemplate
{
    private const CONFIG_PATH = 'transaction_mail_extender/general/';
    private const MODULE_ENABLED = 'enable';
    private const PARCEL_DELIVERY_EMAILS = 'parcel_delivery_emails';
    private const ORDER_EMAILS = 'order_emails';
    private const ORDER_STATUS_MATRIX = 'order_status_matrix';

    /** @var OrderInterface */
    private $order;
    /** @var Shipmentinterface */
    private $shipment;

    public function processTemplate()
    {
        $text = parent::processTemplate();
        if (!$this->getModuleEnabled()) {
            return $text;
        }

        $this->fetchOrderAndShipment();

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

    /**
     * Get the order status matrix
     *
     * @return array
     * @throws NoSuchEntityException
     */
    private function getOrderStatusMatrix()
    {
        return json_decode($this->getConfigValue(self::ORDER_STATUS_MATRIX));
    }

    /**
     * Get the email ids on which should be extended with the order information
     *
     * @return array
     * @throws NoSuchEntityException
     */
    private function getOrderEmails(): array
    {
        return explode(',', $this->getConfigValue(self::ORDER_EMAILS));
    }

    /**
     * Get the email ids on which should be extended with the parcel delivery information
     *
     * @return array
     * @throws NoSuchEntityException
     */
    private function getParcelDeliveryEmails(): array
    {
        return explode(',', $this->getConfigValue(self::PARCEL_DELIVERY_EMAILS));
    }

    /**
     * Is the module enabled
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    private function getModuleEnabled(): bool
    {
        return (bool)$this->getConfigValue(self::MODULE_ENABLED);
    }

    /**
     * Get a specific config value
     *
     * @param string $key
     *
     * @return string|null
     * @throws NoSuchEntityException
     */
    private function getConfigValue(string $key)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH . $key,
            'store',
            $this->storeManager->getStore()->getId()
        );
    }
}
