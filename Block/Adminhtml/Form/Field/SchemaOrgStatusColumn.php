<?php

namespace Flagbit\TransactionMailExtender\Block\Adminhtml\Form\Field;

use EinsUndEins\SchemaOrgMailBody\Model\AbstractOrderInterface;
use Magento\Framework\View\Element\Html\Select;

class SchemaOrgStatusColumn extends Select
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
        $result = [];
        foreach (AbstractOrderInterface::POSSIBLE_ORDER_STATUS as $orderStatus) {
            $result[] = ['label' => $orderStatus, 'value' =>$orderStatus];
        }

        return $result;
    }
}
