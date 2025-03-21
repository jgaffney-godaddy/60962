<?php

namespace GoDaddy\WordPress\MWC\Dashboard\Shipping\DataStores\ShipmentTracking;

use Exception;
use GoDaddy\WordPress\MWC\Common\Helpers\ArrayHelper;
use GoDaddy\WordPress\MWC\Common\Repositories\WooCommerce\OrdersRepository;
use GoDaddy\WordPress\MWC\Dashboard\Shipping\DataSources\ShipmentTracking\Adapters\ShipmentAdapter;
use GoDaddy\WordPress\MWC\Dashboard\Shipping\DataStores\OrderFulfillmentDataStore as WoocommerceOrderFulfillmentDataStore;
use GoDaddy\WordPress\MWC\Dashboard\Shipping\DataStores\Traits\CanManipulateOrderMetaDataTrait;
use GoDaddy\WordPress\MWC\Shipping\Contracts\ShipmentContract;
use GoDaddy\WordPress\MWC\Shipping\Models\Orders\OrderFulfillment;
use GoDaddy\WordPress\MWC\Shipping\Models\Packages\Package;
use GoDaddy\WordPress\MWC\Shipping\Models\Shipment;

/**
 * Order fulfillment data store.
 */
class OrderFulfillmentDataStore
{
    use CanManipulateOrderMetaDataTrait;

    /** @var WoocommerceOrderFulfillmentDataStore */
    protected $datastore;

    /** @var string Shipment tracking items meta key */
    protected $tracking_items_meta_key = '_wc_shipment_tracking_items';

    /** @var string Shipment tracking items hash meta key */
    protected $tracking_items_hash_meta_key = '_mwc_shipment_tracking_items_hash';

    public function __construct(?WoocommerceOrderFulfillmentDataStore $datastore = null)
    {
        $this->datastore = $datastore ?? new WoocommerceOrderFulfillmentDataStore();
    }

    /**
     * Gets the Shipment Tracking plugin data.
     *
     * @param int|null $orderId
     * @return OrderFulfillment|null
     * @throws Exception
     */
    public function read(?int $orderId = null) : ?OrderFulfillment
    {
        $orderFulfillment = $this->datastore->read($orderId);

        if (null === $orderFulfillment) {
            return null;
        }

        $pluginData = $this->getPluginData($orderId);

        if ($this->hasNewPluginData($orderId, $pluginData)) {
            $shipments = $this->getShipmentsFromPluginData($pluginData);

            $orderFulfillment = $this->addOrUpdateShipments($orderFulfillment, $shipments);
            $orderFulfillment = $this->removeOrphanShipments($orderFulfillment, $shipments);

            $this->savePluginData($orderId, $pluginData);
            $this->datastore->save($orderFulfillment);
        }

        return $orderFulfillment;
    }

    /**
     * Determines whether there is new Shipment Tracking plugin data to process.
     *
     * @param int $orderId
     * @param array $data
     * @return bool
     * @throws Exception
     */
    protected function hasNewPluginData(int $orderId, array $data) : bool
    {
        if (empty($data)) {
            return false;
        }

        return $this->getPluginDataHash($data) !== $this->getStoredPluginDataHash($orderId);
    }

    /**
     * Gets data from the shipment tracking plugin.
     *
     * @param int $orderId
     * @return array
     * @throws Exception
     */
    protected function getPluginData(int $orderId) : array
    {
        $wcOrder = OrdersRepository::get($orderId);

        if (! $wcOrder) {
            return [];
        }

        $data = $wcOrder->get_meta($this->tracking_items_meta_key);

        if (empty($data)) {
            return [];
        }

        return $data;
    }

    /**
     * Gets the stored MD5 hash of the data from the shipment tracking plugin.
     *
     * @param int $orderId
     * @return string
     * @throws Exception
     */
    protected function getStoredPluginDataHash(int $orderId) : string
    {
        $wcOrder = OrdersRepository::get($orderId);

        if (! $wcOrder) {
            return '';
        }

        $data = $wcOrder->get_meta($this->tracking_items_hash_meta_key);

        if (empty($data)) {
            return '';
        }

        return $data;
    }

