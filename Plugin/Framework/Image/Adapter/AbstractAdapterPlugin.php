<?php

namespace GardenLawn\Core\Plugin\Framework\Image\Adapter;

use InvalidArgumentException;
use Magento\Framework\Image\Adapter\AbstractAdapter;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class AbstractAdapterPlugin
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @throws LocalizedException
     */
    public function aroundValidateUploadFile(
        AbstractAdapter $subject,
        callable        $proceed,
                        $filePath
    )
    {
        try {
            return $proceed($filePath);
        } catch (InvalidArgumentException $e) {
            $this->logger->warning(
                'Skipped disallowed file type during import: ' . $filePath
            );

            // Zwrócenie false sprawi, że walidacja się nie powiedzie,
            // a plik zostanie pominięty przez proces importu.
            return false;
        }
    }
}
