<?php

namespace EinsUndEins\TransactionMailExtender\Test\Unit\Adminhtml\Form\Field;

use EinsUndEins\SchemaOrgMailBody\Model\OrderInterface;
use EinsUndEins\TransactionMailExtender\Block\Adminhtml\Form\Field\SchemaOrgStatusSelect;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Escaper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SchemaOrgStatusSelectTest extends TestCase
{
    public function testSetInputName(): void
    {
        $value = 'name';
        $select = $this->createSelect();
        $select->setInputName($value);

        $this->assertEquals($value, $select->getData('name'));
    }

    public function testSetInputId(): void
    {
        $value = 'id';
        $select = $this->createSelect();
        $select->setInputId($value);#

        $this->assertEquals($value, $select->getData('id'));
    }

    public function testToHtml(): void
    {
        $expected = '<select name="name" id="id" class="class" title="title" params>';
        foreach (OrderInterface::POSSIBLE_ORDER_STATUS as $orderStatus) {
            $expected .= sprintf('<option value="%s" >%s</option>', $orderStatus, $orderStatus);
        }
        $expected .= '</select>';
        $select = $this->createFilledSelect();

        $this->assertEquals($expected, $select->_toHtml());
    }

    public function testToHtmlSetOptions(): void
    {
        $expected = '<select name="name" id="id" class="class" title="title" params>'
            . '<option value="value1" >label1</option>'
            . '<option value="value2" >label2</option>'
            . '<option value="value3" >label3</option>'
            . '</select>';
        $select = $this->createFilledSelect();
        $select->setOptions(
            [
                [
                    'label' => 'label1',
                    'value' => 'value1'
                ],
                [
                    'label' => 'label2',
                    'value' => 'value2'
                ],
                [
                    'label' => 'label3',
                    'value' => 'value3'
                ]
            ]
        );
        $this->assertEquals($expected, $select->_toHtml());
    }

    /**
     * Get a filled SchemaOrgStatusSelect
     * Filled with the following values:
     *   name => 'name'
     *   id => 'id'
     *   class => 'class'
     *   title => 'title'
     *   extraParams => 'params'
     *
     * @return SchemaOrgStatusSelect&MockObject
     */
    private function createFilledSelect()
    {
        $select = $this->createSelect();
        $select->setName('name');
        $select->setId('id');
        $select->setClass('class');
        $select->setTitle('title');
        $select->setExtraParams('params');

        return $select;
    }

    /**
     * @return SchemaOrgStatusSelect&MockObject
     */
    private function createSelect()
    {
        $escaperStub = $this->createMock(Escaper::class);
        $escaperStub->method('escapeHtml')
            ->will($this->returnArgument(0));

        $contextStub = $this->createMock(Context::class);
        $contextStub->method('getEscaper')
            ->willReturn($escaperStub);
        return new SchemaOrgStatusSelect($contextStub);
    }
}
