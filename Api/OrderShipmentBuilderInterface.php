<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Exception\CouldNotShipException;
use RadWorks\QuickOrderShipment\Model\Order\OrderShipmentBuilder;

/**
 * Manages shipment creation
 */
interface OrderShipmentBuilderInterface
{
    /**
     * Create shipments for order
     *
     * @param OrderInterface $order
     * @param array $quantities
     * @return self
     * @throws LocalizedException
     */
    public function build(OrderInterface $order, array $quantities = []): self;

    /**
     * Create comment creation model for the shipment
     *
     * @param string $comment
     * @return OrderShipmentBuilder
     */
    public function addComment(string $comment): self;

    /**
     * Builds track creation model for the shipment
     *
     * @param array $track
     * @return self
     */
    public function addTrack(array $track): self;

    /**
     * Create shipments for an order
     *
     * @return void
     * @throws CouldNotShipException
     */
    public function save(): void;

    /**
     * Set flag to skip validation
     *
     * @param bool $skipInventoryDeduction
     * @return self
     */
    public function skipInventoryDeduction(bool $skipInventoryDeduction): self;
}