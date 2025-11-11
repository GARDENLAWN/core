<?php declare(strict_types=1);

namespace GardenLawn\Core\Controller\Pages;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Gallery extends Action implements HttpGetActionInterface
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
        $page->getConfig()->getTitle()->set(__('Galeria'));
        $page->getConfig()->setMetaTitle(__('Galeria zdjęć wykonanych przez nas ogrodów i innych usług'));
        $page->getConfig()->setKeywords(__('galeria, zdjęcia, album, realizacje, opole, opolskie, polska'));
        $page->getConfig()->setDescription(__('Prezentacja naszych usług z galerią zdjęć. Zdjęcia wykonanych realizacji. W galerii poszczególnych ogrodów znajdują się zdjęcia ze wszystkich  etapów prac'));
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
                'gallery',
                [
                    'label' => __('Galeria'),
                    'link' => $this->_url->getUrl('galeria')
                ]
            );
        }
        return $page;
    }
}
