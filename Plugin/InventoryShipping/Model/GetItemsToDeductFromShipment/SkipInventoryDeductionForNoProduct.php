<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Plugin\InventoryShipping\Model\GetItemsToDeductFromShipment;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryShipping\Model\GetItemsToDeductFromShipment;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductInterface;
use Magento\Sales\Model\Order\Shipment;
use RadWorks\QuickOrderShipment\Model\Inventory\SourceProviderInterface;

/**
 * Skips shipment item deduction if the product is not found.
 */
class SkipInventoryDeductionForNoProduct
{
    /**
     * @var DefaultSourceProviderInterface $defaultSourceProvider
     */
    private DefaultSourceProviderInterface $defaultSourceProvider;

    /**
     * @var GetProductIdsBySkusInterface $getProductIdsBySkus
     */
    private GetProductIdsBySkusInterface $getProductIdsBySkus;

    /**
     * Constructor.
     *
     * @param DefaultSourceProviderInterface $defaultSourceProvider
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     */
    public function __construct(
        DefaultSourceProviderInterface $defaultSourceProvider,
        GetProductIdsBySkusInterface   $getProductIdsBySkus
    )
    {
        $this->defaultSourceProvider = $defaultSourceProvider;
        $this->getProductIdsBySkus = $getProductIdsBySkus;
    }

    /**
     * Filter out shipment items' deduction array
     *
     * @param GetItemsToDeductFromShipment $subject
     * @param array $result
     * @param Shipment $shipment
     * @return array
     */
    public function afterExecute(GetItemsToDeductFromShipment $subject, array $result, Shipment $shipment): array
    {
        if (!$shipment->getExtensionAttributes()?->getSkipItemsDeduction()) {
            return $result;
        }

        if ($shipment->getExtensionAttributes()?->getSourceCode() == SourceProviderInterface::NO_SOURCE_CODE) {
            $shipment->getExtensionAttributes()->setSourceCode(
                $this->defaultSourceProvider->getCode()
            );
            return [];
        }

        /**  @var ItemToDeductInterface $item */
        foreach ($result as $index => $item) {
            try {
                $this->getProductIdsBySkus->execute([$item->getSku()]);
            } catch (NoSuchEntityException) {
                unset($result[$index]);
            }
        }

        return $result;
    }
}