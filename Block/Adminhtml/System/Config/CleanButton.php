<?php
/**
 * Copyright © GardenLawn. All rights reserved.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Widget\Button;
use Magento\Framework\Exception\LocalizedException;

class CleanButton extends Field
{
    // Zmieniamy, aby użyć nowego szablonu, który będzie renderował dwa przyciski w formie POST
    protected $_template = 'GardenLawn_Core::system/config/clean_buttons.phtml';

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    /**
     * Zwraca URL dla akcji czyszczenia (Clean.php)
     */
    public function getCleanUrl(): string
    {
        return $this->getUrl('gardenlawn_core/config/clean');
    }

    /**
     * Zwraca URL dla akcji testowej (CleanTest.php)
     */
    public function getCleanTestUrl(): string
    {
        return $this->getUrl('gardenlawn_core/config/cleantest');
    }

    /**
     * Generuje HTML dla przycisku "Run Cleaner"
     * @throws LocalizedException
     */
    public function getRunCleanerButtonHtml(): string
    {
        $button = $this->getLayout()->createBlock(Button::class)
            ->setData([
                'id' => 'run_cleaner_button',
                'label' => __('Run Cleaner'),
                'class' => 'action-primary', // Używamy action-primary dla głównej akcji
                'title' => __('Immediately deletes redundant configuration entries.'),
                'type' => 'submit'
            ]);

        return $button->toHtml();
    }

    /**
     * Generuje HTML dla przycisku "Test Run"
     * @throws LocalizedException
     */
    public function getTestRunButtonHtml(): string
    {
        $button = $this->getLayout()->createBlock(Button::class)
            ->setData([
                'id' => 'test_run_button',
                'label' => __('Dry Run (Test)'),
                'class' => 'action-secondary',
                'title' => __('Calculates redundant entries without deleting.'),
                'type' => 'submit'
            ]);

        return $button->toHtml();
    }
}
