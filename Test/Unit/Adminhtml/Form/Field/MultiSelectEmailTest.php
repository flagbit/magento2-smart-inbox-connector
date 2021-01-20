<?php

namespace EinsUndEins\TransactionMailExtender\Test\Unit\Adminhtml\Form\Field;

use EinsUndEins\TransactionMailExtender\Block\Adminhtml\Form\Field\MultiSelectEmail;
use Magento\Email\Model\Template\Config;
use PHPUnit\Framework\TestCase;

class MultiSelectEmailTest extends TestCase
{
    public function testToOptionArray(): void
    {
        $availableTemplatesArray = [
            [
                'label' => 'label1',
                'value' => 'value2'
            ],
            [
                'label' => 'label2',
                'value' => 'value2'
            ],
            [
                'label' => 'label3',
                'value' => 'value3'
            ]
        ];
        $emailConfigStub = $this->createMock(Config::class);
        $emailConfigStub->method('getAvailableTemplates')
            ->willReturn($availableTemplatesArray);

        $multiSelectEmail = new MultiSelectEmail($emailConfigStub);

        $this->assertEquals($availableTemplatesArray, $multiSelectEmail->toOptionArray());
    }
}
