<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Model\Inventory;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventorySalesApi\Model\GetSkuFromOrderItemInterface;
use Magento\InventoryShippingAdminUi\Ui\DataProvider\GetSourcesByOrderIdSkuAndQty;
use Magento\Sales\Model\Order\Item;

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
     * @param Item[] $orderItems
     * @param array $skus
     * @param bool $forceEmptySource
     * @return array
     * @throws LocalizedException
     */
    public function get(array $orderItems, array $skus = [], bool $forceEmptySource = true): array
    {
        $result = [];
        foreach ($orderItems as $orderItem) {
            $sku = $this->getSkuFromOrderItem->execute($orderItem);
            if ($skus && !array_key_exists($sku, $skus)) {
                continue;
            }

            $qtyToShip = (float)$orderItem->getSimpleQtyToShip();
            $customQty = $skus[$sku] ?? $qtyToShip;
            $qtyToShip = min($customQty, $qtyToShip);
            $sources = [];
            $defaultSource = [
                self::DATA_FIELD_QTY => $qtyToShip,
                self::DATA_FIELD_SKU => $sku,
                self::DATA_FIELD_ITEM_ID => $orderItem->getItemId(),
                self::DATA_FIELD_CODE => self::NO_SOURCE_CODE
            ];
            try {
                $sources = $this->getSourcesByOrderIdSkuAndQty->execute(
                    (int)$orderItem->getOrderId(),
                    sku: $defaultSource[self::DATA_FIELD_SKU],
                    qty: $defaultSource[self::DATA_FIELD_QTY]
                );
                $qtyToShipSources = array_sum(array_column($sources, self::DATA_FIELD_QTY));
                if ($forceEmptySource && $qtyToShipSources < $qtyToShip) {
                    $sources = array_filter($sources, fn($source) => $source[self::DATA_FIELD_QTY]);
                    $sources[self::NO_SOURCE_CODE] = array_merge(
                        $defaultSource, [self::DATA_FIELD_QTY => $qtyToShip - $qtyToShipSources]
                    );
                }
            } catch (NoSuchEntityException $e) {
                if (!$forceEmptySource) {
                    throw $e;
                }
            }

            if ($forceEmptySource && !$sources) {
                $sources = [$defaultSource];
            }

            foreach ($sources as $source) {
                $result[$source[self::DATA_FIELD_CODE]][] = array_merge($defaultSource, $source);
            }
        }

        return $result;
    }
}
