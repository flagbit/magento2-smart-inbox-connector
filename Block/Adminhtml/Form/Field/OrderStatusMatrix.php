<?php

namespace Flagbit\TransactionMailExtender\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class OrderStatusMatrix extends AbstractFieldArray
{
    private const MAGE_STATUS_KEY = 'mage_status';
    private const SCHEMA_ORG_STATUS_KEY = 'schema_org_status';

    /** @var SchemaOrgColumn $orderStatusRenderer */
    private $orderStatusRenderer;

    /**
     * @inheritDoc
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            self::MAGE_STATUS_KEY,
            [
                'label' => __('Magento Status'),
                'class' => 'required-entry',
            ]
        );
        $this->addColumn(
            self::SCHEMA_ORG_STATUS_KEY,
            [
                'label'    => __('Schema.org Status'),
                'renderer' => 'required-entry',
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
     * Get Schema orc status renderer
     *
     * @return SchemaOrgColumn
     */
    private function getSchemaOrgStatusRenderer()
    {
        if (!$this->orderStatusRenderer) {
            $this->orderStatusRenderer = $this->getLayout()->createBlock(
                SchemaOrgColumn::class,
                '',
                [ 'data' => [ 'is_render_to_js_template' => true ] ]
            );
        }

        return $this->orderStatusRenderer;
    }
}
