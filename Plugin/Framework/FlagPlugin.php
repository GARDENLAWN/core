<?php

namespace GardenLawn\Core\Plugin\Framework;

use Magento\Framework\Flag;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Serialize\Serializer\Serialize;

class FlagPlugin
{
    /**
     * @var Json
     */
    private $json;

    /**
     * @var Serialize
     */
    private $serialize;

    /**
     * @param Json $json
     * @param Serialize $serialize
     */
    public function __construct(
        Json $json,
        Serialize $serialize
    ) {
        $this->json = $json;
        $this->serialize = $serialize;
    }

    /**
     * @param Flag $subject
     * @param callable $proceed
     * @return mixed
     */
    public function aroundGetFlagData(Flag $subject, callable $proceed)
    {
        if ($subject->hasFlagData()) {
            $flagData = $subject->getData('flag_data');
            if ($flagData === null || $flagData === '') {
                return null;
            }
            try {
                $data = $this->json->unserialize($flagData);
            } catch (\InvalidArgumentException $exception) {
                try {
                    $data = $this->serialize->unserialize($flagData);
                } catch (\Exception $e) {
                    // If unserialization fails, return null or handle gracefully
                    // This prevents the "Unable to unserialize value" error
                    return null;
                }
            }
            return $data;
        }
        return $proceed();
    }
}
