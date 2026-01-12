<?php
declare(strict_types=1);

namespace GardenLawn\Core\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Catalog\Api\Data\ProductAttributeInterface;

class AttributeSet extends \Magento\Framework\DataObject implements OptionSourceInterface
{
    /**
     * @var AttributeSetRepositoryInterface
     */
    private $attributeSetRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    public function __construct(
        AttributeSetRepositoryInterface $attributeSetRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->attributeSetRepository = $attributeSetRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_type_code', ProductAttributeInterface::ENTITY_TYPE_CODE)
            ->create();

        $attributeSets = $this->attributeSetRepository->getList($searchCriteria)->getItems();

        $options = [];
        foreach ($attributeSets as $set) {
            $options[] = [
                'value' => $set->getAttributeSetId(),
                'label' => $set->getAttributeSetName()
            ];
        }

        return $options;
    }
}
