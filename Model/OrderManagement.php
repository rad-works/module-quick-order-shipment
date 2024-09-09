<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsExtensionInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentItemCreationInterface;
use Magento\Sales\Api\Data\ShipmentItemCreationInterfaceFactory;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\Order\Validation\CanShip;
use RadWorks\QuickOrderShipment\Model\Inventory\SourceProviderInterface;

class OrderManagement implements OrderManagementInterface
{
    /**
     * @var ResourceConnection $connection
     */
    private ResourceConnection $connection;

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
     * @var CanShip $validator
     */
    private CanShip $validator;

    /**
     * @param ResourceConnection $connection
     * @param ShipOrderInterface $shipOrder
     * @param ShipmentCreationArgumentsExtensionInterfaceFactory $shipmentCreationArgumentsExtensionFactory
     * @param ShipmentCreationArgumentsInterfaceFactory $shipmentCreationArgumentsFactory
     * @param ShipmentItemCreationInterfaceFactory $shipmentItemCreationFactory
     * @param SourceProviderInterface $inventorySourceProvider
     * @param CanShip $validator
     */
    public function __construct(
        ResourceConnection                                 $connection,
        ShipOrderInterface                                 $shipOrder,
        ShipmentCreationArgumentsExtensionInterfaceFactory $shipmentCreationArgumentsExtensionFactory,
        ShipmentCreationArgumentsInterfaceFactory          $shipmentCreationArgumentsFactory,
        ShipmentItemCreationInterfaceFactory               $shipmentItemCreationFactory,
        SourceProviderInterface                            $inventorySourceProvider,
        CanShip                                            $validator,
    )
    {
        $this->connection = $connection;
        $this->shipOrder = $shipOrder;
        $this->shipmentCreationArgumentsExtensionFactory = $shipmentCreationArgumentsExtensionFactory;
        $this->shipmentCreationArgumentsFactory = $shipmentCreationArgumentsFactory;
        $this->shipmentItemCreationFactory = $shipmentItemCreationFactory;
        $this->inventorySourceProvider = $inventorySourceProvider;
        $this->validator = $validator;
    }

    /**
     * Force ship order
     *
     * @param OrderInterface $order
     * @return OrderInterface
     * @throws LocalizedException
     */
    public function forceShip(OrderInterface $order): OrderInterface
    {
        if ($messages = $this->validator->validate($order)) {
            throw new LocalizedException(__('Order cannot be force shipped: %1', implode(', ', $messages)));
        }

        /** @var ShipmentItemCreationInterface[] $shipmentItemsCreations */
        $shipmentItemsCreations = [];
        foreach ($this->inventorySourceProvider->get($order, forceDefaultSource: true) as $sourceCode => $sources) {
            foreach ($sources as $source) {
                $shipmentItemsCreations[$sourceCode][] = $this->shipmentItemCreationFactory->create()
                    ->setOrderItemId($source['order_item_id'])
                    ->setQty($source['qtyToDeduct']);
            }
        }

        $connection = $this->connection->getConnection('sales');
        $connection->beginTransaction();
        try {
            foreach ($shipmentItemsCreations as $sourceCode => $items) {
                $shipmentCreationArguments = $this->shipmentCreationArgumentsFactory->create();
                $shipmentCreationArguments->setExtensionAttributes(
                    $this->shipmentCreationArgumentsExtensionFactory->create()->setSourceCode($sourceCode)
                );
                $this->shipOrder->execute($order->getEntityId(), $items, arguments: $shipmentCreationArguments);
            }
            throw new LocalizedException(__('Don\'t forget to send shipment items!'));
        } catch (\Throwable) {
            $connection->rollBack();
            throw new LocalizedException(__('Shipment was not created.'));
        }

        $connection->commit();

        return $order;
    }
}