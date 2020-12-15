<?php

namespace EinsUndEins\TransactionMailExtender\Test\Unit\Adminhtml\Form\Field;

use EinsUndEins\TransactionMailExtender\Block\Adminhtml\Form\Field\MageStatusColumn;
use EinsUndEins\TransactionMailExtender\Block\Adminhtml\Form\Field\OrderStatusMatrix;
use EinsUndEins\TransactionMailExtender\Block\Adminhtml\Form\Field\SchemaOrgStatusSelect;
use EinsUndEins\TransactionMailExtender\Test\Unit\TestCase;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Session;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\Cache\StateInterface as CacheStateInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Cache\LockGuardedCacheLoader;
use Magento\Framework\Code\NameBuilder;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filter\FilterManager;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\Math\Random;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Session\SidResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Translate\Inline\StateInterface as InlineStateInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\ConfigInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\File\Resolver;
use Magento\Framework\View\Element\Template\File\Validator;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Framework\View\Layout;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\TemplateEnginePool;
use Magento\Sales\Model\ResourceModel\Order\Status\Collection;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class OrderStatusMatrixTest extends TestCase
{
    public function testGetArrayRows(): void
    {
        $contextStub                 = $this->createContextStub();
        $statusCollectionFactoryStub = $this->createStatusCollectionFactoryStub();
        $dataObjectFactoryStub       = $this->createDataObjectFactoryStub();
        $secureHtmlRendererStub      = $this->createMock(SecureHtmlRenderer::class);
        $jsonHelperStub              = $this->createMock(JsonHelper::class);
        $directoryHelperStub         = $this->createMock(DirectoryHelper::class);
        $this->mockObjectManager(
            [
                [ SecureHtmlRenderer::class, $secureHtmlRendererStub ],
                [ JsonHelper::class, $jsonHelperStub ],
                [ DirectoryHelper::class, $directoryHelperStub ],
            ]
        );
        $matrix = new OrderStatusMatrix(
            $contextStub,
            $statusCollectionFactoryStub,
            $dataObjectFactoryStub
        );

        $layoutStub  = $this->createLayoutStub();
        $elementStub = $this->createElementStub();
        $matrix->setLayout($layoutStub);
        $matrix->setElement($elementStub);
        $matrix->setName('name');
        $matrix->setId('id');

        $expected = [
            [
                'mage_status'        => 'value1',
                'schema_org_status'  => 'schema-value1',
                'column_values'      => [
                    'there-id1_schema_org_status' => 'schema-value1',
                    'there-id1_mage_status'       => 'value1',
                ],
                'option_extra_attrs' => [
                    'option_hash' => 'selected="selected"',
                ],
                '_id'                => 'there-id1',
            ],
            [
                'mage_status'        => 'value2',
                'schema_org_status'  => '',
                'column_values'      => [
                    'value2',
                    '',
                ],
                'option_extra_attrs' => [
                    'option_hash' => 'selected="selected"',
                ],
            ],
            [
                'mage_status'        => 'value3',
                'schema_org_status'  => 'schema-value3',
                'column_values'      => [
                    'there-id3_schema_org_status' => 'schema-value3',
                    'there-id3_mage_status'       => 'value3',
                ],
                'option_extra_attrs' => [
                    'option_hash' => 'selected="selected"',
                ],
                '_id'                => 'there-id3',
            ],
        ];

        $rows = array_values($matrix->getArrayRows());
        $i    = max(count($rows), count($expected)) - 1;
        for (; $i >= 0; --$i) {
            if (!array_key_exists($i, $expected)) {
                $expected[$i] = [];
            }
            if (!array_key_exists($i, $rows)) {
                $rows[$i] = new DataObject();
            }
            $this->assertRow($rows[$i], $expected[$i]);
        }
    }

    /**
     * Assert is the row is correct
     *
     * @param DataObject $row
     * @param array      $expected
     */
    private function assertRow(DataObject $row, array $expected): void
    {
        $data = $row->getData();

        $this->hasKeyAndEquals('mage_status', $expected, $data);
        $this->hasKeyAndEquals('schema_org_status', $expected, $data);

        $this->assertArrayHasKey('column_values', $data);
        foreach ($expected['column_values'] as $key => $columnValue) {
            if (is_string($key)) {
                $this->assertArrayHasKey($key, $data['column_values']);
                $this->assertEquals($columnValue, $data['column_values'][$key]);
            } else {
                $this->assertNotFalse(array_search($columnValue, $data));
            }
        }

        $this->assertArrayHasKey('option_extra_attrs', $data);
        foreach ($expected['option_extra_attrs'] as $key => $optionAttrs) {
            $this->assertArrayHasKey($key, $data['option_extra_attrs']);
            $this->assertEquals($optionAttrs, $data['option_extra_attrs'][$key]);
        }

        $this->hasKeyAndCanBeAssert('_id', $expected, $data);
    }

    /**
     * Create a context stub
     *
     * @return Context&MockObject
     */
    private function createContextStub()
    {
        $contextStub = $this->createMock(Context::class);

        $this->addGetterTo($contextStub, 'localeDate', TimezoneInterface::class);
        $this->addGetterTo($contextStub, 'authorization', AuthorizationInterface::class);
        $this->addGetterTo($contextStub, 'mathRandom', Random::class);
        $this->addGetterTo($contextStub, 'backendSession', Session::class);
        $this->addGetterTo($contextStub, 'formKey', FormKey::class);
        $this->addGetterTo($contextStub, 'nameBuilder', NameBuilder::class);
        $this->addGetterTo($contextStub, 'validator', Validator::class);
        $this->addGetterTo($contextStub, 'resolver', Resolver::class);
        $this->addGetterTo($contextStub, 'filesystem', Filesystem::class);
        $this->addGetterTo($contextStub, 'enginePool', TemplateEnginePool::class);
        $this->addGetterTo($contextStub, 'storeManager', StoreManagerInterface::class);
        $this->addGetterTo($contextStub, 'appState', State::class);
        $this->addGetterTo($contextStub, 'pageConfig', Config::class);
        $this->addGetterTo($contextStub, 'request', RequestInterface::class);
        $this->addGetterTo($contextStub, 'layout', LayoutInterface::class);
        $this->addGetterTo($contextStub, 'eventManager', ManagerInterface::class);
        $this->addGetterTo($contextStub, 'urlBuilder', UrlInterface::class);
        $this->addGetterTo($contextStub, 'cache', CacheInterface::class);
        $this->addGetterTo($contextStub, 'designPackage', DesignInterface::class);
        $this->addGetterTo($contextStub, 'session', SessionManagerInterface::class);
        $this->addGetterTo($contextStub, 'sidResolver', SidResolverInterface::class);
        $this->addGetterTo($contextStub, 'scopeConfig', ScopeConfigInterface::class);
        $this->addGetterTo($contextStub, 'assetRepository', Repository::class);
        $this->addGetterTo($contextStub, 'viewConfig', ConfigInterface::class);
        $this->addGetterTo($contextStub, 'cacheState', CacheStateInterface::class);
        $this->addGetterTo($contextStub, 'logger', LoggerInterface::class);
        $this->addGetterTo($contextStub, 'escaper', Escaper::class);
        $this->addGetterTo($contextStub, 'filterManager', FilterManager::class);
        $this->addGetterTo($contextStub, 'inlineTranslation', InlineStateInterface::class);
        $this->addGetterTo($contextStub, 'lockGuardedCacheLoader', LockGuardedCacheLoader::class);

        return $contextStub;
    }

    /**
     * Create an element stub
     *
     * @return AbstractElement&MockObject
     */
    private function createElementStub()
    {
        $value       = [
            'removed-id1' => [
                OrderStatusMatrix::SCHEMA_ORG_STATUS_KEY => 'removed-value1-1',
                OrderStatusMatrix::MAGE_STATUS_KEY       => 'removed-value1-2',
            ],
            'there-id1'   => [
                OrderStatusMatrix::SCHEMA_ORG_STATUS_KEY => 'schema-value1',
                OrderStatusMatrix::MAGE_STATUS_KEY       => 'value1',
            ],
            'removed-id2' => [
                OrderStatusMatrix::SCHEMA_ORG_STATUS_KEY => 'removed-value2-1',
                OrderStatusMatrix::MAGE_STATUS_KEY       => 'removed-value2-2',
            ],
            'there-id3'   => [
                OrderStatusMatrix::SCHEMA_ORG_STATUS_KEY => 'schema-value3',
                OrderStatusMatrix::MAGE_STATUS_KEY       => 'value3',
            ],
        ];
        $elementStub = $this->getMockBuilder(AbstractElement::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'getValue' ])
            ->getMock();
        $elementStub->method('getValue')
            ->willReturn($value);

        return $elementStub;
    }

    /**
     * Create status collection factory stub, which return a status collection stub
     *
     * @return CollectionFactory&MockObject
     */
    private function createStatusCollectionFactoryStub()
    {
        $statusCollectionStub = $this->createMock(Collection::class);
        $statusCollectionStub->method('toOptionArray')
            ->willReturn(
                [
                    [
                        'value' => 'value1',
                        'label' => 'label1',
                    ],
                    [
                        'value' => 'value2',
                        'label' => 'label2',
                    ],
                    [
                        'value' => 'value3',
                        'label' => 'label3',
                    ],
                ]
            );

        $statusCollectionFactoryStub = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'create' ])
            ->getMock();
        $statusCollectionFactoryStub->method('create')
            ->willReturn($statusCollectionStub);

        return $statusCollectionFactoryStub;
    }

    /**
     * Create data object factory stub
     *
     * @return DataObjectFactory&MockObject
     */
    private function createDataObjectFactoryStub()
    {
        $dataObjectFactoryStub = $this->getMockBuilder(DataObjectFactory::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'create' ])
            ->getMock();
        $dataObjectFactoryStub->method('create')
            ->willReturn(new DataObject());

        return $dataObjectFactoryStub;
    }

    /**
     * Assert if array has the specific key and if the value in the expected array to the key is equal to the value of the
     * actual array to the key.
     *
     * @param string $key
     * @param array  $expected
     * @param array  $actual
     */
    private function hasKeyAndEquals(string $key, array $expected, array $actual): void
    {
        $this->assertArrayHasKey($key, $actual);
        $this->assertEquals($expected[$key], $actual[$key]);
    }

    /**
     * Assert if the actual has the key. If the expected has also the key assert if the values are equal.
     *
     * @param string $key
     * @param array  $expected
     * @param array  $actual
     */
    private function hasKeyAndCanBeAssert(string $key, array $expected, array $actual): void
    {
        $this->assertArrayHasKey($key, $actual);
        if (array_key_exists($key, $expected)) {
            $this->assertEquals($expected[$key], $actual[$key]);
        }
    }

    /**
     * @return LayoutInterface&MockObject
     */
    private function createLayoutStub()
    {
        $mageStatusRendererStub = $this->createBlockStub();
        $schemaOrgStatusRendererStub = $this->createBlockStub();

        $layoutStub = $this->createMock(Layout::class);
        $layoutStub->method('createBlock')
            ->will(
                $this->returnValueMap(
                    [
                        [
                            MageStatusColumn::class,
                            '',
                            [ 'data' => [ 'is_render_to_js_template' => true ] ],
                            $mageStatusRendererStub,
                        ],
                        [
                            SchemaOrgStatusSelect::class,
                            '',
                            [ 'data' => [ 'is_render_to_js_template' => true ] ],
                            $schemaOrgStatusRendererStub,
                        ],
                    ]
                )
            );

        return $layoutStub;
    }

    /**
     * @return BlockInterface&MockObject
     */
    private function createBlockStub()
    {
        $blockStub = $this->getMockBuilder(Template::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'calcOptionHash', 'toHtml' ])
            ->getMock();
        $blockStub->method('calcOptionHash')
            ->willReturn('hash');
        $blockStub->method('toHtml')
            ->willReturn('<div></div>');

        return $blockStub;
    }
}
