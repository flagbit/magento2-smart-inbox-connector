<?php

namespace Flagbit\TransactionMailExtender\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class OrderStatusMatrix extends AbstractFieldArray
{
    /**
     * @inheritDoc
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'mage_status',
            [
                'label' => __('Magento Status'),
                'class' => 'required-entry',
            ]
        );
        $this->addColumn(
            'schema_org_status',
            [
                'label'    => __('Schema.org Status'),
                'renderer' => 'required-entry',
            ]
        );
        $this->_addAfter       = false;
        $this->_addButtonLabel = __('Not usable');
    }
}
