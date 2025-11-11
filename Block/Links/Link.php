<?php namespace GardenLawn\Core\Block\Links;

use Magento\Framework\View\Element\Template\Context;

class Link extends \Magento\Framework\View\Element\Html\Link
{
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getHref(): string
    {
        $page_url = 'terms_and_conditions';
        return $this->getUrl($page_url);
    }

    public function getLabel(): string
    {
        return 'Regulamin sklepu';
    }
}
