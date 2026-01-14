<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin;

use Magento\Sales\CustomerData\LastOrderedItems;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Helper\Image;
use Magento\Store\Model\StoreManagerInterface;

class LastOrderedItemsPlugin
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productCollectionFactory;

    /**
     * @var Image
     */
    private Image $imageHelper;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param CollectionFactory $productCollectionFactory
     * @param Image $imageHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CollectionFactory $productCollectionFactory,
        Image $imageHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
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
    public function afterGetSectionData(LastOrderedItems $subject, array $result): array
    {
        if (empty($result['items'])) {
            return $result;
        }

        $productIds = [];
        foreach ($result['items'] as $item) {
            if (isset($item['product_id'])) {
                $productIds[] = $item['product_id'];
            }
        }

        if (empty($productIds)) {
            return $result;
        }

        try {
            $collection = $this->productCollectionFactory->create();
            $collection->addIdFilter($productIds)
                ->addAttributeToSelect(['small_image', 'name'])
                ->addStoreFilter($this->storeManager->getStore()->getId());

            $products = [];
            foreach ($collection as $product) {
                $products[$product->getId()] = $product;
            }

            foreach ($result['items'] as &$item) {
                $item['image'] = ''; // Default empty
                if (isset($item['product_id']) && isset($products[$item['product_id']])) {
                    $product = $products[$item['product_id']];
                    $imageUrl = $this->imageHelper->init($product, 'product_small_image')
                        ->resize(75)
                        ->getUrl();
                    $item['image'] = $imageUrl;
                }
            }
        } catch (\Exception $e) {
            // Log error if needed, but keep silent for frontend
        }

        return $result;
    }
}
