<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Model\Order;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventorySalesApi\Model\GetSkuFromOrderItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentCommentCreationInterface;
use Magento\Sales\Api\Data\ShipmentCommentCreationInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsExtensionInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentItemCreationInterface;
use Magento\Sales\Api\Data\ShipmentItemCreationInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentTrackCreationInterface;
use Magento\Sales\Api\Data\ShipmentTrackCreationInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Exception\CouldNotShipException;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Validation\CanShip;
use RadWorks\QuickOrderShipment\Api\OrderShipmentBuilderInterface;
use RadWorks\QuickOrderShipment\Model\Inventory\SourceProviderInterface;

/**
 * Manages shipment creation
 */
class OrderShipmentBuilder implements OrderShipmentBuilderInterface
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
     * @var ShipmentCommentCreationInterfaceFactory $shipmentCommentCreationFactory
     */
    private ShipmentCommentCreationInterfaceFactory $shipmentCommentCreationFactory;

    /**
     * @var ShipmentItemCreationInterfaceFactory $shipmentItemCreationFactory
     */
    private ShipmentItemCreationInterfaceFactory $shipmentItemCreationFactory;

    /**
     * @var ShipmentTrackCreationInterfaceFactory $shipmentTrackCreationFactory
     */
    private ShipmentTrackCreationInterfaceFactory $shipmentTrackCreationFactory;

    /**
     * @var SourceProviderInterface $inventorySourceProvider
     */
    private SourceProviderInterface $inventorySourceProvider;

    /**
     * @var ShipmentCommentCreationInterface|null $commentCreation
     */
    private ?ShipmentCommentCreationInterface $commentCreation = null;

    /**
     * @var ShipmentTrackCreationInterface|null $trackCreation
     */
    private ?ShipmentTrackCreationInterface $trackCreation = null;

    private OrderInterface $order;

    /**
     * @var ShipmentItemCreationInterface[] $itemCreations
     */
    private array $itemCreations = [];

    /**
     * Skip deduction of shipment items from source
     *
     * @var bool
     */
    private bool $skipInventoryDeduction = false;

    /**
     * @param ResourceConnection $connection
     * @param CanShip $orderValidator
     * @param GetSkuFromOrderItemInterface $getSkuFromOrderItem
     * @param ShipOrderInterface $shipOrder
     * @param ShipmentCreationArgumentsExtensionInterfaceFactory $shipmentCreationArgumentsExtensionFactory
     * @param ShipmentCreationArgumentsInterfaceFactory $shipmentCreationArgumentsFactory
     * @param ShipmentCommentCreationInterfaceFactory $shipmentCommentCreationFactory
     * @param ShipmentItemCreationInterfaceFactory $shipmentItemCreationFactory
     * @param ShipmentTrackCreationInterfaceFactory $shipmentTrackCreationFactory
     * @param SourceProviderInterface $inventorySourceProvider
     */
    public function __construct(
        ResourceConnection                                 $connection,
        CanShip                                            $orderValidator,
        GetSkuFromOrderItemInterface                       $getSkuFromOrderItem,
        ShipOrderInterface                                 $shipOrder,
        ShipmentCreationArgumentsExtensionInterfaceFactory $shipmentCreationArgumentsExtensionFactory,
        ShipmentCreationArgumentsInterfaceFactory          $shipmentCreationArgumentsFactory,
        ShipmentCommentCreationInterfaceFactory            $shipmentCommentCreationFactory,
        ShipmentItemCreationInterfaceFactory               $shipmentItemCreationFactory,
        ShipmentTrackCreationInterfaceFactory              $shipmentTrackCreationFactory,
        SourceProviderInterface                            $inventorySourceProvider,
    )
    {
        $this->connection = $connection;
        $this->getSkuFromOrderItem = $getSkuFromOrderItem;
        $this->orderValidator = $orderValidator;
        $this->shipOrder = $shipOrder;
        $this->shipmentCreationArgumentsExtensionFactory = $shipmentCreationArgumentsExtensionFactory;
        $this->shipmentCreationArgumentsFactory = $shipmentCreationArgumentsFactory;
        $this->shipmentCommentCreationFactory = $shipmentCommentCreationFactory;
        $this->shipmentItemCreationFactory = $shipmentItemCreationFactory;
        $this->shipmentTrackCreationFactory = $shipmentTrackCreationFactory;
        $this->inventorySourceProvider = $inventorySourceProvider;
    }

    /**
     * Create shipments for order
     *
     * @param OrderInterface $order
     * @param array $quantities
     * @return OrderShipmentBuilder
     * @throws LocalizedException
     */
    public function build(OrderInterface $order, array $quantities = []): self
    {
        $this->reset();
        $this->order = $order;
        if ($messages = $this->orderValidator->validate($order)) {
            throw new LocalizedException(
                __('Order cannot be shipped: %1', implode(', ', $messages))
            );
        }

        $quantities = $this->inventorySourceProvider->get(
            $this->getOrderItemsToShip($order),
            $quantities,
            $this->skipInventoryDeduction
        );

        ;
        if (!$this->createShipmentItemCreations($quantities)) {
            throw new LocalizedException(__(('No items to ship.')));
        }

        return $this;
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
     * Create comment creation model for the shipment
     *
     * @param string $comment
     * @return OrderShipmentBuilder
     */
    public function addComment(string $comment): self
    {
        if ($comment) {
            $this->commentCreation = $this->shipmentCommentCreationFactory->create();
            $this->commentCreation
                ->setComment($comment)
                ->setIsVisibleOnFront(false);
        }

        return $this;
    }

    /**
     * Builds track creation model for the shipment
     *
     * @param array $track
     * @return self
     */
    public function addTrack(array $track): self
    {
        if ($track) {
            /** @var ShipmentTrackCreationInterface $trackCreation */
            $this->trackCreation = $this->shipmentTrackCreationFactory->create();
            $this->trackCreation
                ->setTrackNumber($track[ShipmentTrackInterface::TRACK_NUMBER])
                ->setTitle($track[ShipmentTrackInterface::TITLE])
                ->setCarrierCode($track[ShipmentTrackInterface::CARRIER_CODE]);
        }

        return $this;
    }

    /**
     * Create shipments creations models for an order
     *
     * @param array $inventorySources
     * @return array
     */
    private function createShipmentItemCreations(array $inventorySources): array
    {
        foreach ($inventorySources as $sourceCode => $sources) {
            foreach ($sources as $source) {
                /** @var ShipmentItemCreationInterface $shipmentItemCreation */
                $this->itemCreations[$sourceCode][] = $this->shipmentItemCreationFactory->create()
                    ->setOrderItemId($source[SourceProviderInterface::DATA_FIELD_ITEM_ID])
                    ->setQty($source[SourceProviderInterface::DATA_FIELD_QTY]);
            }
        }

        return $this->itemCreations;
    }

    /**
     * Reset build configuration
     *
     * @return OrderShipmentBuilder
     */
    private function reset(): self
    {
        $this->itemCreations = [];
        $this->trackCreation = null;
        $this->commentCreation = null;

        return $this;
    }

    /**
     * Create shipments for an order
     *
     * @return void
     * @throws CouldNotShipException
     */
    public function save(): void
    {
        $connection = $this->connection->getConnection('sales');
        $connection->beginTransaction();
        try {
            /** @var ShipmentItemCreationInterface[] $items */
            foreach ($this->itemCreations as $sourceCode => $items) {
                $shipmentCreationArguments = $this->shipmentCreationArgumentsFactory->create();
                $shipmentCreationArguments->setExtensionAttributes(
                    $this->shipmentCreationArgumentsExtensionFactory->create()
                        ->setSourceCode($sourceCode)
                        ->setSkipItemsDeduction($this->skipInventoryDeduction)
                );
                $this->shipOrder->execute(
                    $this->order->getEntityId(),
                    $items,
                    comment: $this->commentCreation ?: null,
                    tracks: $this->trackCreation ? [$this->trackCreation] : [],
                    arguments: $shipmentCreationArguments
                );
            }

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw new CouldNotShipException(__('Could not save a shipment, see error log for details'), $e);
        }
    }

    /**
     * Set flag to skip validation
     *
     * @param bool $skipInventoryDeduction
     * @return OrderShipmentBuilder
     */
    public function skipInventoryDeduction(bool $skipInventoryDeduction): self
    {
        $this->skipInventoryDeduction = $skipInventoryDeduction;

        return $this;
    }
}