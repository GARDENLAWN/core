<?php
declare(strict_types=1);

namespace GardenLawn\Core\Model\Sitemap\ItemProvider;

use GardenLawn\Core\ViewModel\Gallery as GalleryViewModel;
use Magento\Framework\DataObject;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\EntityFactoryInterface;

class Gallery implements ItemProviderInterface
{
    private GalleryViewModel $galleryViewModel;
    private SitemapItemInterfaceFactory $sitemapItemFactory;
    private StoreManagerInterface $storeManager;
    private EntityFactoryInterface $entityFactory;

    /**
     * @param GalleryViewModel $galleryViewModel
     * @param SitemapItemInterfaceFactory $sitemapItemFactory
     * @param StoreManagerInterface $storeManager
     * @param EntityFactoryInterface $entityFactory
     */
    public function __construct(
        GalleryViewModel $galleryViewModel,
        SitemapItemInterfaceFactory $sitemapItemFactory,
        StoreManagerInterface $storeManager,
        EntityFactoryInterface $entityFactory
    ) {
        $this->galleryViewModel = $galleryViewModel;
        $this->sitemapItemFactory = $sitemapItemFactory;
        $this->storeManager = $storeManager;
        $this->entityFactory = $entityFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems($storeId): array
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            $website = $this->storeManager->getWebsite($store->getWebsiteId());
            if ($website->getCode() !== 'gardenlawn') {
                return [];
            }
        } catch (\Exception $e) {
            return [];
        }

        $items = [];
        $worksInfo = $this->galleryViewModel->getWorksInfo();

        // Assuming the gallery page URL is /galeria
        // You might want to make this configurable or retrieve it dynamically if it's a CMS page
        $galleryPageUrl = 'galeria';

        // We only have one gallery page, but it contains multiple images.
        // The sitemap structure usually lists pages.
        // If we want to list images for the gallery page, we create one SitemapItem for the page
        // and attach all images to it.

        $images = [];
        foreach ($worksInfo as $gallery) {
            if (empty($gallery['images'])) {
                continue;
            }
            foreach ($gallery['images'] as $image) {
                $images[] = new DataObject([
                    'url' => $image['link'],
                    'title' => $image['title'] ?? $gallery['id'],
                    'caption' => $image['description'] ?? '',
                    'thumbnail' => $image['thumb'] ?? ''
                ]);
            }
        }

        if (!empty($images)) {
            $collection = new Collection($this->entityFactory);
            foreach ($images as $image) {
                $collection->addItem($image);
            }

             $items[] = $this->sitemapItemFactory->create([
                'url' => $galleryPageUrl,
                'updatedAt' => date('Y-m-d H:i:s'), // Or get the last modified date of the images
                'images' => $collection,
                'priority' => '0.5', // Default priority
                'changeFrequency' => 'weekly', // Default frequency
            ]);
        }

        return $items;
    }
}
