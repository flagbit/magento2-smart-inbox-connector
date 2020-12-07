<?php

namespace EinsUndEins\TransactionMailExtender\Test\Unit\Model;

use EinsUndEins\SchemaOrgMailBody\Model\OrderFactory;
use EinsUndEins\SchemaOrgMailBody\Model\OrderInterface as EinsUndEinsOrderInterface;
use EinsUndEins\SchemaOrgMailBody\Model\ParcelDeliveryFactory;
use EinsUndEins\SchemaOrgMailBody\Model\ParcelDeliveryInterface;
use EinsUndEins\SchemaOrgMailBody\Renderer\OrderRenderer;
use EinsUndEins\SchemaOrgMailBody\Renderer\OrderRendererFactory;
use EinsUndEins\SchemaOrgMailBody\Renderer\ParcelDeliveryRenderer;
use EinsUndEins\SchemaOrgMailBody\Renderer\ParcelDeliveryRendererFactory;
use EinsUndEins\TransactionMailExtender\Model\Template;
use EinsUndEins\TransactionMailExtender\Test\Unit\TestCase;
use InvalidArgumentException;
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
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class TemplateTest extends TestCase
{
    public function testProcessTemplateModuleNotEnabled(): void
    {
        $scopeConfigStub = $this->createScopeConfigStub(false);
        $expected        = $this->getOrigTemplateText();

        $template = $this->createTemplate($scopeConfigStub, $expected);
        $template->setTemplateId('email_id_1');
        $template->setTemplateFilter($this->createTemplateFilterStub());
        $template->setVars($this->getNeededVars());

        $this->assertEquals($expected, $template->processTemplate());
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

        $template = $this->createTemplate($scopeConfigStub, $templateText);
        $template->setTemplateId('email_id_1');
        $template->setTemplateFilter($this->createTemplateFilterStub());
        $vars = $this->getNeededVars();
        array_merge(
            $vars,
            [
                $this->createMageOrderStub('cancelled'),
                $this->createShipmentStub('delivered', 3),
            ]
        );
        $template->setVars($vars);

        $this->assertEquals($expected, $template->processTemplate());
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

        $template = $this->createTemplate(
            $scopeConfigStub,
            $templateText,
            'Couldn\'t get order from the email variables'
        );
        $template->setTemplateId('email_id_1');
        $template->setTemplateFilter($this->createTemplateFilterStub());
        $vars = $this->getNeededVars();
        array_merge(
            $vars,
            [
                $this->createShipmentStub('delivered', 3),
            ]
        );
        $template->setVars($vars);

        $this->assertEquals($expected, $template->processTemplate());
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

        $template = $this->createTemplate(
            $scopeConfigStub,
            $templateText,
            'Couldn\'t get shipment from the email variables'
        );
        $template->setTemplateId('email_id_1');
        $template->setTemplateFilter($this->createTemplateFilterStub());
        $vars = $this->getNeededVars();
        array_merge(
            $vars,
            [
                $this->createMageOrderStub('cancelled'),
            ]
        );
        $template->setVars($vars);

        $this->assertEquals($expected, $template->processTemplate());
    }

    public function testProcessTemplateWithWrongOrderStatus(): void
    {
        $expected = $this->getOrigTemplateText();

        $scopeConfigStub = $this->createScopeConfigStub(true);

        $template = $this->createTemplate(
            $scopeConfigStub,
            $expected,
            'Status is not one of the possible status.',
            4,
            true
        );
        $template->setTemplateId('email_id_1');
        $template->setTemplateFilter($this->createTemplateFilterStub());
        $vars = $this->getNeededVars();
        array_merge(
            $vars,
            [
                $this->createMageOrderStub('cancelled'),
                $this->createShipmentStub('delivered', 3),
            ]
        );
        $template->setVars($vars);

        $this->assertEquals($expected, $template->processTemplate());
    }

    /**
     * @param ScopeConfigInterface $scopeConfigStub
     * @param string               $templateText
     * @param string|null          $expectedException
     * @param int|null             $numberOfExpectedExceptions
     * @param bool                 $orderStatusWrong
     *
     * @return Template
     */
    private function createTemplate(
        ScopeConfigInterface $scopeConfigStub,
        string $templateText,
        string $expectedException = null,
        int $numberOfExpectedExceptions = null,
        bool $orderStatusWrong = false
    ): Template {
        $contextStub                      = $this->createContextStub($expectedException, $numberOfExpectedExceptions);
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
        $orderFactoryStub                 = $this->createOrderFactoryStub($orderStub, $orderStatusWrong);
        $orderRendererFactoryStub         = $this->createOrderRendererFactoryStub($orderStub);
        $parcelDeliveryStubs              = $this->createParcelDeliveryStubArray(3);
        $parcelDeliveryFactoryStub        = $this->createParcelDeliveryFactoryStub($parcelDeliveryStubs, $orderStatusWrong);
        $parcelDeliveryRendererFactorySub = $this->createParcelDeliveryRendererFactoryStub($parcelDeliveryStubs);
        $serializerStub                   = $this->createMock(Json::class);
        $databaseStub                     = $this->createMock(Database::class);
        $this->mockObjectManager([ [ Database::class, $databaseStub ] ]);

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
     * @return ScopeConfigInterface
     */
    private function createScopeConfigStub(bool $moduleEnabled): ScopeConfigInterface
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
                            '{"array": [' .
                            '{"mage_status": "cancelled",' .
                            '"schema_org_status": "orderCancelled"},' .
                            '{"mage_status": "delivered",' .
                            '"schema_org_status": "orderDelivered"},' .
                            '{"mage_status": "not-existent",' .
                            '"schema_org_status": "notExistent"}]}',
                        ],
                    ]
                )
            );

        return $scopeConfigStub;
    }

    /**
     * Create template filter stub
     *
     * @return Filter
     */
    private function createTemplateFilterStub(): Filter
    {
        $templateFilterStub = $this->createMock(Filter::class);

        $templateFilterStub->method('setUseSessionInUrl')
            ->with([ false ])
            ->willReturnSelf();

        $templateFilterStub->method('setPlainTemplateMode')
            ->with([ false ])
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
     * @return ShipmentInterface
     */
    private function createShipmentStub(string $orderStatus, int $numberTracks): ShipmentInterface
    {
        $shipmentStub = $this->createMock(ShipmentInterface::class);
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
     * @return MageOrderInterface
     */
    private function createMageOrderStub(string $orderStatus): MageOrderInterface
    {
        $orderStub = $this->createMock(MageOrderInterface::class);
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
     * @return StoreInterface
     */
    private function createStoreStub(): StoreInterface
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
     * @return Track
     */
    private function createTrackStub(string $title, string $trackNumber): Track
    {
        $trackStub = $this->createMock(Track::class);
        $trackStub->method('getTitle')
            ->willReturn($title);
        $trackStub->method('getTrackNumber')
            ->willReturn($trackNumber);

        return $trackStub;
    }

    /**
     * Add getter with new stub to parent-stub
     *
     * @param MockObject $parentStub
     * @param string     $name
     * @param            $class
     *
     * @return MockObject
     */
    private function addGetterTo(MockObject $parentStub, string $name, $class): MockObject
    {
        $stub = $this->createMock($class);
        $parentStub->method('get' . ucfirst($name))
            ->willReturn($stub);

        return $parentStub;
    }

    /**
     * @param string|null $expectsException
     * @param int|null    $numberExpectedExceptions
     *
     * @return Context|MockObject
     */
    private function createContextStub(?string $expectsException, ?int $numberExpectedExceptions)
    {
        $contextStub = $this->createMock(Context::class);

        $this->addGetterTo($contextStub, 'appState', State::class);
        $this->addGetterTo($contextStub, 'eventDispatcher', ManagerInterface::class);
        $this->addGetterTo($contextStub, 'cacheManager', CacheInterface::class);
        $this->addGetterTo($contextStub, 'actionValidator', RemoveAction::class);

        $loggerStub = $this->createLoggerStub($expectsException, $numberExpectedExceptions);
        $contextStub->method('getLogger')
            ->willReturn($loggerStub);

        return $contextStub;
    }

    /**
     * @param ThemeInterface $designThemeStub
     *
     * @return DesignInterface|MockObject
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
     * @return Filesystem|MockObject
     */
    private function createFilesystemStub(string $templateText)
    {
        $rootDirectoryStub = $this->createMock(ReadInterface::class);
        $rootDirectoryStub->method('readFile')
            ->with([ 'relativePath' ])
            ->willReturn($templateText);
        $rootDirectoryStub->method('getRelativePath')
            ->with([ '' ])
            ->willReturn('relativePath');

        $filesystemStub = $this->createMock(Filesystem::class);
        $filesystemStub->method('getDirectoryRead')
            ->with([ 'base' ])
            ->willReturn($rootDirectoryStub);

        return $filesystemStub;
    }

    /**
     * @param ThemeInterface $designThemeStub
     *
     * @return Config|MockObject
     */
    private function createEmailConfigStub(ThemeInterface $designThemeStub)
    {
        $emailConfigStub = $this->createMock(Config::class);
        $emailConfigStub->method('getTemplateFilename')
            ->with([ 'email_id_1', [ 'area' => '', 'theme' => '', 'themeModel' => $designThemeStub, 'locale' => '' ] ])
            ->willReturn('');
        $emailConfigStub->method('getTemplateType')
            ->with([ 'email_id_1' ])
            ->willReturn('html');

        return $emailConfigStub;
    }

    /**
     * @param EinsUndEinsOrderInterface $orderStub
     * @param bool                      $orderStatusWrong
     *
     * @return OrderFactory|MockObject
     */
    private function createOrderFactoryStub(EinsUndEinsOrderInterface $orderStub, bool $orderStatusWrong = false): OrderFactory
    {
        $orderFactoryStub = $this
            ->getMockBuilder('EinsUndEins\SchemaOrgMailBody\Model\OrderFactory')
            ->disableOriginalConstructor()
            ->setMethods([ 'create' ])
            ->getMock();
        $method           = $orderFactoryStub
            ->method('create')
            ->with([ [ 'orderNumber' => 1, 'orderStatus' => '', 'shopName' => 'shop' ] ])
            ->willReturn($orderStub);
        if ($orderStatusWrong) {
            $method->willThrowException(new InvalidArgumentException('Status is not one of the possible status.'));
        }

        return $orderFactoryStub;
    }

    /**
     * @param EinsUndEinsOrderInterface $orderStub
     *
     * @return OrderRendererFactory|MockObject
     */
    private function createOrderRendererFactoryStub(EinsUndEinsOrderInterface $orderStub): OrderRendererFactory
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
            ->with([ [ 'order' => $orderStub ] ])
            ->willReturn($orderRendererStub);

        return $orderRendererFactoryStub;
    }

    /**
     * @return Emulation|MockObject
     */
    private function createAppEmulationStub()
    {
        $appEmulationStub = $this->createMock(Emulation::class);
        $appEmulationStub->method('startEnvironmentEmulation');
        $appEmulationStub->method('stopEnvironmentEmulation');

        return $appEmulationStub;
    }

    /**
     * @return ThemeInterface|MockObject
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
     * @return StoreManagerInterface|MockObject
     */
    private function createStoreManagerStub(StoreInterface $storeStub)
    {
        $storeManagerStub = $this->createMock(StoreManagerInterface::class);
        $storeManagerStub->method('getStore')
            ->willReturn($storeStub);

        return $storeManagerStub;
    }

    /**
     * @param string $extra
     *
     * @return ParcelDeliveryRenderer|MockObject
     */
    private function createParcelDeliveryRendererStub(string $extra)
    {
        $parcelDeliveryRendererStub1 = $this->createMock(ParcelDeliveryRenderer::class);
        $parcelDeliveryRendererStub1
            ->method('render')
            ->willReturn('<div>eins-und-eins-library-parcel-delivery-' . $extra . '-result</div>');

        return $parcelDeliveryRendererStub1;
    }

    /**
     * @param array $parcelDeliveryStubs
     * @param bool  $orderStatusWrong
     *
     * @return ParcelDeliveryFactory|MockObject
     */
    private function createParcelDeliveryFactoryStub(
        array $parcelDeliveryStubs,
        bool $orderStatusWrong = false
    ): ParcelDeliveryFactory {
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
            ->method('create')
            ->will($this->returnValueMap($valueMap));
        if ($orderStatusWrong) {
            $method->willThrowException(new InvalidArgumentException('Status is not one of the possible status.'));
        }

        return $parcelDeliveryFactoryStub;
    }

    /**
     * @param array $parcelDeliveryStubs
     *
     * @return ParcelDeliveryRendererFactory|MockObject
     */
    private function createParcelDeliveryRendererFactoryStub(array $parcelDeliveryStubs): ParcelDeliveryRendererFactory
    {
        $parcelDeliveryRendererFactorySub = $this
            ->getMockBuilder('EinsUndEins\SchemaOrgMailBody\Renderer\ParcelDeliveryRendererFactory')
            ->disableOriginalConstructor()
            ->setMethods([ 'create' ])
            ->getMock();
        $valueMap                         = [];
        foreach ($parcelDeliveryStubs as $id => $parcelDeliveryStub) {
            $valueMap[] = [
                [ 'parcelDelivery' => $parcelDeliveryStub['parcelDelivery'] ],
                $parcelDeliveryStub['parcelDeliveryRenderer'],
            ];
        }
        $parcelDeliveryRendererFactorySub
            ->method('create')
            ->will($this->returnValueMap($valueMap));

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
                'parcelDelivery'         => $this->createMock(ParcelDeliveryInterface::class),
                'parcelDeliveryRenderer' => $this->createParcelDeliveryRendererStub($i),
            ];
        }

        return $parcelDeliveryStubs;
    }

    /**
     * @param string|null $expectsException
     * @param int|null    $numberExpectedExceptions
     *
     * @return MockObject|LoggerInterface
     */
    private function createLoggerStub(?string $expectsException, ?int $numberExpectedExceptions)
    {
        $loggerStub = $this->createMock(LoggerInterface::class);
        if (!empty($expectsException)) {
            $number = $numberExpectedExceptions ? new InvokedCount($numberExpectedExceptions) : $this->once();
            $loggerStub->expects($number)
                ->method('error')
                ->with([ $expectsException ]);
        }

        return $loggerStub;
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
}
