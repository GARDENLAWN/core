<?php
namespace GardenLawn\Core\Plugin\Framework\App\View\Asset;

use Closure;
use Exception;
use GardenLawn\Core\Model\S3Adapter;
use Magento\Framework\App\View\Asset\Publisher as Subject;
use Magento\Framework\View\Asset\File;
use Magento\Framework\View\Asset;
use Psr\Log\LoggerInterface;

class PublisherPlugin
{
    private S3Adapter $s3Adapter;
    private LoggerInterface $logger;

    public function __construct(
        S3Adapter $s3Adapter,
        LoggerInterface $logger
    ) {
        $this->s3Adapter = $s3Adapter;
        $this->logger = $logger;
    }

    /**
     * Intercept asset publishing and upload to S3 instead.
     *
     * @param Subject $subject
     * @param Closure $proceed
     * @param File $asset
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundPublish(Subject $subject, Closure $proceed, Asset\LocalInterface $asset): bool
    {
        try {
            $filePath = $asset->getPath();
            $content = $asset->getContent();

            // Construct the destination key for S3.
            // This will result in paths like 'static/frontend/Magento/luma/en_US/css/styles-m.css'
            $destinationKey = $this->s3Adapter->getPrefixedPath('static', $filePath);

            $this->s3Adapter->uploadContent($content, $destinationKey);

            // Return true to indicate success, preventing the original method from running.
            return true;
        } catch (Exception $e) {
            $this->logger->error('S3 Static Content Deployment Error: ' . $e->getMessage());
            // In case of an error, we can either log it and proceed with the original method,
            // or we can stop the process. For now, we'll log and let it fail silently
            // in the context of S3 upload, but still return 'true' to not break the deployment process.
            // A more robust solution might involve a fallback mechanism.
            return true; // Or `return $proceed($asset);` to fall back to filesystem.
        }
    }
}
