<?php

namespace GoDaddy\WordPress\MWC\Core\Features\Commerce\Catalog\Interceptors\Handlers;

use GoDaddy\WordPress\MWC\Common\Interceptors\Handlers\AbstractInterceptorHandler;
use GoDaddy\WordPress\MWC\Core\Features\Commerce\Catalog\CatalogIntegration;
use GoDaddy\WordPress\MWC\Core\Features\Commerce\Commerce;
use GoDaddy\WordPress\MWC\Core\Features\Commerce\Inventory\InventoryIntegration;

/**
 * Handles the WooCommerce product import started.
 *
 * This handler disables the integrations when the product import is started. If the integration is enabled while the
 * import is running, the platform can receive partial data, which can lead to inconsistencies.
 * {@see WooProductImportDoneHandler} will run the backfill job to send the imported products to the platform after the
 * import is completed. Also {@see WooProductImportInterceptor} for the integration.
 */
class WooProductImportStartedHandler extends AbstractInterceptorHandler
{
    /**
     * {@inheritDoc}
     */
    public function run(...$args)
    {
        if (CatalogIntegration::hasCommerceCapability(Commerce::CAPABILITY_WRITE)) {
            CatalogIntegration::disableCapability(Commerce::CAPABILITY_WRITE);
        }

        if (InventoryIntegration::hasCommerceCapability(Commerce::CAPABILITY_WRITE)) {
            InventoryIntegration::disableCapability(Commerce::CAPABILITY_WRITE);
        }
    }
}
