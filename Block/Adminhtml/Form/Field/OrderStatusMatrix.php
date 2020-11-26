<?php

namespace EinsUndEins\TransactionMailExtender\Block\Adminhtml\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;

class OrderStatusMatrix extends AbstractFieldArray
{
    public const MAGE_STATUS_KEY = 'mage_status';
    public const SCHEMA_ORG_STATUS_KEY = 'schema_org_status';

    /** @var SchemaOrgStatusSelect $schemaOrgStatusRenderer */
    private $schemaOrgStatusRenderer;
    /** @var CollectionFactory $statusCollectionFactory */
    private $statusCollectionFactory;
    /** @var DataObjectFactory $dataObjectFactory */
    private $dataObjectFactory;
    /** @var MageStatusColumn $mageStatusRenderer */
    private $mageStatusRenderer;

    public function __construct(
        Context $context,
        CollectionFactory $statusCollectionFactory,
        DataObjectFactory $dataObjectFactory,
        array $data = []
    ) {
        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        parent::__construct($context, $data);
        $this->_template = 'EinsUndEins_TransactionMailExtender::system/config/form/field/array.phtml';
    }

    /**
     * Obtain existing data from form element
     *
     * Each row will be instance of \Magento\Framework\DataObject
     *
     * @return array
     */
    public function getArrayRows()
    {
        $oldArrayRows = parent::getArrayRows();


        /** @var DataObject[] $temporarilyRows */
        $temporarilyRows = [];
        foreach ($oldArrayRows as $key => $oldRow) {
            $oldRow->setData('key', $key);
            $temporarilyRows[$oldRow->getData(self::MAGE_STATUS_KEY)] = $oldRow;
        }

        $resultRows = [];
        foreach ($this->getMageStatusOptions() as $mageOrderStatus) {
            if (array_key_exists($mageOrderStatus['value'], $temporarilyRows)) {
                $dataObject = $temporarilyRows[$mageOrderStatus['value']];
            } else {
                $dataObject = $this->createNewDataObject($mageOrderStatus['value']);
            }
            $this->_prepareArrayRow($dataObject);
            $resultRows[$dataObject->getData('_id')] = $dataObject;
        }

        return $resultRows;
    }

    /**
     * @inheritDoc
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            self::MAGE_STATUS_KEY,
            [
                'label' => __('Magento Status'),
                'renderer' => $this->getMageStatusRenderer(),
            ]
        );
        $this->addColumn(
            self::SCHEMA_ORG_STATUS_KEY,
            [
                'label'    => __('Schema.org Status'),
                'renderer' => $this->getSchemaOrgStatusRenderer(),
            ]
        );
        $this->_addAfter       = false;
        $this->_addButtonLabel = __('Not usable');
    }

    /**
     * @inheritDoc
     */
    protected function _prepareArrayRow(DataObject $row)
    {
        $options     = [];
        $orderStatus = $row->getData(self::SCHEMA_ORG_STATUS_KEY);
        if ($orderStatus !== null) {
            $options['option_' . $this->getSchemaOrgStatusRenderer()->calcOptionHash($orderStatus)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * Get Schema org status renderer
     *
     * @return SchemaOrgStatusSelect
     */
    private function getSchemaOrgStatusRenderer()
    {
        if (!$this->schemaOrgStatusRenderer) {
            $this->schemaOrgStatusRenderer = $this->getLayout()->createBlock(
                SchemaOrgStatusSelect::class,
                '',
                [ 'data' => [ 'is_render_to_js_template' => true ] ]
            );
        }

        return $this->schemaOrgStatusRenderer;
    }

    /**
     * Get mage status renderer
     *
     * @return MageStatusColumn
     */
    private function getMageStatusRenderer()
    {
        if (!$this->mageStatusRenderer) {
            $this->mageStatusRenderer = $this->getLayout()->createBlock(
                MageStatusColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->mageStatusRenderer;
    }

    /**
     * Create a new DataObject and fill it with the needed data
     *
     * @param string $mageOrderStatus
     *
     * @return DataObject
     */
    private function createNewDataObject(string $mageOrderStatus): DataObject
    {
        $dataObject = $this->dataObjectFactory->create();
        $dataObject->setData(self::MAGE_STATUS_KEY, $mageOrderStatus);
        $dataObject->setData(self::SCHEMA_ORG_STATUS_KEY, '');
        $id = $this->createId();
        $dataObject->setData('_id', $id);
        $dataObject->setData('column_values', [
            $id . '_' . self::MAGE_STATUS_KEY => $dataObject->getData(self::MAGE_STATUS_KEY),
            $id . '_' . self::SCHEMA_ORG_STATUS_KEY => $dataObject->getData(self::SCHEMA_ORG_STATUS_KEY)
        ]);

        return $dataObject;
    }

    /**
     * Get all possible order status
     *
     * @return array
     */
    private function getMageStatusOptions(): array
    {
        return $this->statusCollectionFactory->create()->toOptionArray();
    }

    /**
     * Create a time base id
     *
     * @return string
     */
    private function createId(): string
    {
        return '_' . time() . '_' . explode(' ', microtime())[0];
    }
}
