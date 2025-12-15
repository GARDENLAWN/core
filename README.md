# GardenLawn Core Module

This module provides core functionalities for the GardenLawn Magento 2 project.

## Console Commands

This module includes several console commands to perform various tasks.

### Sync Static Assets to S3

This command synchronizes static assets with the configured S3 bucket. It is useful when you need to manually push static files to the remote storage.

**Usage:**
```bash
bin/magento gardenlawn:s3:sync-static
```

*(Note: The command name in `di.xml` is `gardenlawn_s3_sync_static`, but it's likely invoked via a different name defined in the command class itself. Assuming a standard naming convention.)*

### Sync Dealer Prices

This command synchronizes the `dealer_price` (in EUR) attribute with the native Tier Price functionality for configured B2B customer groups. It converts the price from EUR to the store's base currency (PLN).

**Usage:**
```bash
bin/magento gardenlawn:dealer:sync-prices
```

**Workflow:**
1.  Run the command to calculate and save tier prices.
2.  After the command finishes, reindex the price indexer to apply the changes on the frontend.
    ```bash
    bin/magento indexer:reindex catalog_product_price
    ```

This command should be run periodically (e.g., via a cron job) to keep dealer prices up-to-date with currency rate changes or after bulk updates to the `dealer_price` attribute.
