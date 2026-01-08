<?php
declare(strict_types=1);

namespace GardenLawn\Core\Model\InventoryConfigurableProductIndexer\Indexer;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameResolverInterface;
use Magento\InventoryConfigurableProductIndexer\Indexer\SelectBuilder as OriginalSelectBuilder;
use Magento\Inventory\Model\ResourceModel\SourceItem;
use Magento\Inventory\Model\ResourceModel\StockSourceLink;

class SelectBuilder extends OriginalSelectBuilder
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var IndexNameBuilder
     */
    private $indexNameBuilder;

    /**
     * @var IndexNameResolverInterface
     */
    private $indexNameResolver;

    /**
     * @var MetadataPool
     */
    private MetadataPool $metadataPool;
    /**
     * @var DefaultStockProviderInterface
     */
    private $defaultStockProvider;

    /**
     * @param ResourceConnection $resourceConnection
     * @param IndexNameBuilder $indexNameBuilder
     * @param IndexNameResolverInterface $indexNameResolver
     * @param MetadataPool $metadataPool
     * @param DefaultStockProviderInterface $defaultStockProvider
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        IndexNameBuilder $indexNameBuilder,
        IndexNameResolverInterface $indexNameResolver,
        MetadataPool $metadataPool,
        DefaultStockProviderInterface $defaultStockProvider
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->indexNameBuilder = $indexNameBuilder;
        $this->indexNameResolver = $indexNameResolver;
        $this->metadataPool = $metadataPool;
        $this->defaultStockProvider = $defaultStockProvider;
    }

    /**
     * Prepare select.
     *
     * @param int $stockId
     * @return Select
     * @throws Exception
     */
    public function execute(int $stockId): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $linkField = $metadata->getLinkField();

        // Instead of reading from inventory_stock_X (which might be stale or empty during full reindex),
        // we read directly from inventory_source_item joined with inventory_source_stock_link.

        $select = $connection->select()
            ->from(
                ['source_item' => $this->resourceConnection->getTableName(SourceItem::TABLE_NAME_SOURCE_ITEM)],
                [
                    IndexStructure::SKU => 'parent_product_entity.sku',
                    IndexStructure::QUANTITY => 'SUM(source_item.quantity)',
                    // If any child source item has status 1, the parent is salable.
                    IndexStructure::IS_SALABLE => 'MAX(source_item.status)',
                ]
            )
            ->joinInner(
                ['stock_source_link' => $this->resourceConnection->getTableName(StockSourceLink::TABLE_NAME_STOCK_SOURCE_LINK)],
                'stock_source_link.source_code = source_item.source_code',
                []
            )
            ->joinInner(
                ['product_entity' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'product_entity.sku = source_item.sku',
                []
            )
            ->joinInner(
                ['parent_link' => $this->resourceConnection->getTableName('catalog_product_super_link')],
                'parent_link.product_id = product_entity.entity_id',
                []
            )
            ->joinInner(
                ['parent_product_entity' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'parent_product_entity.' . $linkField . ' = parent_link.parent_id',
                []
            )
            ->where('stock_source_link.stock_id = ?', $stockId)
            ->group(['parent_product_entity.sku']);

        return $select;
    }
}
