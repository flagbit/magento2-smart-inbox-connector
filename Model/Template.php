<?php

namespace EinsUndEins\TransactionMailExtender\Model;

use EinsUndEins\SchemaOrgMailBody\Model\OrderFactory;
use EinsUndEins\SchemaOrgMailBody\Model\ParcelDeliveryFactory;
use EinsUndEins\SchemaOrgMailBody\Renderer\OrderRendererFactory;
use EinsUndEins\SchemaOrgMailBody\Renderer\ParcelDeliveryRendererFactory;
use EinsUndEins\TransactionMailExtender\Block\Adminhtml\Form\Field\OrderStatusMatrix;
use Exception;
use InvalidArgumentException;
use Magento\Email\Model\Template as MageTemplate;
use Magento\Email\Model\Template\Config;
use Magento\Email\Model\Template\FilterFactory;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filter\FilterManager;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\DesignInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

class Template extends MageTemplate
{
    private const CONFIG_PATH            = 'transaction_mail_extender/general/';
    private const MODULE_ENABLED         = 'enable';
    private const PARCEL_DELIVERY_EMAILS = 'parcel_delivery_emails';
    private const ORDER_EMAILS           = 'order_emails';
    private const ORDER_STATUS_MATRIX    = 'order_status_matrix';
    /** @var OrderFactory $orderFactory */
    private $orderFactory;
    /** @var OrderRendererFactory $orderRendererFactory */
    private $orderRendererFactory;
    /** @var ParcelDeliveryFactory $parcelDeliveryFactory */
    private $parcelDeliveryFactory;
    /** @var ParcelDeliveryRendererFactory $parcelDeliveryRendererFactory */
    private $parcelDeliveryRendererFactory;
    /** @var OrderInterface|null */
    private $order;
    /** @var Shipmentinterface|null */
    private $shipment;

    public function __construct(
        Context $context,
        DesignInterface $design,
        Registry $registry,
        Emulation $appEmulation,
        StoreManagerInterface $storeManager,
        Repository $assetRepo,
        Filesystem $filesystem,
        ScopeConfigInterface $scopeConfig,
        Config $emailConfig,
        TemplateFactory $templateFactory,
        FilterManager $filterManager,
        UrlInterface $urlModel,
        FilterFactory $filterFactory,
        OrderFactory $orderFactory,
        ParcelDeliveryFactory $parcelDeliveryFactory,
        OrderRendererFactory $orderRendererFactory,
        ParcelDeliveryRendererFactory $parcelDeliveryRendererFactory,
        array $data = [],
        Json $serializer = null
    ) {
        $this->orderFactory                  = $orderFactory;
        $this->parcelDeliveryFactory         = $parcelDeliveryFactory;
        $this->orderRendererFactory          = $orderRendererFactory;
        $this->parcelDeliveryRendererFactory = $parcelDeliveryRendererFactory;
        parent::__construct(
            $context,
            $design,
            $registry,
            $appEmulation,
            $storeManager,
            $assetRepo,
            $filesystem,
            $scopeConfig,
            $emailConfig,
            $templateFactory,
            $filterManager,
            $urlModel,
            $filterFactory,
            $data,
            $serializer
        );
    }

    public function processTemplate()
    {
        $text = parent::processTemplate();
        if (!$this->getModuleEnabled()) {
            return $text;
        }

        $this->initOrderAndShipment();

        if (in_array($this->getId(), $this->getOrderEmails())) {
            $text = $this->extendOrderData($text);
        }

        if (in_array($this->getId(), $this->getParcelDeliveryEmails())) {
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
        try {
            if (null === $this->order) {
                throw new Exception('Couldn\'t get order from the email variables');
            }

            $orderNumber = $this->order->getId();
            $orderStatus = $this->getSchemaOrderStatusFromOrder($this->order);
            $shopName    = $this->order->getStoreName();

            $order         = $this->orderFactory->create(
                [
                    'orderNumber' => $orderNumber,
                    'orderStatus' => $orderStatus,
                    'shopName'    => $shopName,
                ]
            );
            $orderRenderer = $this->orderRendererFactory->create([ 'order' => $order ]);
            $extension     = $orderRenderer->render();
            $text          = self::replaceLast('</body>', $extension . '</body>', $text);
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
            if (null === $this->shipment) {
                throw new Exception('Couldn\'t get shipment from the email variables');
            }

            $orderStatus = $this->getSchemaOrderStatusFromOrder($this->shipment->getOrder());
            $orderNumber = $this->shipment->getOrderId();
            $shopName    = $this->shipment->getStore()->getName();
            foreach ($this->shipment->getTracksCollection() as $track) {
                try {
                    $deliveryName   = $track->getTitle();
                    $trackingNumber = $track->getTrackNumber();
                    $parcelDelivery = $this->parcelDeliveryFactory->create(
                        [
                            'deliveryName' => $deliveryName,
                            'trackingNumber' => $trackingNumber,
                            'orderNumber' => $orderNumber,
                            'orderStatus' => $orderStatus,
                            'shopName' => $shopName
                        ]
                    );
                    $parcelDeliveryRenderer = $this->parcelDeliveryRendererFactory->create(['parcelDelivery' => $parcelDelivery]);
                    $extension              = $parcelDeliveryRenderer->render();
                    $text                   = self::replaceLast('</body>', $extension . '</body>', $text);
                } catch (InvalidArgumentException $e) {
                    $this->_logger->error($e->getMessage());

                    continue;
                }
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
    private function initOrderAndShipment(): void
    {
        $this->order    = null;
        $this->shipment = null;
        foreach ($this->_vars as $var) {
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
        if ($pos !== false) {
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
        $mageOrderStatus   = $order->getStatus();
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
    private function getOrderStatusMatrix(): array
    {
        $origin   = json_decode($this->getConfigValue(self::ORDER_STATUS_MATRIX));
        $remapped = [];
        foreach ($origin as $entry) {
            $remapped[$entry->{OrderStatusMatrix::MAGE_STATUS_KEY}] = $entry->{OrderStatusMatrix::SCHEMA_ORG_STATUS_KEY};
        }

        return $remapped;
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
    private function getConfigValue(string $key): ?string
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH . $key,
            'store',
            $this->storeManager->getStore()->getId()
        );
    }
}
