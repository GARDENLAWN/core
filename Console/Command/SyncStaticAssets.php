<?php
declare(strict_types=1);

namespace GardenLawn\Core\Console\Command;

use Exception;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GardenLawn\MediaGallery\Model\S3Adapter;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;

class SyncStaticAssets extends Command
{
    private const string THEME_OPTION = 'theme';

    private S3Adapter $s3Adapter;
    private Filesystem $filesystem;
    private LoggerInterface $logger;

    public function __construct(
        S3Adapter $s3Adapter,
        Filesystem $filesystem,
        LoggerInterface $logger,
        string $name = null
    ) {
        $this->s3Adapter = $s3Adapter;
        $this->filesystem = $filesystem;
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('gardenlawn:s3:sync-static')
            ->setDescription('Synchronizes static assets for specific themes to S3.')
            ->addOption(
                self::THEME_OPTION,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The theme(s) to synchronize (e.g., Magento/luma).'
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $themes = $input->getOption(self::THEME_OPTION);

        if (empty($themes)) {
            $output->writeln('<error>You must specify at least one theme using --theme option.</error>');
            return Cli::RETURN_FAILURE;
        }

        $output->writeln("<info>Starting synchronization of static assets for themes to S3...</info>");

        try {
            $staticDir = $this->filesystem->getDirectoryRead(DirectoryList::STATIC_VIEW);

            $versionFilePath = 'deployed_version.txt';
            if (!$staticDir->isExist($versionFilePath) || !$staticDir->isFile($versionFilePath)) {
                $output->writeln("<error>deployed_version.txt not found in pub/static.</error>");
                return Cli::RETURN_FAILURE;
            }
            $version = trim($staticDir->readFile($versionFilePath));

            $filesToUpload = [];
            $existingS3Files = [];
            $allS3Files = [];

            // Fetch ALL existing files from S3 static directory (across all versions)
            $output->writeln("<info>Fetching all existing static files from S3...</info>");
            $s3Objects = $this->s3Adapter->listObjectsByStorageType('static', '');

            // Ensure $s3Objects is iterable
            if ($s3Objects instanceof \Generator || is_iterable($s3Objects)) {
                foreach ($s3Objects as $object) {
                    // Check if $object is an array and has 'Key'
                    if (!is_array($object) || !isset($object['Key'])) {
                        continue;
                    }

                    // Key format: static/versionXXXX/frontend/Theme/Name/file.ext
                    $key = $object['Key'];
                    $allS3Files[$key] = $object['Size'] ?? 0;

                    // Extract relative path without version prefix to compare content across versions
                    // Expected format: prefix/static/versionXXXX/path/to/file
                    // We want: path/to/file and the size
                    if (preg_match('#/version\d+/(.*)$#', $key, $matches)) {
                        $relativePath = $matches[1];
                        // Store mapping of relative path + size => existing S3 key
                        // This allows us to find if this exact file content exists in ANY version folder
                        $existingS3Files[$relativePath . '_' . ($object['Size'] ?? 0)] = $key;
                    }
                }
            } else {
                $output->writeln("<warning>No existing static files found in S3 or invalid response.</warning>");
            }

            // Track which files we are uploading/keeping for the CURRENT version
            $currentVersionFiles = [];

            // First, gather all files to get a total count
            foreach ($themes as $theme) {
                $theme = trim($theme, " \t\n\r\0\x0B,");
                $output->writeln("<info>Scanning theme: '{$theme}'</info>");

                $frontendPath = 'frontend/' . $theme;
                $adminhtmlPath = 'adminhtml/' . $theme;
                $themePath = null;

                if ($staticDir->isExist($frontendPath)) {
                    $themePath = $frontendPath;
                } elseif ($staticDir->isExist($adminhtmlPath)) {
                    $themePath = $adminhtmlPath;
                }

                if ($themePath === null) {
                    $output->writeln("<warning>Theme '{$theme}' not found. Skipping.</warning>");
                    continue;
                }

                $files = $staticDir->readRecursively($themePath);
                foreach ($files as $file) {
                    if ($staticDir->isFile($file)) {
                        $destinationPath = 'version' . $version . '/' . $file;
                        $fullS3Key = $this->s3Adapter->getPrefixedPath('static', $destinationPath);
                        $localFileSize = $staticDir->stat($file)['size'];

                        // Mark this file as needed for the current version
                        $currentVersionFiles[$fullS3Key] = true;

                        // Check if file exists in S3 at the EXACT destination path and has the same size
                        if (isset($allS3Files[$fullS3Key]) && $allS3Files[$fullS3Key] == $localFileSize) {
                            // File already exists in the correct version folder with correct size
                            continue;
                        }

                        $relativePath = $file;
                        $lookupKey = $relativePath . '_' . $localFileSize;

                        if (isset($existingS3Files[$lookupKey])) {
                            // Found an identical file in another version folder!
                            $sourceKey = $existingS3Files[$lookupKey];

                             $filesToUpload[] = [
                                'sourcePath' => $staticDir->getAbsolutePath($file),
                                'destinationPath' => $destinationPath,
                                'copyFromS3Key' => $sourceKey // Optimization hint
                            ];
                        } else {
                            $filesToUpload[] = [
                                'sourcePath' => $staticDir->getAbsolutePath($file),
                                'destinationPath' => $destinationPath,
                            ];
                        }
                    }
                }
            }

            // Identify files to delete (cleanup old versions or stale files)
            $filesToDelete = [];
            foreach ($allS3Files as $key => $size) {
                // Filter by themes being processed
                $belongsToProcessedTheme = false;
                foreach ($themes as $theme) {
                    $theme = trim($theme, " \t\n\r\0\x0B,");
                    // Ensure we match the full theme directory by appending '/'
                    // This prevents matching "Magento/luma" against "Magento/luma-child"
                    if (strpos($key, 'frontend/' . $theme . '/') !== false || strpos($key, 'adminhtml/' . $theme . '/') !== false) {
                        $belongsToProcessedTheme = true;
                        break;
                    }
                }

                if ($belongsToProcessedTheme) {
                    // If it belongs to a processed theme, but is NOT in the current version files list, DELETE IT.
                    // This covers:
                    // 1. Files from OLD versions (different version path)
                    // 2. Files removed from the theme in the current version
                    if (!isset($currentVersionFiles[$key])) {
                        $filesToDelete[] = $key;
                    }
                }
            }

            if (empty($filesToUpload) && empty($filesToDelete)) {
                $output->writeln("<info>All files are already synchronized.</info>");
                return Cli::RETURN_SUCCESS;
            }

            // Process Deletions
            if (!empty($filesToDelete)) {
                $output->writeln("<info>Deleting " . count($filesToDelete) . " obsolete files...</info>");
                $this->s3Adapter->deleteObjects($filesToDelete);
            }

            // Process Uploads/Copies
            if (!empty($filesToUpload)) {
                $output->writeln("<info>Synchronizing " . count($filesToUpload) . " files...</info>");
                $progressBar = new ProgressBar($output, count($filesToUpload));
                $progressBar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
                $progressBar->start();

                $this->s3Adapter->uploadStaticFiles($filesToUpload, function () use ($progressBar) {
                    $progressBar->advance();
                });

                $progressBar->finish();
            }

            $output->writeln("\n<info>Synchronization complete.</info>");

            return Cli::RETURN_SUCCESS;
        } catch (Exception $e) {
            $this->logger->error('S3 Static Sync Error: ' . $e->getMessage());
            $output->writeln("<error>An error occurred: {$e->getMessage()}</error>");
            return Cli::RETURN_FAILURE;
        }
    }
}
