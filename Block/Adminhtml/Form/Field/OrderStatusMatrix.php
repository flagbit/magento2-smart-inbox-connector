<?php

namespace Flagbit\TransactionMailExtender\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class OrderStatusMatrix extends AbstractFieldArray
{
    private const MAGE_STATUS_KEY = 'mage_status';
    private const SCHEMA_ORG_STATUS_KEY = 'schema_org_status';

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
}
