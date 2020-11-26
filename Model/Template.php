<?php

namespace EinsUndEins\TransactionMailExtender\Model;

use EinsUndEins\SchemaOrgMailBody\Model\Order;
use EinsUndEins\SchemaOrgMailBody\Model\ParcelDelivery;
use EinsUndEins\SchemaOrgMailBody\Renderer\OrderRenderer;
use EinsUndEins\SchemaOrgMailBody\Renderer\ParcelDeliveryRenderer;
use Exception;
use InvalidArgumentException;
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

        if (in_array($this->getOrderEmails(), $this->getId())) {
            $text = $this->extendOrderData($text);
        }

        if (in_array($this->getParcelDeliveryEmails(), $this->getId())) {
            $text = $this->extendParcelDeliveryData($text);
        }

        return $text;
    }

    /**
     * Render the order information in valid schema.org html and add it to the text
     *
     * @param string $text
     *
     * @return string
     */
    private function extendOrderData(string $text): string
    {
        try{
            if ($this->order) {
                throw new Exception('Couldn\'t get order from the email variables');
            }

            $orderNumber = $this->order->getId();
            $orderStatus = $this->getSchemaOrderStatusFromOrder($this->order);
            $shopName = $this->order->getStoreName();

            $order = new Order($orderNumber, $orderStatus, $shopName);
            $orderRenderer = new OrderRenderer($order);
            $extension = $orderRenderer->render();
            $text = self::replaceLast('</body>', $extension . '</body>', $text);
        } catch (Exception $e) {
            $this->_logger->error($e->getMessage());
        } finally {
            return $text;
        }
    }

    /**
     * Render the shipment information in valid schema.org html and add it to the text
     *
     * @param string $text
     *
     * @return string
     */
    private function extendParcelDeliveryData(string $text): string
    {
        try {
            if ($this->shipment) {
                throw new Exception('Couldn\'t get shipment from the email variables');
            }

            $orderStatus = $this->getSchemaOrderStatusFromOrder($this->shipment->getOrder());
            $orderNumber = $this->shipment->getOrderId();
            $shopName = $this->shipment->getStore()->getName();
            foreach ($this->shipment->getTracksCollection() as $track) {
                $deliveryName = $track->getTitle();
                $trackingNumber = $track->getTrackNumber();

                try {
                    $parcelDelivery = new ParcelDelivery($deliveryName, $trackingNumber, $orderNumber, $orderStatus, $shopName);
                } catch (InvalidArgumentException $e) {
                    $this->_logger->error($e->getMessage());

                    continue;
                }

                $parcelDeliveryRenderer = new ParcelDeliveryRenderer($parcelDelivery);
                $extension = $parcelDeliveryRenderer->render();
                $text = self::replaceLast('</body>', $extension . '</body>', $text);
            }
        } catch (Exception $e) {
            $this->_logger->error($e->getMessage());
        } finally {
            return $text;
        }
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
     * @param OrderInterface $order
     *
     * @return string
     * @throws Exception
     */
    private function getSchemaOrderStatusFromOrder(OrderInterface $order): string
    {
        $mageOrderStatus = $order->getStatus();
        $schemaOrderStatus = $this->getOrderStatusMatrix()[$mageOrderStatus];
        if (!$schemaOrderStatus) {
            throw new Exception('Magento order status \'' . $mageOrderStatus . '\' not configured to schema.org order status.');
        }

        return $schemaOrderStatus;
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
        // @TODO give it as array back
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
