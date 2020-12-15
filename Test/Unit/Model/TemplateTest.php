<?php

namespace EinsUndEins\TransactionMailExtender\Test\Unit\Model;

use EinsUndEins\SchemaOrgMailBody\Model\OrderFactory;
use EinsUndEins\SchemaOrgMailBody\Model\OrderInterface as EinsUndEinsOrderInterface;
use EinsUndEins\SchemaOrgMailBody\Model\ParcelDelivery;
use EinsUndEins\SchemaOrgMailBody\Model\ParcelDeliveryFactory;
use EinsUndEins\SchemaOrgMailBody\Renderer\OrderRenderer;
use EinsUndEins\SchemaOrgMailBody\Renderer\OrderRendererFactory;
use EinsUndEins\SchemaOrgMailBody\Renderer\ParcelDeliveryRenderer;
use EinsUndEins\SchemaOrgMailBody\Renderer\ParcelDeliveryRendererFactory;
use EinsUndEins\TransactionMailExtender\Model\Template;
use EinsUndEins\TransactionMailExtender\Test\Unit\TestCase;
use InvalidArgumentException;
use Magento\Email\Model\ResourceModel\Template as ResourceTemplate;
use Magento\Email\Model\Template\Config;
use Magento\Email\Model\Template\Filter;
use Magento\Email\Model\Template\FilterFactory;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filter\FilterManager;
use Magento\Framework\Model\ActionValidator\RemoveAction;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Magento\Sales\Api\Data\OrderInterface as MageOrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManager;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\Test\TestLogger;

class TemplateTest extends TestCase
{
    public function testProcessTemplateModuleNotEnabled(): void
    {
        $scopeConfigStub = $this->createScopeConfigStub(false);
        $expected        = $this->getOrigTemplateText();

        $logger = new TestLogger();

        $template = $this->createTemplate($scopeConfigStub, $expected, $logger, '');
        $template->setTemplateId('email_id_1');
        $template->setTemplateFilter($this->createTemplateFilterStub());
        $template->setVars($this->getNeededVars());

        $actual = $template->processTemplate();

        $this->assertLoggerHasNoRecords($logger);
        $this->assertEquals($expected, $actual);
    }

    public function testProcessTemplate(): void
    {
        $templateText = $this->getOrigTemplateText();
        $expected     = '<html lang="en">' .
            '<head><title>foo</title></head>' .
            '<body>' .
            '<h1>bar</h1><span>text<!--</body>--></span>' .
            '<div>eins-und-eins-library-order-result</div>' .
            '<div>eins-und-eins-library-parcel-delivery-0-result</div>' .
            '<div>eins-und-eins-library-parcel-delivery-1-result</div>' .
            '<div>eins-und-eins-library-parcel-delivery-2-result</div>' .
            '</body>' .
            '</html>';

        $scopeConfigStub = $this->createScopeConfigStub(true);

        $logger   = new TestLogger();
        $template = $this->createTemplate($scopeConfigStub, $templateText, $logger, 'orderCancelled');
        $template->setTemplateId('email_id_1');
        $template->setTemplateFilter($this->createTemplateFilterStub());
        $vars = $this->getNeededVars();
        $vars = array_merge(
            $vars,
            [
                $this->createMageOrderStub('cancelled'),
                $this->createShipmentStub('delivered', 3),
            ]
        );
        $template->setVars($vars);

        $actual = $template->processTemplate();

        $this->assertEquals($expected, $actual);
        $this->assertLoggerHasNoRecords($logger);
    }

    public function testProcessTemplateWithoutOrderObject(): void
    {
        $templateText = $this->getOrigTemplateText();
        $expected     = '<html lang="en">' .
            '<head><title>foo</title></head>' .
            '<body>' .
            '<h1>bar</h1><span>text<!--</body>--></span>' .
            '<div>eins-und-eins-library-parcel-delivery-0-result</div>' .
            '<div>eins-und-eins-library-parcel-delivery-1-result</div>' .
            '<div>eins-und-eins-library-parcel-delivery-2-result</div>' .
            '</body>' .
            '</html>';

        $scopeConfigStub = $this->createScopeConfigStub(true);
        $logger          = new TestLogger();
        $template        = $this->createTemplate(
            $scopeConfigStub,
            $templateText,
            $logger,
            ''
        );
        $template->setTemplateId('email_id_1');
        $template->setTemplateFilter($this->createTemplateFilterStub());
        $vars = $this->getNeededVars();
        $vars = array_merge(
            $vars,
            [
                $this->createShipmentStub('delivered', 3),
            ]
        );
        $template->setVars($vars);

        $actual = $template->processTemplate();

        $this->assertTrue($logger->hasErrorThatContains('Couldn\'t get order from the email variables'));
        $this->assertEquals($expected, $actual);
    }

