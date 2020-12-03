<?php

namespace EinsUndEins\TransactionMailExtender\Test\Unit\Adminhtml\Form\Field;

use EinsUndEins\TransactionMailExtender\Block\Adminhtml\Form\Field\MageStatusColumn;
use Magento\Backend\Block\Template\Context;
use PHPUnit\Framework\TestCase;

class MageStatusColumnTest extends TestCase
{
    public function testSetInputName(): void
    {
        $value = 'name';
        $column = $this->createMageStatusColumn();
        $column->setInputName($value);
        $this->assertEquals($value, $column->getData('name'));
    }

    public function testToHtml(): void
    {
        $column = $this->createMageStatusColumn();
        $column->setInputName('name');
        $column->setColumnName('column-name');
        $column->setInputId('input-id');
        $column->setColumn(['size' => 10, 'style' => 'display: none']);

        $expected = '<input type="text"'
            . ' id="input-id"'
            . ' name="name"'
            . ' value="<%- column-name %>"'
            . ' size="10"'
            . ' class="input-text"'
            . ' style="display: none"'
            . ' readonly />';

        $this->assertEquals($expected, $column->toHtml());
    }

    public function testToHtml2(): void
    {
        $column = $this->createMageStatusColumn();
        $column->setInputName('name2');
        $column->setColumnName('column-name2');
        $column->setInputId('input-id2');
        $column->setColumn(['size' => false, 'class' => 'css-class']);

        $expected = '<input type="text"'
            . ' id="input-id2"'
            . ' name="name2"'
            . ' value="<%- column-name2 %>"'
            . '  class="css-class"'
            . ' readonly />';

        $this->assertEquals($expected, $column->toHtml());
    }

    /**
     * @return MageStatusColumn
     */
    private function createMageStatusColumn(): MageStatusColumn
    {
        $contextStub = $this->createMock(Context::class);

        return new MageStatusColumn($contextStub);
    }
}
