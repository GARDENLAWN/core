<?php
declare(strict_types=1);

namespace GardenLawn\Core\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;

class CleanButtons extends Field
{
    /**
     * @var string
     */
    protected $_template = 'GardenLawn_Core::system/config/clean_buttons.phtml';

    /**
     * @var UrlInterface
     */
    protected UrlInterface $urlBuilder;

    /**
     * @param Context $context
     * @param UrlInterface $urlBuilder
     * @param array $data
     */
    public function __construct(
        Context $context,
        UrlInterface $urlBuilder,
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $data);
    }

    /**
     * Render button
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        // Remove scope label
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get the button and scripts contents
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $originalData = $element->getOriginalData();
        $this->addData(
            [
                'button_label' => $originalData['button_label'] ?? __('Run Cleanup Now'),
                'html_id' => $element->getHtmlId(),
                'ajax_url_dry_run' => $this->urlBuilder->getUrl('gardenlawn_core/config/cleanup', ['is_dry_run' => 1]),
                'ajax_url_cleanup' => $this->urlBuilder->getUrl('gardenlawn_core/config/cleanup', ['is_dry_run' => 0]),
            ]
        );

        return $this->_toHtml();
    }
}
