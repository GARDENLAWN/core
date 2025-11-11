<?php

namespace GardenLawn\Core\Observer;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;

class AddRow extends Container
{
    /**
     * Core registry.
     *
     * @var ?Registry
     */
    protected ?Registry $_coreRegistry = null;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context  $context,
        Registry $registry,
        array    $data = []
    )
    {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve text for header element depending on loaded image.
     *
     * @return Phrase
     */
    public function getHeaderText(): Phrase
    {
        return __('Add Row Data');
    }

    /**
     * Get form action URL.
     *
     * @return string
     */
    public function getFormActionUrl(): string
    {
        if ($this->hasFormActionUrl()) {
            return $this->getData('form_action_url');
        }

        return $this->getUrl('*/*/save');
    }

    /**
     * Initialize Imagegallery Images Edit Block.
     */
    protected function _construct(): void
    {
        $this->_objectId = 'row_id';
        $this->_blockGroup = 'GardenLawn_Company';
        $this->_controller = 'adminhtml_grid';
        parent::_construct();
        if ($this->_isAllowedAction('GardenLawn_Company::add_row')) {
            $this->buttonList->update('save', 'label', __('Save'));
        } else {
            $this->buttonList->remove('save');
        }
        $this->buttonList->remove('reset');
    }

    /**
     * Check permission for passed action.
     *
     * @param string $resourceId
     *
     * @return bool
     */
    protected function _isAllowedAction(string $resourceId): bool
    {
        return $this->_authorization->isAllowed($resourceId);
    }
}
