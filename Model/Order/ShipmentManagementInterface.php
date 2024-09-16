<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Manages shipment creation
 */
interface ShipmentManagementInterface
{
    /**
     * Create shipments for order
     *
     * @param OrderInterface $order
     * @param array $constraints
     * @param bool $skipInventoryCheck
     * @return OrderInterface
     * @throws LocalizedException
     */
    public function shipOrder(OrderInterface $order, array $constraints = [], bool $skipInventoryCheck = true): OrderInterface;
}