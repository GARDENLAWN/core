# GardenLawn Core Module Documentation

This document provides an overview of the GardenLawn Core module.

## Directory Structure

- **Api**: Interfaces for the module.
- **Block**: Block classes.
- **Configs**: Configuration related files.
- **Console**: Console commands.
- **Controller**: Controller actions.
- **Cron**: Cron jobs.
- **Helper**: Helper classes.
- **Model**: Models and resource models.
- **Observer**: Event observers.
- **Plugin**: Plugins (Interceptors).
- **Scripts**: Utility scripts.
- **Setup**: Database setup scripts.
- **Utils**: Utility classes.
- **ViewModel**: View models.
- **etc**: Configuration files.
- **files**: Miscellaneous files.
- **view**: Layouts and templates.

## Overview

This module serves as the core foundation for the GardenLawn project, providing shared functionality, utilities, and base configurations.

---

# GardenLawn Core Module (Console Commands)

This module provides core functionalities for the GardenLawn Magento 2 project.

## Console Commands

This document lists all custom console commands available in the `GardenLawn` modules.

---

### Core Module (`GardenLawn/Core`)

#### Sync Static Assets to S3
Synchronizes static assets for specific themes with the configured S3 bucket.

**Usage:**
```bash
bin/magento gardenlawn:s3:sync-static <theme>...
```
**Arguments:**
- `theme`: (Required, array) The theme(s) to synchronize (e.g., `Magento/luma` `GardenLawn/theme`).

#### Sync Dealer Prices
Synchronizes the `dealer_price` (EUR) attribute with native Tier Prices for B2B customer groups, converting the price to the store's base currency (PLN). After running, reindex the price indexer.

**Usage:**
```bash
bin/magento gardenlawn:dealer:sync-prices
bin/magento indexer:reindex catalog_product_price
```
This command has no arguments or options.

---

### Company Module (`GardenLawn/Company`)

#### Import Dealers
Imports dealer data from JSON files (`stihl.json`, `husqvarna.json`) located in `GardenLawn/Core/Configs/`. It updates existing records based on name and customer group or creates new ones.

**Usage:**
```bash
bin/magento gardenlawn:import:dealers
```
This command has no arguments or options.

---

### MediaGallery Module (`GardenLawn/MediaGallery`)

#### Sync S3 Assets
Synchronizes AWS S3 assets with the `media_gallery_asset` table.

**Usage:**
```bash
bin/magento gardenlawn:mediagallery:sync-s3 [options]
```
**Options:**
- `--dry-run`: Do not modify the database, only show what would be done.
- `--with-delete`: Enable deletion of database assets that are no longer in S3.
- `--force-update`: Force update of existing assets if hash/width/height is missing or hash has changed.

#### Populate Asset Links
Creates galleries from folder paths, links assets, and optionally prunes old galleries.

**Usage:**
```bash
bin/magento gardenlawn:mediagallery:populate-all [options]
```
**Options:**
- `--dry-run`: Do not modify the database, only show what would be done.
- `--with-prune`: Enable pruning of galleries that no longer have corresponding asset paths.

#### Convert Images to WebP
Converts images in the media gallery to the WebP format, creates thumbnails, and cleans up legacy files.

**Usage:**
```bash
bin/magento gardenlawn:gallery:convert-to-webp [options]
```
**Options:**
- `-f`, `--force`: Force regeneration of existing WebP files and thumbnails, and refreshes metadata for original WebP files.

#### Deduplicate Assets
Finds and removes duplicate assets (files with the same path) from the `media_gallery_asset` table.

**Usage:**
```bash
bin/magento gardenlawn:mediagallery:deduplicate-assets [options]
```
**Options:**
- `--dry-run`: Do not modify the database, only show what would be done.
```
