<?php
declare(strict_types=1);

namespace GardenLawn\Core\Model\ItemProvider;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Sitemap\Model\SitemapItemFactory;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Filesystem\Driver\File;

class Pages implements ItemProviderInterface
{
    protected array $sitemapItems = [];
    private PagesConfigReader $configReader;
    private SitemapItemFactory $itemFactory;
    private ModuleDirReader $moduleDirReader;
    private File $fileDriver;

    /**
     * @param PagesConfigReader $configReader
     * @param SitemapItemFactory $itemFactory
     * @param ModuleDirReader $moduleDirReader
     * @param File $fileDriver
     */
    public function __construct(
        PagesConfigReader $configReader,
        SitemapItemFactory $itemFactory,
        ModuleDirReader $moduleDirReader,
        File $fileDriver
    ) {
        $this->configReader = $configReader;
        $this->itemFactory = $itemFactory;
        $this->moduleDirReader = $moduleDirReader;
        $this->fileDriver = $fileDriver;
    }

    /**
     * @param int $storeId
     * @return array
     * @throws NoSuchEntityException|LocalizedException
     */
    public function getItems($storeId): array
    {
        $pages = $this->loadPagesFromFile();

        foreach ($pages as $page) {
            $this->sitemapItems[] = $this->itemFactory->create(
                [
                    'url' => $page['url'],
                    'updatedAt' => date("Y-m-d H:i:s"),
                    'priority' => $this->getPriority($storeId),
                    'changeFrequency' => $this->getChangeFrequency($storeId)
                ]
            );
        }

        return $this->sitemapItems;
    }

    /**
     * Load pages from pages.txt file.
     *
     * @return array
     * @throws LocalizedException
     */
    private function loadPagesFromFile(): array
    {
        $pages = [];
        $filePath = $this->moduleDirReader->getModuleDir('', 'GardenLawn_Core') . '/Model/ItemProvider/pages.txt';

        if (!$this->fileDriver->isExists($filePath)) {
            throw new LocalizedException(__('Sitemap pages file not found: %1', $filePath));
        }

        $content = $this->fileDriver->fileGetContents($filePath);
        $lines = explode(PHP_EOL, $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $parts = explode(',', $line, 2); // Limit to 2 parts to handle commas in name
            if (count($parts) === 2) {
                $pages[] = [
                    'name' => $parts[0],
                    'url' => $parts[1]
                ];
            }
        }
        return $pages;
    }

    /**
     * @param int $storeId
     *
     * @return string
     *
     */
    private function getChangeFrequency(int $storeId): string
    {
        return $this->configReader->getChangeFrequency($storeId);
    }

    /**
     * @param int $storeId
     *
     * @return string
     *
     */
    private function getPriority(int $storeId): string
    {
        return $this->configReader->getPriority($storeId);
    }
}
