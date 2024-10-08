<?php
declare(strict_types=1);

namespace RadWorks\QuickOrderShipment\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save;
use Magento\Ui\Component\MassAction\FilterFactory;
use RadWorks\QuickOrderShipment\Api\OrderShipmentBuilderInterface;

/**
 * Force creation of shipments of the selected orders
 */
class QuickShip extends Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = Save::ADMIN_RESOURCE;

    /**
     * @var CollectionFactory $collectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var FilterFactory $filterFactory
     */
    private FilterFactory $filterFactory;

    /**
     * @var OrderShipmentBuilderInterface $shipmentBuilder
     */
    private OrderShipmentBuilderInterface $shipmentBuilder;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param FilterFactory $filterFactory
     * @param OrderShipmentBuilderInterface $shipmentBuilder
     */
    public function __construct(
        Context                     $context,
        CollectionFactory           $collectionFactory,
        FilterFactory               $filterFactory,
        OrderShipmentBuilderInterface $shipmentBuilder
    )
    {
        $this->collectionFactory = $collectionFactory;
        $this->filterFactory = $filterFactory;
        $this->shipmentBuilder = $shipmentBuilder;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $collection = $this->filterFactory->create()->getCollection($this->collectionFactory->create());
        $shipped = [];
        $cannotBeShipped = [];
        /** @var OrderInterface $order */
        foreach ($collection->getItems() as $order) {
            $incrementId = $order->getIncrementId();
            if (!$order->canShip()) {
                $cannotBeShipped[] = $incrementId;
                continue;
            }

            try {
                $this->shipmentBuilder
                    ->skipInventoryDeduction(true)
                    ->build($order)
                    ->save();
                $shipped[] = $incrementId;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage(
                    __('Order #%1: shipment was not created - "%2".', $incrementId, $e->getMessage())
                );
            } catch (\Throwable) {
                $this->messageManager->addErrorMessage(__('Order #%1: no shipment created.', $incrementId));
            }
        }

        if ($shipped) {
            $this->messageManager->addSuccessMessage(
                __('These orders have been shipped: %1.', implode(', ', $shipped))
            );
        }

        if ($cannotBeShipped) {
            $this->messageManager->addNoticeMessage(
                __('These orders cannot be shipped: %1.', implode(', ', $cannotBeShipped))
            );
        }

        return $resultRedirect->setPath('*/*/');
    }
}