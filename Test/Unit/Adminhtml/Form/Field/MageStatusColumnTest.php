<?php

namespace EinsUndEins\TransactionMailExtender\Test\Unit\Adminhtml\Form\Field;

use EinsUndEins\TransactionMailExtender\Block\Adminhtml\Form\Field\MageStatusColumn;
use PHPUnit\Framework\TestCase;

class MageStatusColumnTest extends TestCase
{
    public function testSetInputName(): void
    {
        $value = 'name';
        $column = new MageStatusColumn();
        $column->setInputName($value);
        $this->assertEquarls($value, $column->getData('name'));
    }

    public function testToHtml(): void
    {
        $column = new MageStatusColumn();
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
        $column = new MageStatusColumn();
        $column->setInputName('name2');
        $column->setColumnName('column-name2');
        $column->setInputId('input-id2');
        $column->setColumn(['class' => 'css-class']);

        $expected = '<input type="text"'
            . ' id="input-id2"'
            . ' name="name2"'
            . ' value="<%- column-name2 %>"'
            . ' class="css-class"'
            . ' readonly />';

        $this->assertEquals($expected, $column->toHtml());
    }
}
