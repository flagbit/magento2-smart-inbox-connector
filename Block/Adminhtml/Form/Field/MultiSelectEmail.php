<?php

namespace EinsUndEins\TransactionMailExtender\Block\Adminhtml\Form\Field;

use Magento\Email\Model\Template\Config;
use Magento\Framework\Data\OptionSourceInterface;

class MultiSelectEmail implements OptionSourceInterface
{
    /** @var Config $emailConfig */
    private $emailConfig;

    public function __construct(
        Config $emailConfig
    ) {
        $this->emailConfig = $emailConfig;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        $result = [];
        foreach ($this->getConfigTemplates() as $emailTemplate) {
            $result[] = [
                'value' => $emailTemplate['value'],
                'label' => $emailTemplate['label'],
            ];
        }

        return $result;
    }

    /**
     * Get list of available email templates
     *
     * @return array[]
     */
    private function getConfigTemplates()
    {
        return $this->emailConfig->getAvailableTemplates();
    }
}