    public function testProcessTemplateWithoutShipmentObject(): void
    {
        $templateText = $this->getOrigTemplateText();
        $expected     = '<html lang="en">' .
            '<head><title>foo</title></head>' .
            '<body>' .
            '<h1>bar</h1><span>text<!--</body>--></span>' .
            '<div>eins-und-eins-library-order-result</div>' .
            '</body>' .
            '</html>';

        $scopeConfigStub = $this->createScopeConfigStub(true);
        $logger          = new TestLogger();

        $template = $this->createTemplate(
            $scopeConfigStub,
            $templateText,
            $logger,
            'orderCancelled'
        );
        $template->setTemplateId('email_id_1');
        $template->setTemplateFilter($this->createTemplateFilterStub());
        $vars = $this->getNeededVars();
        $vars = array_merge(
            $vars,
            [
                $this->createMageOrderStub('cancelled'),
            ]
        );
        $template->setVars($vars);

        $actual = $template->processTemplate();

        $this->assertTrue($logger->hasErrorThatContains('Couldn\'t get shipment from the email variables'));
        $this->assertEquals($expected, $actual);
    }

    public function testProcessTemplateWithWrongOrderStatus(): void
    {
        $expected = $this->getOrigTemplateText();

        $scopeConfigStub = $this->createScopeConfigStub(true);

        $logger   = new TestLogger();
        $template = $this->createTemplate(
            $scopeConfigStub,
            $expected,
            $logger,
            'orderCancelled',
            true
        );
        $template->setTemplateId('email_id_1');
        $template->setTemplateFilter($this->createTemplateFilterStub());
        $vars = $this->getNeededVars();
        $vars = array_merge(
            $vars,
            [
                $this->createMageOrderStub('cancelled'),
                $this->createShipmentStub('delivered', 3),
            ]
        );
        $template->setVars($vars);

        $actual = $template->processTemplate();

        $this->assertTrue($logger->hasErrorThatContains('Status is not one of the possible status.'));
        $this->assertEquals($expected, $actual);
    }

    /**
     * @param ScopeConfigInterface $scopeConfigStub
     * @param string               $templateText
     * @param LoggerInterface      $logger
     * @param string               $orderStatus
     * @param bool                 $orderStatusWrong
     *
     * @return Template
     */
    private function createTemplate(
        ScopeConfigInterface $scopeConfigStub,
        string $templateText,
        LoggerInterface $logger,
        string $orderStatus,
        bool $orderStatusWrong = false
    ): Template {
        $contextStub                      = $this->createContextStub($logger);
        $storeStub                        = $this->createStoreStub();
        $storeManagerStub                 = $this->createStoreManagerStub($storeStub);
        $designThemeStub                  = $this->createDesignThemeStub();
        $designStub                       = $this->createDesignStub($designThemeStub);
        $registryStub                     = $this->createMock(Registry::class);
        $appEmulationStub                 = $this->createAppEmulationStub();
        $assetRepoStub                    = $this->createMock(Repository::class);
        $filesystemStub                   = $this->createFilesystemStub($templateText);
        $emailConfigStub                  = $this->createEmailConfigStub($designThemeStub);
        $templateFactoryStub              = $this->createFactoryStub('Magento\Email\Model\TemplateFactory');
        $filterManagerStub                = $this->createMock(FilterManager::class);
        $urlModelStub                     = $this->createMock(UrlInterface::class);
        $filterFactoryStub                = $this->createFactoryStub('Magento\Email\Model\Template\FilterFactory');
        $orderStub                        = $this->createMock(EinsUndEinsOrderInterface::class);
        $orderFactoryStub                 = $this->createOrderFactoryStub($orderStub, $orderStatus, $orderStatusWrong);
        $orderRendererFactoryStub         = $this->createOrderRendererFactoryStub($orderStub);
        $parcelDeliveryStubs              = $this->createParcelDeliveryStubArray(3);
        $parcelDeliveryFactoryStub        = $this->createParcelDeliveryFactoryStub($parcelDeliveryStubs, $orderStatusWrong);
        $parcelDeliveryRendererFactorySub = $this->createParcelDeliveryRendererFactoryStub($parcelDeliveryStubs);
        $serializerStub                   = $this->createMock(Json::class);
        $databaseStub                     = $this->createMock(Database::class);
        $resourceTemplateStub             = $this->createResourceTemplateStub();
        $this->mockObjectManager(
            [
                [ Database::class, $databaseStub ],
                [ ResourceTemplate::class, $resourceTemplateStub ],
            ]
        );

        return new Template(
            $contextStub,
            $designStub,
            $registryStub,
            $appEmulationStub,
            $storeManagerStub,
            $assetRepoStub,
            $filesystemStub,
            $scopeConfigStub,
            $emailConfigStub,
            $templateFactoryStub,
            $filterManagerStub,
            $urlModelStub,
            $filterFactoryStub,
            $orderFactoryStub,
            $parcelDeliveryFactoryStub,
            $orderRendererFactoryStub,
            $parcelDeliveryRendererFactorySub,
            [],
            $serializerStub
        );
    }

