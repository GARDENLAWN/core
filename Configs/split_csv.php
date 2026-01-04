<?php

$sourceFile = __DIR__ . '/AMRobotsAll_full_desc.csv';
$descFile = __DIR__ . '/AM_Desc.csv';
$baseFile = __DIR__ . '/AM_Base.csv';

if (!file_exists($sourceFile)) {
    die("Source file not found: $sourceFile\n");
}

$handle = fopen($sourceFile, 'r');
$descHandle = fopen($descFile, 'w');
$baseHandle = fopen($baseFile, 'w');

if ($handle === false || $descHandle === false || $baseHandle === false) {
    die("Error opening files.\n");
}

// Read header
$header = fgetcsv($handle, 0, ';');

if ($header === false) {
    die("Empty CSV file.\n");
}

// Find indices
$skuIndex = array_search('external_sku', $header);
$shortDescIndex = array_search('short_description', $header);
$descIndex = array_search('description', $header);

if ($skuIndex === false || $shortDescIndex === false || $descIndex === false) {
    die("Required columns not found.\n");
}

// Prepare headers for new files
$descHeader = ['external_sku', 'short_description', 'description'];
$baseHeader = array_values(array_diff($header, ['short_description', 'description']));

// Write headers
fputcsv($descHandle, $descHeader, ';');
fputcsv($baseHandle, $baseHeader, ';');

// Process rows
while (($row = fgetcsv($handle, 0, ';')) !== false) {
    // Data for AM_Desc.csv
    $descData = [
        $row[$skuIndex] ?? '',
        $row[$shortDescIndex] ?? '',
        $row[$descIndex] ?? ''
    ];
    fputcsv($descHandle, $descData, ';');

    // Data for AM_Base.csv
    $baseData = [];
    foreach ($header as $index => $colName) {
        if ($index == $shortDescIndex || $index == $descIndex) {
            continue;
        }
        $baseData[] = $row[$index] ?? '';
    }
    fputcsv($baseHandle, $baseData, ';');
}

fclose($handle);
fclose($descHandle);
fclose($baseHandle);

echo "Files created successfully: AM_Desc.csv and AM_Base.csv\n";
