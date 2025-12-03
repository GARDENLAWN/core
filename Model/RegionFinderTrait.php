<?php
declare(strict_types=1);

namespace GardenLawn\Core\Model;

use Magento\Directory\Model\RegionFactory;

/**
 * Trait for finding region ID by name.
 *
 * This trait requires the consuming class to have a `regionFactory` property
 * of type Magento\Directory\Model\RegionFactory.
 */
trait RegionFinderTrait
{
    /**
     * Get region ID by name and country ID.
     *
     * @param string $regionName
     * @param string $countryId
     * @return int|null
     */
    private function getRegionIdByName(string $regionName, string $countryId): ?int
    {
        /** @var RegionFactory $this->regionFactory */
        $region = $this->regionFactory->create();
        $region->loadByName(ucfirst($regionName), $countryId);
        if ($region->getId()) {
            return (int)$region->getId();
        }
        return null;
    }
}
