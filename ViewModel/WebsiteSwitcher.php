<?php
declare(strict_types=1);

namespace GardenLawn\Core\ViewModel;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

class WebsiteSwitcher implements ArgumentInterface
{
    private StoreManagerInterface $storeManager;

    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getWebsites(): array
    {
        $websites = [];
        $currentWebsiteId = $this->storeManager->getCurrentWebsite()->getId();

        // Pomiń stronę 'admin' (ID 0)
        foreach ($this->storeManager->getWebsites(false) as $website) {
            // Pokaż tylko te trzy strony, o które prosiłeś
            if (!in_array($website->getCode(), ['gardenlawn', 'amrobots', 'finnpolska'])) {
                continue;
            }

            $websites[] = [
                'name' => $website->getName(),
                'url' => $website->getBaseUrl(),
                'is_current' => $website->getId() === $currentWebsiteId
            ];
        }

        return $websites;
    }
}
