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
use GardenLawn\Core\Model\S3Adapter;
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
                        $filesToUpload[] = $file;
                    }
                }
            }

            if (empty($filesToUpload)) {
                $output->writeln("<warning>No files found to upload.</warning>");
                return Cli::RETURN_SUCCESS;
            }

            // Setup and run the progress bar
            $output->writeln("<info>Uploading files...</info>");
            $progressBar = new ProgressBar($output, count($filesToUpload));
            $progressBar->start();

            foreach ($filesToUpload as $file) {
                $sourcePath = $staticDir->getAbsolutePath($file);
                $destinationKey = 'static/version' . $version . '/' . $file;

                $this->s3Adapter->uploadStaticFile($sourcePath, $destinationKey);
                $progressBar->advance();
            }

            $progressBar->finish();
            $output->writeln("\n<info>Synchronization complete.</info>");

            return Cli::RETURN_SUCCESS;
        } catch (Exception $e) {
            $this->logger->error('S3 Static Sync Error: ' . $e->getMessage());
            $output->writeln("<error>An error occurred: {$e->getMessage()}</error>");
            return Cli::RETURN_FAILURE;
        }
    }
}
