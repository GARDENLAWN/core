<?php

namespace GardenLawn\Core\Cron;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader;
use Psr\Log\LoggerInterface;

class CopyErrorPages
{
    /**
     * @var Reader
     */
    protected $moduleReader;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var File
     */
    protected $fileDriver;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Reader $moduleReader
     * @param Filesystem $filesystem
     * @param File $fileDriver
     * @param LoggerInterface $logger
     */
    public function __construct(
        Reader $moduleReader,
        Filesystem $filesystem,
        File $fileDriver,
        LoggerInterface $logger
    ) {
        $this->moduleReader = $moduleReader;
        $this->filesystem = $filesystem;
        $this->fileDriver = $fileDriver;
        $this->logger = $logger;
    }

    /**
     * Execute the cron job
     *
     * @return void
     */
    public function execute()
    {
        try {
            $moduleDir = $this->moduleReader->getModuleDir('', 'GardenLawn_Core');
            $sourceDir = $moduleDir . '/pub/errors/gardenlawn';

            $pubDir = $this->filesystem->getDirectoryRead(DirectoryList::PUB)->getAbsolutePath();
            $destinationDir = $pubDir . 'errors/gardenlawn';

            if ($this->fileDriver->isExists($sourceDir)) {
                $this->copyDirectory($sourceDir, $destinationDir);

                // Update local.xml to use the new skin
                $localXmlPath = $pubDir . 'errors/local.xml';
                $localXmlSamplePath = $pubDir . 'errors/local.xml.sample';

                if (!$this->fileDriver->isExists($localXmlPath) && $this->fileDriver->isExists($localXmlSamplePath)) {
                    $this->fileDriver->copy($localXmlSamplePath, $localXmlPath);
                }

                if ($this->fileDriver->isExists($localXmlPath)) {
                    $content = $this->fileDriver->fileGetContents($localXmlPath);
                    $newContent = preg_replace('/<skin>.*?<\/skin>/', '<skin>gardenlawn</skin>', $content);
                    if ($content !== $newContent) {
                        $this->fileDriver->filePutContents($localXmlPath, $newContent);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error copying error pages: ' . $e->getMessage());
        }
    }

    /**
     * Recursively copy directory
     *
     * @param string $source
     * @param string $destination
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function copyDirectory($source, $destination)
    {
        if (!$this->fileDriver->isExists($destination)) {
            $this->fileDriver->createDirectory($destination);
        }

        $files = $this->fileDriver->readDirectory($source);
        foreach ($files as $file) {
            $fileName = basename($file);
            $destFile = $destination . '/' . $fileName;

            if ($this->fileDriver->isDirectory($file)) {
                $this->copyDirectory($file, $destFile);
            } else {
                $this->fileDriver->copy($file, $destFile);
            }
        }
    }
}
