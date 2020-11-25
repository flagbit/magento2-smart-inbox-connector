<?php

namespace Flagbit\TransactionMailExtender\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Html\Select;

class SchemaOrgColumn extends Select
{
    /**
     * Set "name" for <select> element
     *
     * @param string $value
     *
     * @return $this
     */
    public function setInputName(string $value)
    {
        return $this->setName($value);
    }

    /**
     * Set "id" for <select> element
     *
     * @param string $value
     *
     * @return $this
     */
    public function setInputId(string $value)
    {
        return $this->setId($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }

        return parent::_toHtml();
    }

    /**
     * Get possible options
     *
     * @return array
     */
    public function getSourceOptions(): array
    {
        // @TODO add possible schema org order status
        return [
            ['label' => 'OrderCanceled', 'value' => 'OrderCanceled'],
            ['label' => 'OrderPending', 'value' => 'OrderPending']
        ];
    }
}
