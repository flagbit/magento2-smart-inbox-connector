<?php

namespace Flagbit\TransactionMailExtender\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\AbstractBlock;

class MageStatusColumn extends AbstractBlock
{
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    public function toHtml()
    {
        $column = $this->getColumn();
        return '<input type="text" id="' . $this->getInputId() . '"' .
            ' name="' .
            $this->getInputName() .
            '" value="<%- ' .
            $this->getColumnName() .
            ' %>" ' .
            ($column['size'] ? 'size="' .
                $column['size'] .
                '"' : '') .
            ' class="' .
            (isset($column['class'])
                ? $column['class']
                : 'input-text') . '"' . (isset($column['style']) ? ' style="' . $column['style'] . '"' : '')
            . 'readonly />';
    }
}