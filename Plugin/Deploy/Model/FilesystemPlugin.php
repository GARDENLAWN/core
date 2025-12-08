<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Deploy\Model;

use Closure;
use Magento\Deploy\Model\Filesystem as Subject;
use Magento\Framework\App\Filesystem\DirectoryList;
use Psr\Log\LoggerInterface;

class FilesystemPlugin
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Around plugin to prevent cleaning of pub/static directory during static content deployment.
     *
     * @param Subject $subject
     * @param Closure $proceed
     * @param array $directoryCodeList
     * @return void
     */
    public function aroundCleanupFilesystem(Subject $subject, Closure $proceed, array $directoryCodeList): void
    {
        // Filter out DirectoryList::STATIC_VIEW from the list of directories to be cleaned
        $filteredDirectoryCodeList = array_filter($directoryCodeList, function ($code) {
            return $code !== DirectoryList::STATIC_VIEW;
        });

        if (count($filteredDirectoryCodeList) < count($directoryCodeList)) {
            $this->logger->info('GardenLawn_Core: Skipping pub/static directory clean during static content deployment.');
        }

        // Proceed with cleaning the remaining directories
        if (!empty($filteredDirectoryCodeList)) {
            $proceed($filteredDirectoryCodeList);
        }
    }
}
