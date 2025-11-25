<?php
declare(strict_types=1);

namespace GardenLawn\Core\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class WebsiteSwitcher implements ArgumentInterface
{
    private StoreManagerInterface $storeManager;
    private ScopeConfigInterface $scopeConfig;
    private Repository $assetRepo;

    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        Repository $assetRepo
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->assetRepo = $assetRepo;
    }

    /**
     * Pobierz wszystkie dostępne website'y
     *
     * @return WebsiteInterface[]
     */
    public function getAvailableWebsites(): array
    {
        $allWebsites = $this->storeManager->getWebsites();

        $websites = array_filter(
            $allWebsites,
            function (WebsiteInterface $website) {
                return $website->getCode() !== 'base';
            }
        );

        $sortOrder = ['gardenlawn', 'amrobots', 'finnpolska'];
        $sortedWebsites = [];

        foreach ($sortOrder as $code) {
            foreach ($websites as $website) {
                if ($website->getCode() === $code) {
                    $sortedWebsites[] = $website;
                }
            }
        }

        return $sortedWebsites;
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

    /**
     * Pobierz logo dla danego website'u
     *
     * @param WebsiteInterface $website
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getWebsiteLogo(WebsiteInterface $website): string
    {
        $logoMap = [
            'gardenlawn' => 'https://pub.gardenlawn.pl/media/images/logo.webp',
            'amrobots' => 'https://pub.am-robots.pl/media/producers/am-robots.webp',
            'finnpolska' => 'https://pub.finnpolska.pl/media/producers/finn.webp'
        ];

        $websiteCode = $website->getCode();

        if (isset($logoMap[$websiteCode])) {
            $logoPath = $logoMap[$websiteCode];
            if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
                return $logoPath;
            }
            return $this->assetRepo->getUrl($logoPath);
        }

        // Fallback to default logo logic if not in map
        $logoPath = $this->scopeConfig->getValue(
            'design/header/logo_src',
            ScopeInterface::SCOPE_WEBSITE,
            $website->getCode()
        );

        if (!$logoPath) {
            $logoPath = $this->scopeConfig->getValue(
                'design/header/logo_src',
                ScopeInterface::SCOPE_STORE,
                $this->storeManager->getStore()->getId()
            );
        }

        if (!$logoPath) {
            return '';
        }

        $asset = $this->assetRepo->createAsset('logo/' . $logoPath, ['_secure' => $this->isSecure()]);
        return $asset->getUrl();
    }

    private function isSecure(): bool
    {
        try {
            return $this->storeManager->getStore()->isCurrentlySecure();
        } catch (LocalizedException $e) {
            return false;
        }
    }
}
