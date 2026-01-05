<?php

namespace GardenLawn\Core\Cron;

use Aws\S3\S3Client;
use Exception;
use GardenLawn\Core\Api\Data\ScraperService;
use GardenLawn\Core\Utils\Logger;
use GardenLawn\Core\Utils\Utils;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Adapter\AdapterInterface;

class Custom
{
    protected ObjectManager $objectManager;
    protected S3Client $s3client;
    protected AdapterInterface $connection;

    public function __construct()
    {
        $this->objectManager = ObjectManager::getInstance();
        $this->s3client = Utils::getS3Client();
        $resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
        $this->connection = $resource->getConnection();
    }

    /**
     * @throws Exception
     */
    public function execute(): void
    {
        //return;
        //ScraperService::saveAutomowJsonData();
        ScraperService::prepareAutomowJsonData();

        try {

            $string = file_get_contents(BP . "/app/code/GardenLawn/Core/Configs/export-images.json");
            $string = json_decode($string);
            $string = json_encode($string);
            file_put_contents(BP . "/app/code/GardenLawn/Core/Configs/export-images.json", $string);

            $string = file_get_contents(BP . "/app/code/GardenLawn/Core/Configs/automow_prepared_data.json");
            $string = json_decode($string);
            $string = json_encode($string);
            file_put_contents(BP . "/app/code/GardenLawn/Core/Configs/automow_prepared_data.json", $string);

            $string = file_get_contents(BP . "/app/code/GardenLawn/Core/Configs/automow_prepared_configurable_data.json");
            $string = json_decode($string);
            $string = json_encode($string);
            file_put_contents(BP . "/app/code/GardenLawn/Core/Configs/automow_prepared_configurable_data.json", $string);

            $string = file_get_contents(BP . "/app/code/GardenLawn/Core/Configs/automow_prepared_single_data.json");
            $string = json_decode($string);
            $string = json_encode($string);
            file_put_contents(BP . "/app/code/GardenLawn/Core/Configs/automow_prepared_single_data.json", $string);

            $string = file_get_contents(BP . "/app/code/GardenLawn/Core/Configs/automow_prepared_description_data_processed.json");
            $string = json_decode($string);
            $string = json_encode($string);
            file_put_contents(BP . "/app/code/GardenLawn/Core/Configs/automow_prepared_description_data_processed.json", $string);
        } catch (Exception) {
        }
    }
}
