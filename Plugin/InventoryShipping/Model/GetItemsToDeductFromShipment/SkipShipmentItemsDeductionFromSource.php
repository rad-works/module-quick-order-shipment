<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Plugin\InventoryShipping\Model\GetItemsToDeductFromShipment;

use Magento\InventoryShipping\Model\GetItemsToDeductFromShipment;
use Magento\Sales\Model\Order\Shipment;

class SkipShipmentItemsDeductionFromSource
{
    /**
     * @param GetItemsToDeductFromShipment $subject
     * @param array $result
     * @param Shipment $shipment
     * @return array
     */
    public function afterExecute(GetItemsToDeductFromShipment $subject, array $result, Shipment $shipment): array
    {
        if ($shipment->getExtensionAttributes()?->getSkipItemsDeduction()) {
            return [];
        }

        return $result;
    }
}