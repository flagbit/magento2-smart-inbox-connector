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
        return $this->emailConfig->getAvailableTemplates();
    }
}
