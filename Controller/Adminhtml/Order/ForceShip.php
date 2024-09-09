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
use Magento\Ui\Component\MassAction\FilterFactory;
use RadWorks\QuickOrderShipment\Model\OrderManagementInterface;

/**
 * Force creation of shipments of the selected orders
 */
class ForceShip extends Action implements HttpPostActionInterface
{
    /**
     * @var CollectionFactory $collectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var FilterFactory $filterFactory
     */
    private FilterFactory $filterFactory;

    /**
     * @var OrderManagementInterface $orderManagement
     */
    private OrderManagementInterface $orderManagement;

    public function __construct(
        Context                  $context,
        CollectionFactory        $collectionFactory,
        FilterFactory            $filterFactory,
        OrderManagementInterface $orderManagement
    )
    {
        $this->collectionFactory = $collectionFactory;
        $this->filterFactory = $filterFactory;
        $this->orderManagement = $orderManagement;
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
        /** @var OrderInterface $order */
        foreach ($collection->getItems() as $order) {
            $incrementId = $order->getIncrementId();
            if (!$order->canShip()) {
                $this->messageManager->addNoticeMessage(__('Skipped. Order #%1 cannot be shipped.', $incrementId));
                continue;
            }

            try {
                $this->orderManagement->forceShip($order);
                $this->messageManager->addSuccessMessage(__('#%1 has been shipped.', $incrementId));
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage(__('#%1: shipment was not created - "%2".', $incrementId, $e->getMessage()));
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage(__('#%1: shipment was not created.', $incrementId));
            }
        }

        return $resultRedirect->setPath('*/*/');
    }
}