    /**
     * Create scope config stub
     *
     * @param bool $moduleEnabled
     *
     * @return ScopeConfigInterface&MockObject
     */
    private function createScopeConfigStub(bool $moduleEnabled)
    {
        $scopeConfigStub = $this->createMock(ScopeConfigInterface::class);
        $scopeConfigStub->method('getValue')
            ->will(
                $this->returnValueMap(
                    [
                        [
                            'transaction_mail_extender/general/enable',
                            'store',
                            1,
                            ($moduleEnabled ? '1' : '0'),
                        ],
                        [
                            'transaction_mail_extender/general/order_emails',
                            'store',
                            1,
                            'email_id_1,email_id_2,email_id_3',
                        ],
                        [
                            'transaction_mail_extender/general/parcel_delivery_emails',
                            'store',
                            1,
                            'email_id_1,email_id_3,email_id_4',
                        ],
                        [
                            'transaction_mail_extender/general/order_status_matrix',
                            'store',
                            1,
                            '[' .
                            '{"mage_status": "cancelled",' .
                            '"schema_org_status": "orderCancelled"},' .
                            '{"mage_status": "delivered",' .
                            '"schema_org_status": "orderDelivered"},' .
                            '{"mage_status": "not-existent",' .
                            '"schema_org_status": "notExistent"}]',
                        ],
                    ]
                )
            );

        return $scopeConfigStub;
    }

    /**
     * Create template filter stub
     *
     * @return Filter&MockObject
     */
    private function createTemplateFilterStub()
    {
        $templateFilterStub = $this->createMock(Filter::class);

        $templateFilterStub->method('setUseSessionInUrl')
            ->with(false)
            ->willReturnSelf();

        $templateFilterStub->method('setPlainTemplateMode')
            ->with(false)
            ->willReturnSelf();

        $templateFilterStub->method('setIsChildTemplate')
            ->willReturnSelf();

        $templateFilterStub->method('setTemplateProcessor')
            ->willReturnSelf();

        $templateFilterStub->method('setDesignParams');
        $templateFilterStub->method('setStoreId');
        $templateFilterStub->method('setVariables');
        $templateFilterStub->method('setStrictMode');
        $templateFilterStub->method('filter')
            ->will($this->returnArgument(0));

        return $templateFilterStub;
    }

    /**
     * Create shipment stub
     *
     * @param string $orderStatus
     * @param int    $numberTracks
     *
     * @return ShipmentInterface&MockObject
     */
    private function createShipmentStub(string $orderStatus, int $numberTracks)
    {
        $shipmentStub = $this->getMockBuilder(Shipment::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'getOrder', 'getOrderId', 'getStore', 'getTracksCollection' ])
            ->getMock();
        $shipmentStub->method('getOrder')
            ->willReturn($this->createMageOrderStub($orderStatus));
        $shipmentStub->method('getOrderId')
            ->willReturn(1);
        $shipmentStub->method('getStore')
            ->willReturn($this->createStoreStub());
        $tracks = [];
        for ($i = 0; $i < $numberTracks; ++$i) {
            $tracks[] = $this->createTrackStub('title' . $i, 'track-number' . $i);
        }
        $shipmentStub->method('getTracksCollection')
            ->willReturn($tracks);

