<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Model\Inventory;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Item;

/**
 * Provides order items' inventory source selections by order
 */
interface SourceProviderInterface
{
    public const NO_SOURCE_CODE = 'no_source';

    /**
     * Sources' fields names
     */
    public const DATA_FIELD_QTY = 'qtyToDeduct';
    public const DATA_FIELD_SKU = 'sku';
    public const DATA_FIELD_ITEM_ID = 'order_item_id';
    public const DATA_FIELD_CODE = 'sourceCode';

    /**
     * Get all available inventory sources for order items
     *
     * @param Item[] $orderItems
     * @param array $constraints
     * @param bool $forceEmptySource
     * @return array
     * @throws LocalizedException
     */
    public function get(array $orderItems, array $constraints = [], bool $forceEmptySource = true): array;
}