    /**
     * Calculates the MD5 hash of the provided plugin data.
     *
     * @param array $data
     * @return string
     */
    protected function getPluginDataHash(array $data) : string
    {
        return md5(json_encode($data) ?: '');
    }

    /**
     * Converts data from Shipment Tracking plugin into ShipmentContract objects.
     *
     * @param array $data
     * @return ShipmentContract[]
     * @throws Exception
     */
    protected function getShipmentsFromPluginData(array $data)
    {
        return array_map(function ($shipmentData) {
            return $this->getAdapter($shipmentData)->convertFromSource();
        }, $data);
    }

    /**
     * Adds or updates shipments in an order fulfillment.
     *
     * Uses a control list of {@see Shipment} objects:
     * If a shipment is inside the {@see OrderFulfillment} object, it updates the shipment in the object.
     * Otherwise, add the shipment in the object.
     *
     * @param OrderFulfillment $fulfillment
     * @param ShipmentContract[] $shipments
     * @return OrderFulfillment updated order fulfillment
     */
    protected function addOrUpdateShipments(OrderFulfillment $fulfillment, array $shipments) : OrderFulfillment
    {
        foreach ($shipments as $shipmentToAddOrUpdate) {
            // we can assume that two shipment objects represent the same shipment if they have the same ID
            if ($existingShipment = $fulfillment->getShipment($shipmentToAddOrUpdate->getId())) {
                $this->updateShipment($existingShipment, $shipmentToAddOrUpdate);
            } else {
                $fulfillment->addShipment($shipmentToAddOrUpdate);
            }
        }

        return $fulfillment;
    }

    /**
     * Removes orphaned shipments from the order fulfillment.
     *
     * If a shipment in a {@see OrderFulfillment} isn't present in a control list of {@see Shipment} to compare, it removes the shipment.
     *
     * @param OrderFulfillment $fulfillment
     * @param ShipmentContract[] $shipments
     * @return OrderFulfillment updated order fulfillment
     */
    protected function removeOrphanShipments(OrderFulfillment $fulfillment, array $shipments) : OrderFulfillment
    {
        $shipmentIds = array_map(static function ($shipment) {
            return $shipment->getId();
        }, $shipments);

        foreach ($fulfillment->getShipments() as $shipment) {
            $this->maybeRemoveOrphanShipment($fulfillment, $shipment, $shipmentIds);
        }

        return $fulfillment;
    }

    /**
     * Removes an orphaned shipment from the order fulfillment.
     *
     * @param OrderFulfillment $orderFulfillment
     * @param ShipmentContract $shipment
     * @param string[] $shipmentIds
     * @return void
     */
    protected function maybeRemoveOrphanShipment(OrderFulfillment $orderFulfillment, ShipmentContract $shipment, array $shipmentIds) : void
    {
        if ($this->isShipmentProviderDifferentFromCarrier($shipment)) {
            return;
        }

        // we can assume that two shipment objects represent the same shipment if they have the same ID
        if (in_array($shipment->getId(), $shipmentIds, false)) {
            return;
        }

        $orderFulfillment->removeShipment($shipment);
    }

    /**
     * Updates select properties in a {@see Shipment} object with values from another {@see Shipment} object.
     *
     * @param ShipmentContract $oldShipment
     * @param ShipmentContract $newShipment
     * @return ShipmentContract updated shipment
     */
    protected function updateShipment(ShipmentContract $oldShipment, ShipmentContract $newShipment) : ShipmentContract
    {
        if ($this->isShipmentProviderDifferentFromCarrier($oldShipment)) {
            return $oldShipment;
        }

        $oldShipment->setProviderName($newShipment->getProviderName() ?? $oldShipment->getProviderName());
        $oldShipment->setProviderLabel($newShipment->getProviderLabel() ?? $oldShipment->getProviderLabel());
        $oldShipment->setCreatedAt($newShipment->getCreatedAt() ?? $oldShipment->getCreatedAt());

        /** @var Package $oldPackage */
        $oldPackage = current($oldShipment->getPackages());
        /** @var Package $newPackage */
        $newPackage = current($newShipment->getPackages());

        if ($oldPackage && $newPackage) {
            $oldShipment->removePackage($oldPackage);
            $oldPackage->setTrackingNumber($newPackage->getTrackingNumber());
            $oldPackage->setTrackingUrl($newPackage->getTrackingUrl());
            $oldShipment->addPackage($oldPackage);
        }

        return $oldShipment;
    }

