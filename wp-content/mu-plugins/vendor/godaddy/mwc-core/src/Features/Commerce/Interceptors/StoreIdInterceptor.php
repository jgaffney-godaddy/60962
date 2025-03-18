<?php

namespace GoDaddy\WordPress\MWC\Core\Features\Commerce\Interceptors;

use Exception;
use GoDaddy\WordPress\MWC\Common\Configuration\Configuration;
use GoDaddy\WordPress\MWC\Common\Exceptions\SentryException;
use GoDaddy\WordPress\MWC\Common\Helpers\TypeHelper;
use GoDaddy\WordPress\MWC\Common\HostingPlans\Enums\HostingPlanNamesEnum;
use GoDaddy\WordPress\MWC\Common\Interceptors\AbstractInterceptor;
use GoDaddy\WordPress\MWC\Common\Platforms\Contracts\PlatformRepositoryContract;
use GoDaddy\WordPress\MWC\Common\Register\Register;
use GoDaddy\WordPress\MWC\Common\Repositories\WordPressRepository;
use GoDaddy\WordPress\MWC\Core\Features\Commerce\Commerce;
use GoDaddy\WordPress\MWC\Core\Traits\CanCheckIfOnboardingHasInitializedTrait;

/**
 * Interceptor to handle the site (default) store ID.
 */
class StoreIdInterceptor extends AbstractInterceptor
{
    use CanCheckIfOnboardingHasInitializedTrait;

    protected PlatformRepositoryContract $platformRepository;

    public function __construct(PlatformRepositoryContract $platformRepository)
    {
        $this->platformRepository = $platformRepository;
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function addHooks() : void
    {
        Register::action()
            ->setGroup('admin_init')
            ->setHandler([$this, 'maybeSetDefaultStoreId'])
            ->execute();
    }

    /**
     * Maybe sets the default store ID.
     *
     * @internal
     *
     * @return void
     */
    public function maybeSetDefaultStoreId() : void
    {
        if ($this->shouldDetermineDefaultStoreId()) {
            $this->setDefaultStoreId();
        }
    }

    /**
     * Determines if we should set the default store ID.
     *
     * @return bool
     */
    protected function shouldDetermineDefaultStoreId() : bool
    {
        try {
            return $this->shouldDetermineDefaultStoreIdForCurrentPlan()
                && ! WordPressRepository::isAjax()
                && ! $this->hasOnboardingInitialized()
                && empty(Commerce::getStoreId());
        } catch (Exception $e) {
            // catch all exceptions in a hook callback
            return false;
        }
    }

    /**
     * Determines whether the capability to detect the default Store ID is enabled for the current plan.
     */
    protected function shouldDetermineDefaultStoreIdForCurrentPlan() : bool
    {
        if ($this->isManagedWooCommerceStoresPlan()) {
            return true;
        }

        return TypeHelper::bool(Configuration::get('godaddy.store.shouldDetermineDefaultSiteId'), false);
    }

    /**
     * Determines if current plan is a Managed WooCommerce Stores (MWCS) plan.
     */
    protected function isManagedWooCommerceStoresPlan() : bool
    {
        return HostingPlanNamesEnum::isManagedWooCommerceStoresPlan($this->platformRepository->getPlan()->getName());
    }

    /**
     * Sets the default store ID.
     *
     * @return void
     */
    protected function setDefaultStoreId() : void
    {
        try {
            $storeRepository = $this->platformRepository->getStoreRepository();
            $defaultStoreId = $storeRepository->determineDefaultStoreId();

            if (empty($defaultStoreId)) {
                return;
            }

            $storeRepository->setDefaultStoreId($defaultStoreId);
        } catch (Exception $exception) {
            new SentryException('Could not set the default store ID.', $exception);
        }
    }
}
