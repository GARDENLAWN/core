<?php declare(strict_types=1);

namespace GardenLawn\Core\Controller\Pages;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Test extends Action implements HttpGetActionInterface
{
    private PageFactory $pageFactory;
    private Registry $coreRegistry;

    public function __construct(Context $context, PageFactory $pageFactory, Registry $coreRegistry)
    {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->coreRegistry = $coreRegistry;
    }

    public function execute(): Page
    {
        $objectManager = ObjectManager::getInstance();
        $category = $objectManager->get('Magento\Catalog\Model\Category')->load(0);
        $this->coreRegistry->register('current_category', $category);

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Test'));
        $breadcrumbsBlock = $page->getLayout()->getBlock('breadcrumbs');
        if ($breadcrumbsBlock) {
            $breadcrumbsBlock->addCrumb(
                'home',
                [
                    'label' => __('Strona główna'),
                    'link' => $this->_url->getUrl('/')
                ]
            );
            $breadcrumbsBlock->addCrumb(
                'test',
                [
                    'label' => __('Test'),
                    'link' => $this->_url->getUrl('test')
                ]
            );
        }
        return $page;
    }
}