    /**
     * Determines if given shipment's provider different from its carrier.
     *
     * When the carrier name and the provider name are different, the shipment was likely created through
     * a different integration. In those cases we shouldn't modify the shipment or remove its information.
     *
     * @param ShipmentContract $shipment
     * @return bool
     */
    protected function isShipmentProviderDifferentFromCarrier(ShipmentContract $shipment) : bool
    {
        return $shipment->getCarrier()->getName() !== $shipment->getProviderName();
    }

    /**
     * Saves the shipment tracking plugin data.
     *
     * @param OrderFulfillment|null $fulfillment
     * @throws Exception
     */
    public function save(?OrderFulfillment $fulfillment = null) : void
    {
        if (null === $fulfillment) {
            return;
        }

        $orderId = $fulfillment->getOrder()->getId();
        $pluginData = $this->getPluginData($orderId);

        if ($this->hasNewPluginData($orderId, $pluginData)) {
            $this->addMissingShipments($fulfillment, $this->getShipmentsFromPluginData($pluginData));
        }

        $this->savePluginData($orderId, $this->getPluginDataFromShipments($fulfillment->getShipments()));
        $this->datastore->save($fulfillment);
    }

    /**
     * Adds any shipments in the given array that were missing in the OrderFulfillment object.
     *
     * @since x.y.z
     *
     * @param OrderFulfillment $fulfillment
     * @param ShipmentContract[] $shipments
     */
    protected function addMissingShipments(OrderFulfillment $fulfillment, array $shipments)
    {
        if (empty($shipments)) {
            return;
        }

        foreach ($shipments as $shipment) {
            if ($fulfillment->hasShipment($shipment)) {
                continue;
            }

            $fulfillment->addShipment($shipment);
        }
    }

    /**
     * Convert each shipment object into shipment data.
     *
     * @since x.y.z
     *
     * @param ShipmentContract[] $shipments
     *
     * @return array
     */
    protected function getPluginDataFromShipments(array $shipments) : array
    {
        $adapter = $this->getAdapter([]);

        return array_values(array_map(static function ($shipment) use ($adapter) {
            return $adapter->convertToSource($shipment);
        }, $shipments));
    }

    /**
     * Updates the order meta key _wc_shipment_tracking_items using the given array.
     *
     * @param int $orderId
     * @param array $data
     * @throws Exception
     */
    protected function savePluginData(int $orderId, array $data)
    {
        $wcOrder = OrdersRepository::get($orderId);

        if ($wcOrder) {
            $wcOrder->update_meta_data($this->tracking_items_meta_key, $data);
            $wcOrder->update_meta_data($this->tracking_items_hash_meta_key, $this->getPluginDataHash($data));
            // TODO: replace this call to WC_Order::save() with a call to WC_Order::save_meta_data() in https://godaddy-corp.atlassian.net/browse/MWC-13394
            $wcOrder->save();
        }
    }

    /**
     * Deletes the shipment tracking data.
     *
     * @param OrderFulfillment|null $fulfillment
     * @throws Exception
     */
    public function delete(?OrderFulfillment $fulfillment = null) : bool
    {
        if (empty($fulfillment)) {
            return false;
        }

        $orderId = (int) $fulfillment->getOrder()->getId();

        if (! $this->hasNewPluginData($orderId, $this->getPluginData($orderId))) {
            $this->deleteOrderMetaData($orderId, [$this->tracking_items_hash_meta_key, $this->tracking_items_meta_key]);
        }

        return $this->datastore->delete($fulfillment);
    }

    /**
     * Gets a new instance of the ShipmentAdapter.
     *
     * @param mixed $data
     */
    protected function getAdapter($data) : ShipmentAdapter
    {
        return new ShipmentAdapter(ArrayHelper::wrap($data));
    }
}
