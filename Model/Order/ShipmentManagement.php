<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Model\Order;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventorySalesApi\Model\GetSkuFromOrderItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsExtensionInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentItemCreationInterface;
use Magento\Sales\Api\Data\ShipmentItemCreationInterfaceFactory;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Exception\CouldNotShipException;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Validation\CanShip;
use RadWorks\QuickOrderShipment\Model\Inventory\SourceProviderInterface;

/**
 * Manages shipment creation
 */
class ShipmentManagement implements ShipmentManagementInterface
{
    /**
     * @var ResourceConnection $connection
     */
    private ResourceConnection $connection;

    /**
     * @var GetSkuFromOrderItemInterface $getSkuFromOrderItem
     */
    private GetSkuFromOrderItemInterface $getSkuFromOrderItem;

    /**
     * @var CanShip $orderValidator
     */
    private CanShip $orderValidator;

    /**
     * @var ShipOrderInterface $shipOrder
     */
    private ShipOrderInterface $shipOrder;

    /**
     * @var ShipmentCreationArgumentsExtensionInterfaceFactory $shipmentCreationArgumentsExtensionFactory
     */
    private ShipmentCreationArgumentsExtensionInterfaceFactory $shipmentCreationArgumentsExtensionFactory;

    /**
     * @var ShipmentCreationArgumentsInterfaceFactory $shipmentCreationArgumentsFactory
     */
    private ShipmentCreationArgumentsInterfaceFactory $shipmentCreationArgumentsFactory;

    /**
     * @var ShipmentItemCreationInterfaceFactory $shipmentItemCreationFactory
     */
    private ShipmentItemCreationInterfaceFactory $shipmentItemCreationFactory;

    /**
     * @var SourceProviderInterface $inventorySourceProvider
     */
    private SourceProviderInterface $inventorySourceProvider;

    /**
     * Skip deduction of shipment items from source
     *
     * @var bool
     */
    private bool $skipInventoryCheck = false;

    /**
     * @param ResourceConnection $connection
     * @param CanShip $orderValidator
     * @param GetSkuFromOrderItemInterface $getSkuFromOrderItem
     * @param ShipOrderInterface $shipOrder
     * @param ShipmentCreationArgumentsExtensionInterfaceFactory $shipmentCreationArgumentsExtensionFactory
     * @param ShipmentCreationArgumentsInterfaceFactory $shipmentCreationArgumentsFactory
     * @param ShipmentItemCreationInterfaceFactory $shipmentItemCreationFactory
     * @param SourceProviderInterface $inventorySourceProvider
     */
    public function __construct(
        ResourceConnection                                 $connection,
        CanShip                                            $orderValidator,
        GetSkuFromOrderItemInterface                       $getSkuFromOrderItem,
        ShipOrderInterface                                 $shipOrder,
        ShipmentCreationArgumentsExtensionInterfaceFactory $shipmentCreationArgumentsExtensionFactory,
        ShipmentCreationArgumentsInterfaceFactory          $shipmentCreationArgumentsFactory,
        ShipmentItemCreationInterfaceFactory               $shipmentItemCreationFactory,
        SourceProviderInterface                            $inventorySourceProvider,
    )
    {
        $this->connection = $connection;
        $this->getSkuFromOrderItem = $getSkuFromOrderItem;
        $this->orderValidator = $orderValidator;
        $this->shipOrder = $shipOrder;
        $this->shipmentCreationArgumentsExtensionFactory = $shipmentCreationArgumentsExtensionFactory;
        $this->shipmentCreationArgumentsFactory = $shipmentCreationArgumentsFactory;
        $this->shipmentItemCreationFactory = $shipmentItemCreationFactory;
        $this->inventorySourceProvider = $inventorySourceProvider;
    }

    /**
     * Create shipments for order
     *
     * @param OrderInterface $order
     * @param array $constraints
     * @param bool $skipInventoryCheck
     * @return OrderInterface
     * @throws LocalizedException
     */
    public function shipOrder(OrderInterface $order, array $constraints = [], bool $skipInventoryCheck = true): OrderInterface
    {
        $this->skipInventoryCheck = $skipInventoryCheck;
        if ($messages = $this->orderValidator->validate($order)) {
            throw new LocalizedException(
                __('Order cannot be shipped: %1', implode(', ', $messages))
            );
        }

        $shipmentItemCreations = $this->createShipmentItemCreations(
            $this->inventorySourceProvider->get(
                $this->getOrderItemsToShip($order),
                $constraints,
                $this->skipInventoryCheck
            )
        );

        if (!$shipmentItemCreations) {
            throw new LocalizedException(__(('No items to ship.')));
        }

        $this->saveShipmentItemCreations((int)$order->getEntityId(), $shipmentItemCreations);

        return $order;
    }

    /**
     * Get order items to ship
     *
     * @param OrderInterface $order
     * @return Item[]
     */
    private function getOrderItemsToShip(OrderInterface $order): array
    {
        $result = [];
        foreach ($order->getAllItems() as $orderItem) {
            if ($orderItem->getIsVirtual() || $orderItem->getLockedDoShip()) {
                continue;
            }

            if ($orderItem->canShip()) {
                $result[] = $orderItem->isDummy(true) ? $orderItem->getParentItem() : $orderItem;
            }
        }

        return $result;
    }

    /**
     * Create shipments creations models for an order
     *
     * @param array $inventorySources
     * @return array
     */
    private function createShipmentItemCreations(array $inventorySources): array
    {
        $result = [];
        foreach ($inventorySources as $sourceCode => $sources) {
            foreach ($sources as $source) {
                /** @var ShipmentItemCreationInterface $shipmentItemCreation */
                $result[$sourceCode][] = $this->shipmentItemCreationFactory->create()
                    ->setOrderItemId($source[SourceProviderInterface::DATA_FIELD_ITEM_ID])
                    ->setQty($source[SourceProviderInterface::DATA_FIELD_QTY]);
            }
        }

        return $result;
    }

    /**
     * Create shipments for an order
     *
     * @param int $orderId
     * @param array $shipmentItemCreations
     * @return void
     * @throws CouldNotShipException
     */
    private function saveShipmentItemCreations(int $orderId, array $shipmentItemCreations): void
    {
        $connection = $this->connection->getConnection('sales');
        $connection->beginTransaction();
        try {
            /** @var ShipmentItemCreationInterface[] $items */
            foreach ($shipmentItemCreations as $sourceCode => $items) {
                $shipmentCreationArguments = $this->shipmentCreationArgumentsFactory->create();
                $shipmentCreationArguments->setExtensionAttributes(
                    $this->shipmentCreationArgumentsExtensionFactory->create()
                        ->setSourceCode($sourceCode)
                        ->setSkipItemsDeduction($this->skipInventoryCheck)
                );
                $this->shipOrder->execute($orderId, $items, arguments: $shipmentCreationArguments);
            }

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw new CouldNotShipException(__('Could not save a shipment, see error log for details'));
        }
    }
}