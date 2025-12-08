<?php
declare(strict_types=1);

namespace GardenLawn\Core\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
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

            foreach ($themes as $theme) {
                $theme = trim($theme, " \t\n\r\0\x0B,");
                $output->writeln("<info>Synchronizing theme: '{$theme}'</info>");

                $frontendPath = 'frontend/' . $theme;
                $adminhtmlPath = 'adminhtml/' . $theme;
                $themePath = null;

                if ($staticDir->isExist($frontendPath)) {
                    $themePath = $frontendPath;
                } elseif ($staticDir->isExist($adminhtmlPath)) {
                    $themePath = $adminhtmlPath;
                }

                if ($themePath === null) {
                    $output->writeln("<warning>Theme '{$theme}' not found in pub/static/frontend or pub/static/adminhtml. Skipping.</warning>");
                    continue;
                }

                $output->writeln("<info>Found theme at 'pub/static/{$themePath}'. Starting sync...</info>");
                $files = $staticDir->readRecursively($themePath);

                if (empty($files)) {
                    $output->writeln("<warning>No files found in 'pub/static/{$themePath}'.</warning>");
                    continue;
                }

                foreach ($files as $file) {
                    // $file is the full relative path from pub/static, e.g., "frontend/Magento/luma/css/styles.css"
                    $sourcePath = $staticDir->getAbsolutePath($file);
                    $destinationKey = 'static/' . $file;

                    if ($staticDir->isFile($file)) {
                        $this->s3Adapter->uploadFile($sourcePath, $destinationKey);
                        $output->writeln("Uploaded: {$destinationKey}");
                    }
                }
            }

            $output->writeln("<info>Synchronization complete.</info>");
            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('S3 Static Sync Error: ' . $e->getMessage());
            $output->writeln("<error>An error occurred: {$e->getMessage()}</error>");
            return Cli::RETURN_FAILURE;
        }
    }
}
