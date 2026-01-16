<?php
declare(strict_types=1);

namespace GardenLawn\Core\Console\Command;

use Exception;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GardenLawn\MediaGallery\Model\S3Adapter;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;

class SyncStaticAssets extends Command
{
    private const string THEME_ARGUMENT = 'theme';

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
            ->addArgument(
                self::THEME_ARGUMENT,
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'The theme(s) to synchronize (e.g., Magento/luma).'
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $themes = $input->getArgument(self::THEME_ARGUMENT);
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
            foreach ($s3Objects as $object) {
                // Key format: static/versionXXXX/frontend/Theme/Name/file.ext
                $key = $object['Key'];
                $allS3Files[$key] = $object['Size'];

                // Extract relative path without version prefix to compare content across versions
                // Expected format: prefix/static/versionXXXX/path/to/file
                // We want: path/to/file and the size
                if (preg_match('#/version\d+/(.*)$#', $key, $matches)) {
                    $relativePath = $matches[1];
                    // Store mapping of relative path + size => existing S3 key
                    // This allows us to find if this exact file content exists in ANY version folder
                    $existingS3Files[$relativePath . '_' . $object['Size']] = $key;
                }
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

                        // Check if the same file (same path suffix and size) exists in ANY other version folder
                        // If so, we can copy it instead of uploading (server-side copy is faster)
                        // For now, the requirement is just to skip if identical.
                        // But since the version folder CHANGED, the path is different.
                        // The user asked: "nawet jak wersja sie zmienia to zmiana jest nazwy folderu ale pliki identyczne sa ponijane bo isntieja"
                        // This implies we should COPY the file from the old version to the new version if it exists.
                        // OR, if the user means we should just NOT upload it and somehow reuse it?
                        // Magento static signing relies on the path containing the version.
                        // So the file MUST exist at the new path.
                        // To optimize, we can COPY from old version to new version on S3 side.

                        $relativePath = $file;
                        $lookupKey = $relativePath . '_' . $localFileSize;

                        if (isset($existingS3Files[$lookupKey])) {
                            // Found an identical file in another version folder!
                            $sourceKey = $existingS3Files[$lookupKey];

                            // We can perform a server-side copy
                            // But S3Adapter doesn't have a simple copy method exposed publicly that takes full keys easily in this context without some work.
                            // Let's add a copyObject method to S3Adapter or use what we have.
                            // Actually, the user said "pliki identyczne sa pomijane bo istnieja".
                            // If the URL changes (due to version), the file MUST exist at the new URL.
                            // So we cannot "skip" creating it. We must create it.
                            // But we can optimize by COPYING instead of UPLOADING.

                            // Let's assume for now we just upload to be safe and simple,
                            // UNLESS the user implies that we should just check if it exists in the NEW location.
                            // But the user said "nawet jak wersja sie zmienia... pliki identyczne sa pomijane".
                            // This is tricky. If version changes, the path changes. So the file DOES NOT exist at the new path.
                            // If we skip it, 404.

                            // INTERPRETATION:
                            // Maybe the user means: If I run sync, and the file is already there (from previous run of THIS version), skip it.
                            // AND if I run sync for a NEW version, but the file content is same as OLD version,
                            // maybe we can do a server-side copy?

                            // Let's implement Server-Side Copy for optimization if found in another version.
                            // This saves bandwidth.

                            // However, looking at the request: "usuwane musza byc te ktorych juz nie ma"
                            // This implies cleanup of OLD versions or files that are no longer in the current theme?
                            // Usually "sync" implies making destination match source.
                            // So we should delete files in the CURRENT version folder that are not in source.
                            // And maybe delete OLD version folders entirely?

                            // Let's stick to the most robust interpretation:
                            // 1. Ensure all needed files for CURRENT version exist in S3 (Upload or Copy).
                            // 2. Delete files in S3 that are NOT needed (e.g. old versions).

                            // Let's add copy capability to S3Adapter first.
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
            // The user said: "usuwane musza byc te ktorych juz nie ma"
            // This usually means: Delete anything in S3 that is NOT in the list of files we just processed for the CURRENT version.
            // AND also delete entire folders of OLD versions?
            // Let's assume we want to keep ONLY the current version in S3 to save space/cleanup.

            $filesToDelete = [];
            foreach ($allS3Files as $key => $size) {
                // Check if this file belongs to one of the themes we are processing?
                // Or just clean up everything that is not in the current version map?
                // If we run this command for ONLY "Magento/luma", we shouldn't delete "Magento/backend".
                // So we need to be careful.

                // Filter by themes being processed
                $belongsToProcessedTheme = false;
                foreach ($themes as $theme) {
                    $theme = trim($theme, " \t\n\r\0\x0B,");
                    if (strpos($key, 'frontend/' . $theme) !== false || strpos($key, 'adminhtml/' . $theme) !== false) {
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

                // We need to modify uploadStaticFiles to handle "copy" optimization or do it here.
                // Since uploadStaticFiles takes an array, let's modify it to handle the 'copyFromS3Key' hint if possible,
                // or just iterate here.
                // To keep S3Adapter clean, let's just use a loop here or add a smart method.
                // Actually, S3Adapter::uploadStaticFiles uses CommandPool for concurrency.
                // We should probably extend that to support CopyObject commands.

                // For now, let's just use the existing uploadStaticFiles but we need to handle the copy logic.
                // I will update S3Adapter to support a "smart sync" or just handle it here.
                // Updating S3Adapter to support 'copySource' in the file array is best.

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
