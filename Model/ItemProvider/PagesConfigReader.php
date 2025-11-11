<?php

namespace GardenLawn\Core\Model\ItemProvider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sitemap\Model\ItemProvider\ConfigReaderInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class ExamplePagesConfigReader
 * @package VendorName\Sitemap\Model\ItemProvider
 */
class PagesConfigReader implements ConfigReaderInterface
{
    const string XML_PATH_CHANGE_FREQUENCY = 'sitemap/pages/changefreq';
    const string XML_PATH_PRIORITY = 'sitemap/pages/priority';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     *
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param int $storeId
     * @return string
     */
    public function getPriority($storeId): string
    {
        $storeId = (int)$storeId;
        return $this->getConfigValue(self::XML_PATH_PRIORITY, $storeId);
    }

    /**
     * @param string $configPath
     * @param int $storeId
     *
     * @return string
     *
     */
    private function getConfigValue(string $configPath, int $storeId): string
    {
        $configValue = $this->scopeConfig->getValue(
            $configPath,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return (string)$configValue;
    }

    /**
     * @param int $storeId
     * @return string
     */
    public function getChangeFrequency($storeId): string
    {
        $storeId = (int)$storeId;
        return $this->getConfigValue(self::XML_PATH_CHANGE_FREQUENCY, $storeId);
    }
}
