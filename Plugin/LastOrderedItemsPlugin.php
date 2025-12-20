<?php

namespace GardenLawn\Core\Plugin;

use Magento\Sales\CustomerData\LastOrderedItems;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Store\Model\StoreManagerInterface;

class LastOrderedItemsPlugin
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var Image
     */
    protected $imageHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param Image $imageHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        Image $imageHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->productRepository = $productRepository;
        $this->imageHelper = $imageHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * Add image URL to last ordered items
     *
     * @param LastOrderedItems $subject
     * @param array $result
     * @return array
     */
    public function afterGetSectionData(LastOrderedItems $subject, array $result)
    {
        if (isset($result['items'])) {
            foreach ($result['items'] as &$item) {
                if (isset($item['product_id'])) {
                    try {
                        $product = $this->productRepository->getById($item['product_id']);
                        $imageUrl = $this->imageHelper->init($product, 'product_small_image')
                            ->resize(75)
                            ->getUrl();
                        $item['image'] = $imageUrl;
                    } catch (\Exception $e) {
                        $item['image'] = '';
                    }
                }
            }
        }
        return $result;
    }
}
