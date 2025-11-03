<?php
declare(strict_types=1);

namespace GardenLawn\Core\ViewModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

class WebsiteSwitcher implements ArgumentInterface
{
    private StoreManagerInterface $storeManager;
    private UrlInterface $urlBuilder;

    public function __construct(
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder
    ) {
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Pobierz wszystkie dostępne website'y
     *
     * @return WebsiteInterface[]
     */
    public function getAvailableWebsites(): array
    {
        $allWebsites = $this->storeManager->getWebsites();

        return array_filter(
            $allWebsites,
            function (WebsiteInterface $website) {
                return $website->getCode() !== 'base';
            }
        );
    }

    /**
     * Pobierz aktualny website
     *
     * @return WebsiteInterface
     * @throws LocalizedException
     */
    public function getCurrentWebsite(): WebsiteInterface
    {
        return $this->storeManager->getWebsite();
    }

    /**
     * Pobierz URL dla danego website'u
     *
     * @param WebsiteInterface $website
     * @return string
     * @throws LocalizedException
     */
    public function getWebsiteUrl(WebsiteInterface $website): string
    {
        $defaultStore = $this->storeManager->getWebsite($website->getId())->getDefaultStore();

        if (!$defaultStore) {
            return '#';
        }

        return $defaultStore->getBaseUrl();
    }
}
