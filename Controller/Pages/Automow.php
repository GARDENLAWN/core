<?php declare(strict_types=1);

namespace GardenLawn\Core\Controller\Pages;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Automow extends Action implements HttpGetActionInterface
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
        $page->getConfig()->getTitle()->set(__('Automow'));
        $page->getConfig()->setMetaTitle(__('Usługi ogrodnicze - Opole, województwo opolskie, Polska'));
        $page->getConfig()->setKeywords('usługi ogrodnicze, opole, opolskie, zakładanie ogrodów');
        $page->getConfig()->setDescription(__('Oferujemy usługi ogrodnicze w Opolu, w województwie opolskim oraz na terenie kraju. Zakładanie trawników, systemów nawadniania i prace związane z ogrodnictwem'));
        $breadcrumbsBlock = $page->getLayout()->getBlock('breadcrumbs');
        if ($breadcrumbsBlock) {
            $breadcrumbsBlock->addCrumb(
                'home',
                [
                    'label' => __('Strona główna'),
                    'link' => $this->_url->getUrl('/')
                ]
            );
        }
        return $page;
    }
}
