<?php declare(strict_types=1);

namespace GardenLawn\Core\Utils;

class Logger
{
    protected static string $fileName = '/var/www/html/magento/var/log/custom.log';

    public function __construct()
    {
        self::createFile();
    }

    public static function createFile()
    {
        return fopen(self::$fileName, 'wb');
    }

    public static function writeLog($message): void
    {
        if (!file_exists(self::$fileName)) {
            self::createFile();
        }
        $file = self::openFile();
        fwrite($file, date("Y-m-d H:i:s") . ": " . print_r($message, true) . "\n");
    }

    private static function openFile()
    {
        return fopen(self::$fileName, 'ab');
    }

    public static function closeFile(): void
    {
        $file = fopen(self::$fileName, 'rb');
        fclose($file);
    }

    public static function removeFile(): void
    {
        unlink(self::$fileName);
    }
}
