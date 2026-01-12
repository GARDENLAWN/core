<?php
declare(strict_types=1);

namespace GardenLawn\Core\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Html\Select;

class RoundingMethodColumn extends Select
{
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    public function setInputId($value)
    {
        return $this->setId($value);
    }

    public function _toHtml()
    {
        if (!$this->getOptions()) {
            $this->addOption('nearest', __('Nearest'));
            $this->addOption('up', __('Up'));
            $this->addOption('down', __('Down'));
        }
        return parent::_toHtml();
    }
}
