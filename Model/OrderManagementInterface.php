<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;

interface OrderManagementInterface
{
    /**
     * Force ship order
     *
     * @param OrderInterface $order
     * @return OrderInterface
     * @throws LocalizedException
     */
    public function forceShip(OrderInterface $order): OrderInterface;
}