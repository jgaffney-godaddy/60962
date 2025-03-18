<?php

namespace GoDaddy\WordPress\MWC\Core\Features\Commerce\Catalog\Interceptors\Handlers;

use DateTime;
use Exception;
use GoDaddy\WordPress\MWC\Common\Exceptions\SentryException;
use GoDaddy\WordPress\MWC\Common\Helpers\ArrayHelper;
use GoDaddy\WordPress\MWC\Common\Interceptors\Handlers\AbstractInterceptorHandler;
use GoDaddy\WordPress\MWC\Common\Schedule\Schedule;
use GoDaddy\WordPress\MWC\Core\Features\Commerce\Backfill\Interceptors\InitiateBackfillInterceptor;

/**
 * Handles the WooCommerce product import done.
 *
 * This handler re-enables the integrations and schedules a single backfill job to send the
 * imported products to the platform. Also {@see WooProductImportInterceptor} for the integration.
 */
class WooProductImportDoneHandler extends AbstractInterceptorHandler
{
    /**
     * {@inheritDoc}
     */
    public function run(...$args)
    {
        if (! $this->shouldHandle()) {
            return;
        }

        $this->scheduleBackfillJob();
    }

    /**
     * The import is "done" when we are on the "Import Complete!" page.
     *
     * Unfortunately, the WC import process does not have a proper action to hook too, so the most reliable way to
     * detect the import is done is to check the URL.
     *
     * @return bool
     */
    protected function shouldHandle() : bool
    {
        return ArrayHelper::get($_GET, 'page', '') === 'product_importer'
            && ArrayHelper::get($_GET, 'step', '') === 'done';
    }

    /**
     * Schedule a single backfill job to send the imported products to the platform.
     *
     * @return void
     */
    protected function scheduleBackfillJob() : void
    {
        try {
            Schedule::singleAction()
                ->setName(InitiateBackfillInterceptor::BACKFILL_JOB_NAME)
                ->setScheduleAt(new DateTime())
                // Set the priority to 15 to ensure it runs after default priority jobs (priority = 10)
                // and before WebhookRequestHandler jobs (priority = 20).
                ->setPriority(15)
                ->schedule();
        } catch (Exception $e) {
            SentryException::getNewInstance('Could not schedule backfill job', $e);
        }
    }
}
