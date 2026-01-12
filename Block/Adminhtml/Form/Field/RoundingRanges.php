<?php
declare(strict_types=1);

namespace GardenLawn\Core\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;

class RoundingRanges extends AbstractFieldArray
{
    /**
     * @var RoundingMethodColumn
     */
    private $methodRenderer;

    protected function _prepareToRender()
    {
        $this->addColumn('price_limit', [
            'label' => __('Price Up To'),
            'class' => 'required-entry validate-number'
        ]);
        $this->addColumn('rounding_target', [
            'label' => __('Target Decimal (e.g. .99)'),
            'class' => 'required-entry validate-number'
        ]);
        $this->addColumn('rounding_method', [
            'label' => __('Method'),
            'renderer' => $this->getMethodRenderer()
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Range');
    }

    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        $method = $row->getRoundingMethod();
        if ($method !== null) {
            $options['option_' . $this->getMethodRenderer()->calcOptionHash($method)] = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
    }

    private function getMethodRenderer()
    {
        if (!$this->methodRenderer) {
            $this->methodRenderer = $this->getLayout()->createBlock(
                RoundingMethodColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->methodRenderer;
    }
}