        return $shipmentStub;
    }

    /**
     * Create order stub
     *
     * @param string $orderStatus
     *
     * @return MageOrderInterface&MockObject
     */
    private function createMageOrderStub(string $orderStatus)
    {
        $orderStub = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'getId', 'getStatus', 'getStoreName' ])
            ->getMock();
        $orderStub->method('getId')
            ->willReturn(1);
        $orderStub->method('getStatus')
            ->willReturn($orderStatus);
        $orderStub->method('getStoreName')
            ->willReturn('shop.com');

        return $orderStub;
    }

    /**
     * Create store stub
     *
     * @return StoreInterface&MockObject
     */
    private function createStoreStub()
    {
        $storeStub = $this->getMockBuilder(StoreInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getFrontendName',
                    'getId',
                    'setId',
                    'getCode',
                    'setCode',
                    'getName',
                    'setName',
                    'getWebsiteId',
                    'setWebsiteId',
                    'getStoreGroupId',
                    'setStoreGroupId',
                    'setIsActive',
                    'getIsActive',
                    'getExtensionAttributes',
                    'setExtensionAttributes',
                ]
            )
            ->getMock();
        $storeStub->method('getId')
            ->willReturn(1);
        $storeStub->method('getFrontendName')
            ->willReturn('shop');
        $storeStub->method('getName')
            ->willReturn('shop');

        return $storeStub;
    }

    /**
     * Create track stub
     *
     * @param string $title
     * @param string $trackNumber
     *
     * @return Track&MockObject
     */
    private function createTrackStub(string $title, string $trackNumber)
    {
        $trackStub = $this->getMockBuilder(Track::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'getTitle', 'getTrackNumber' ])
            ->getMock();
        $trackStub->method('getTitle')
            ->willReturn($title);
        $trackStub->method('getTrackNumber')
            ->willReturn($trackNumber);

        return $trackStub;
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return Context&MockObject
     */
    private function createContextStub(LoggerInterface $logger)
    {
        $contextStub = $this->createMock(Context::class);

        $this->addGetterTo($contextStub, 'appState', State::class);
        $this->addGetterTo($contextStub, 'eventDispatcher', ManagerInterface::class);
        $this->addGetterTo($contextStub, 'cacheManager', CacheInterface::class);
        $this->addGetterTo($contextStub, 'actionValidator', RemoveAction::class);

        $contextStub->method('getLogger')
            ->willReturn($logger);

        return $contextStub;
    }

    /**
     * @param ThemeInterface $designThemeStub
     *
     * @return DesignInterface&MockObject
     */
    private function createDesignStub(ThemeInterface $designThemeStub)
    {
        $designStub = $this->createMock(DesignInterface::class);
        $designStub->method('getArea')
            ->willReturn('');
        $designStub->method('getDesignTheme')
            ->willReturn($designThemeStub);
        $designStub->method('getLocale')
            ->willReturn('');

        return $designStub;
    }

    /**
     * @param string $templateText
     *
     * @return Filesystem&MockObject
     */
    private function createFilesystemStub(string $templateText)
    {
        $rootDirectoryStub = $this->createMock(ReadInterface::class);
        $rootDirectoryStub->method('readFile')
            ->with('relativePath')
            ->willReturn($templateText);
        $rootDirectoryStub->method('getRelativePath')
            ->with('')
            ->willReturn('relativePath');

        $filesystemStub = $this->createMock(Filesystem::class);
        $filesystemStub->method('getDirectoryRead')
            ->with('base')
            ->willReturn($rootDirectoryStub);

        return $filesystemStub;
    }

    /**
     * @param ThemeInterface $designThemeStub
     *
     * @return Config&MockObject
     */
    private function createEmailConfigStub(ThemeInterface $designThemeStub)
    {
        $emailConfigStub = $this->createMock(Config::class);
        $emailConfigStub->method('getTemplateFilename')
            ->with('email_id_1', [ 'area' => '', 'theme' => '', 'themeModel' => $designThemeStub, 'locale' => '' ])
            ->willReturn('');
        $emailConfigStub->method('getTemplateType')
            ->with('email_id_1')
            ->willReturn('html');

        return $emailConfigStub;
    }

    /**
     * @param EinsUndEinsOrderInterface $orderStub
     * @param string                    $orderStatus
     * @param bool                      $orderStatusWrong
     *
     * @return OrderFactory&MockObject
     */
    private function createOrderFactoryStub(
        EinsUndEinsOrderInterface $orderStub,
        string $orderStatus,
        bool $orderStatusWrong = false
    ) {
        $orderFactoryStub = $this
            ->getMockBuilder('EinsUndEins\SchemaOrgMailBody\Model\OrderFactory')
            ->disableOriginalConstructor()
            ->setMethods([ 'create' ])
            ->getMock();
        $method           = $orderFactoryStub
            ->method('create')
            ->with(
                [
                    'orderNumber' => 1,
                    'orderStatus' => $orderStatus,
                    'shopName'    => 'shop.com',
                ]
            );
        if ($orderStatusWrong) {
            $method->willThrowException(new InvalidArgumentException('Status is not one of the possible status.'));
        } else {
            $method->willReturn($orderStub);
        }

        return $orderFactoryStub;
    }

    /**
     * @param EinsUndEinsOrderInterface $orderStub
     *
     * @return OrderRendererFactory&MockObject
     */
    private function createOrderRendererFactoryStub(EinsUndEinsOrderInterface $orderStub)
    {
        $orderRendererStub = $this->createMock(OrderRenderer::class);
        $orderRendererStub
            ->method('render')
            ->willReturn('<div>eins-und-eins-library-order-result</div>');
        $orderRendererFactoryStub = $this
            ->getMockBuilder('EinsUndEins\SchemaOrgMailBody\Renderer\OrderRendererFactory')
            ->disableOriginalConstructor()
            ->setMethods([ 'create' ])
            ->getMock();
        $orderRendererFactoryStub
            ->method('create')
            ->with([ 'order' => $orderStub ])
            ->willReturn($orderRendererStub);

        return $orderRendererFactoryStub;
    }

    /**
     * @return Emulation&MockObject
     */
    private function createAppEmulationStub()
    {
        $appEmulationStub = $this->createMock(Emulation::class);
        $appEmulationStub->method('startEnvironmentEmulation');
        $appEmulationStub->method('stopEnvironmentEmulation');

        return $appEmulationStub;
    }

    /**
     * @return ThemeInterface&MockObject
     */
    private function createDesignThemeStub()
    {
        $designThemeStub = $this->createMock(ThemeInterface::class);
        $designThemeStub->method('getCode')
            ->willReturn('');

        return $designThemeStub;
    }

    /**
     * @param StoreInterface $storeStub
     *
     * @return StoreManagerInterface&MockObject
     */
    private function createStoreManagerStub(StoreInterface $storeStub)
    {
        $storeManagerStub = $this->createMock(StoreManager::class);
        $storeManagerStub->method('getStore')
            ->willReturn($storeStub);

        return $storeManagerStub;
    }

    /**
     * @param string $extra
     *
     * @return ParcelDeliveryRenderer&MockObject
     */
    private function createParcelDeliveryRendererStub(string $extra)
    {
        $parcelDeliveryRendererStub = $this->createMock(ParcelDeliveryRenderer::class);
        $parcelDeliveryRendererStub
            ->method('render')
            ->willReturn('<div>eins-und-eins-library-parcel-delivery-' . $extra . '-result</div>');

        return $parcelDeliveryRendererStub;
    }

    /**
     * @param array $parcelDeliveryStubs
     * @param bool  $orderStatusWrong
     *
     * @return ParcelDeliveryFactory&MockObject
     */
    private function createParcelDeliveryFactoryStub(
        array $parcelDeliveryStubs,
        bool $orderStatusWrong = false
    ) {
        $parcelDeliveryFactoryStub = $this
            ->getMockBuilder('EinsUndEins\SchemaOrgMailBody\Model\ParcelDeliveryFactory')
            ->disableOriginalConstructor()
            ->setMethods([ 'create' ])
            ->getMock();
        $valueMap                  = [];
        foreach ($parcelDeliveryStubs as $id => $parcelDeliveryStub) {
            $valueMap[] = [
                [
                    'deliveryName'   => 'title' . $id,
                    'trackingNumber' => 'track-number' . $id,
                    'orderNumber'    => 1,
                    'orderStatus'    => 'OrderDelivered',
                    'shopName'       => 'shop',
                ],
                $parcelDeliveryStub['parcelDelivery'],
            ];
        }

        $method = $parcelDeliveryFactoryStub
            ->method('create');
        if ($orderStatusWrong) {
            $method->willThrowException(new InvalidArgumentException('Status is not one of the possible status.'));
        } else {
            $method->will($this->returnValueMap($valueMap));
        }

        return $parcelDeliveryFactoryStub;
    }

    /**
     * @param array $parcelDeliveryStubs
     *
     * @return ParcelDeliveryRendererFactory&MockObject
     */
    private function createParcelDeliveryRendererFactoryStub(array $parcelDeliveryStubs)
    {
        $parcelDeliveryRendererFactorySub = $this
            ->getMockBuilder('EinsUndEins\SchemaOrgMailBody\Renderer\ParcelDeliveryRendererFactory')
            ->disableOriginalConstructor()
            ->setMethods([ 'create' ])
            ->getMock();
        $results                         = [];
        foreach ($parcelDeliveryStubs as $id => $parcelDeliveryStub) {
            $results[] = $parcelDeliveryStub['parcelDeliveryRenderer'];
        }
        $parcelDeliveryRendererFactorySub
            ->method('create')
            ->will($this->onConsecutiveCalls(...$results));

        return $parcelDeliveryRendererFactorySub;
    }

    /**
     * @param int $number
     *
     * @return array
     */
    private function createParcelDeliveryStubArray(int $number): array
    {
        $parcelDeliveryStubs = [];
        for ($i = 0; $i < $number; ++$i) {
            $parcelDeliveryStubs[$i] = [
                'parcelDelivery'         => $this->createMock(ParcelDelivery::class),
                'parcelDeliveryRenderer' => $this->createParcelDeliveryRendererStub((string)$i),
            ];
        }

        return $parcelDeliveryStubs;
    }

    /**
     * Get an array of the needed variables for the filtering
     *
     * @return array
     */
    private function getNeededVars(): array
    {
        return [
            'logo_url'        => 'shop.com/logo.png',
            'logo_alt'        => 'shop.com',
            'logo_width'      => 100,
            'logo_height'     => 100,
            'store'           => [],
            'store_phone'     => '071122334455',
            'store_hours'     => 12,
            'store_email'     => 'shop@shop.com',
            'template_styles' => 'some css',
        ];
    }

    /**
     * @return string
     */
    private function getOrigTemplateText(): string
    {
        return '<html lang="en">' .
            '<head><title>foo</title></head>' .
            '<body><h1>bar</h1><span>text<!--</body>--></span></body>' .
            '</html>';
    }

    /**
     * @param string $className
     *
     * @return MockObject
     */
    private function createFactoryStub(string $className): MockObject
    {
        return $this->getMockBuilder($className)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return ResourceTemplate&MockObject
     */
    private function createResourceTemplateStub()
    {
        $resourceTemplateStub = $this->createMock(ResourceTemplate::class);
        $resourceTemplateStub->method('getIdFieldName')
            ->willReturn('id');

        return $resourceTemplateStub;
    }

    /**
     * @param TestLogger $logger
     */
    private function assertLoggerHasNoRecords(TestLogger $logger): void
    {
        $this->assertFalse($logger->hasErrorRecords());
        $this->assertFalse($logger->hasAlertRecords());
        $this->assertFalse($logger->hasDebugRecords());
        $this->assertFalse($logger->hasCriticalRecords());
        $this->assertFalse($logger->hasEmergencyRecords());
        $this->assertFalse($logger->hasInfoRecords());
        $this->assertFalse($logger->hasNoticeRecords());
        $this->assertFalse($logger->hasWarningRecords());
    }
}
