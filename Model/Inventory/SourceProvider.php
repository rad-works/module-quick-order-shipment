<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Model\Inventory;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventorySalesApi\Model\GetSkuFromOrderItemInterface;
use Magento\InventoryShippingAdminUi\Ui\DataProvider\GetSourcesByOrderIdSkuAndQty;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Provides order items' inventory source selections by order
 */
class SourceProvider implements SourceProviderInterface
{
    /**
     * @var DefaultSourceProviderInterface $defaultSourceProvider
     */
    private DefaultSourceProviderInterface $defaultSourceProvider;

    /**
     * @var GetSkuFromOrderItemInterface $getSkuFromOrderItem
     */
    private GetSkuFromOrderItemInterface $getSkuFromOrderItem;

    /**
     * @var GetSourcesByOrderIdSkuAndQty $getSourcesByOrderIdSkuAndQty
     */
    private GetSourcesByOrderIdSkuAndQty $getSourcesByOrderIdSkuAndQty;

    /**
     * @param DefaultSourceProviderInterface $defaultSourceProvider
     * @param GetSkuFromOrderItemInterface $getSkuFromOrderItem
     * @param GetSourcesByOrderIdSkuAndQty $getSourcesByOrderIdSkuAndQty
     */
    public function __construct(
        DefaultSourceProviderInterface $defaultSourceProvider,
        GetSkuFromOrderItemInterface   $getSkuFromOrderItem,
        GetSourcesByOrderIdSkuAndQty   $getSourcesByOrderIdSkuAndQty
    )
    {
        $this->defaultSourceProvider = $defaultSourceProvider;
        $this->getSkuFromOrderItem = $getSkuFromOrderItem;
        $this->getSourcesByOrderIdSkuAndQty = $getSourcesByOrderIdSkuAndQty;
    }

    /**
     * Get all available inventory sources for order items
     *
     * @param OrderInterface $order
     * @param bool $forceDefaultSource
     * @return array
     * @throws LocalizedException
     */
    public function get(OrderInterface $order, bool $forceDefaultSource = true): array
    {
        $result = [];
        $defaultSource = $this->defaultSourceProvider->getCode();
        foreach ($order->getAllItems() as $orderItem) {
            if ($orderItem->getIsVirtual() || $orderItem->getLockedDoShip() || $orderItem->getHasChildren()) {
                continue;
            }

            $item = $orderItem->isDummy(true) ? $orderItem->getParentItem() : $orderItem;
            $defaultSource = [
                'qtyToDeduct' => (float)$item->getSimpleQtyToShip(),
                'sku' => $this->getSkuFromOrderItem->execute($item),
                'order_item_id' => $orderItem->getItemId(),
                'sourceCode' => $defaultSource
            ];
            try {
                $sources = $this->getSourcesByOrderIdSkuAndQty->execute(
                    (int)$order->getEntityId(),
                    sku: $defaultSource['sku'],
                    qty: $defaultSource['qtyToDeduct']
                );
            } catch (NoSuchEntityException $e) {
                if (!$forceDefaultSource) {
                    throw $e;
                }

                $sources = [$defaultSource];
            }

            foreach ($sources as $source) {
                $sourceCode = $source['sourceCode'];
                $result[$sourceCode][] = array_merge($defaultSource, $source);
            }
        }

        return $result;
    }
}
