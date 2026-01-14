<?php
/**
 * Copyright © GardenLawn. All rights reserved.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Wishlist\CustomerData;

use Magento\Wishlist\CustomerData\Wishlist;
use Magento\Wishlist\Helper\Data as WishlistHelper;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;

class WishlistPlugin
{
    /**
     * @var WishlistHelper
     */
    private WishlistHelper $wishlistHelper;

    /**
     * @var ItemResolverInterface
     */
    private ItemResolverInterface $itemResolver;

    /**
     * @param WishlistHelper $wishlistHelper
     * @param ItemResolverInterface $itemResolver
     */
    public function __construct(
        WishlistHelper $wishlistHelper,
        ItemResolverInterface $itemResolver
    ) {
        $this->wishlistHelper = $wishlistHelper;
        $this->itemResolver = $itemResolver;
    }

    /**
     * Add call_for_price attribute to wishlist item data
     *
     * @param Wishlist $subject
     * @param array $result
     * @return array
     */
    public function afterGetSectionData(Wishlist $subject, array $result): array
    {
        if (empty($result['items'])) {
            return $result;
        }

        $wishlist = $this->wishlistHelper->getWishlist();
        if (!$wishlist->getId()) {
            return $result;
        }

        // Eager load products for wishlist items to avoid loop loading
        $items = $wishlist->getItemCollection();
        // Add attributes to select if needed, though getFinalProduct usually handles loading
        // $items->addAttributeToSelect(['call_for_price', 'sku']);

        // Map product ID to wishlist item for faster lookup
        $wishlistItemsByProductId = [];
        foreach ($items as $wishlistItem) {
            $wishlistItemsByProductId[$wishlistItem->getProductId()] = $wishlistItem;
        }

        foreach ($result['items'] as &$itemData) {
            $itemData['call_for_price'] = false; // Default

            $productId = null;
            // Try to extract product ID from add_to_cart_params
            if (isset($itemData['add_to_cart_params'])) {
                $params = json_decode($itemData['add_to_cart_params'], true);
                if (isset($params['data']['product'])) {
                    $productId = $params['data']['product'];
                }
            }

            // Fallback: sometimes image URL contains ID, or product_id might be exposed in future versions
            if (!$productId && isset($itemData['product_id'])) {
                $productId = $itemData['product_id'];
            }

            if ($productId && isset($wishlistItemsByProductId[$productId])) {
                $wishlistItem = $wishlistItemsByProductId[$productId];
                $product = $this->itemResolver->getFinalProduct($wishlistItem);

                $itemData['call_for_price'] = (bool)$product->getData('call_for_price');
                $itemData['product_sku'] = $product->getSku();
            }
        }

        return $result;
    }
}
