<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Model\Inventory;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Provides order items' inventory source selections by order
 */
interface SourceProviderInterface
{

    /**
     * Get all available inventory sources for order items
     *
     * @param OrderInterface $order
     * @param bool $forceDefaultSource
     * @return array
     * @throws LocalizedException
     */
    public function get(OrderInterface $order, bool $forceDefaultSource = true): array;
}