<?php
namespace Gtbank\Orders\Setup;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;

/**
 * Class InstallData
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * Custom Order-Status
     */
    const ORDER_STATUS_PICKED_UP = 'picked_up';
    const ORDER_STATUS_PICKED_UP_LABEL = 'Picked up';

    const ORDER_STATUS_READY_FOR_PICK_UP = 'ready_for_pick_up';
    const ORDER_STATUS_READY_FOR_PICK_UP_LABEL = 'Ready for Pick Up';

    /**
     * Status Factory
     *
     * @var StatusFactory
     */
    protected $statusFactory;

    /**
     * Status Resource Factory
     *
     * @var StatusResourceFactory
     */
    protected $statusResourceFactory;

    /**
     * InstallData constructor
     *
     * @param StatusFactory $statusFactory
     * @param StatusResourceFactory $statusResourceFactory
     */
    public function __construct(
        StatusFactory $statusFactory,
        CollectionFactory $orderCollectionFactory,
        StatusResourceFactory $statusResourceFactory,
        \Magento\Framework\App\State $appState

    ) {
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->statusFactory = $statusFactory;
        $this->appState = $appState;
        $this->statusResourceFactory = $statusResourceFactory;
    }

    /**
     * Installs data for a module
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     *
     * @throws Exception
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->appState->setAreaCode('adminhtml');

        if (version_compare($context->getVersion(), '1.0.6', '<')) {
            $this->addNewOrderPickedUpStatus();
            $this->addNewOrderReadyForPickUpStatus();
            $this->updateStatus();
        }
    }

    /**
     * Create new order status and assign it to the existent state
     *
     * @return void
     *
     * @throws Exception
     */
    protected function addNewOrderPickedUpStatus()
    {
        /** @var StatusResource $statusResource */
        $statusResource = $this->statusResourceFactory->create();
        /** @var Status $status */
        $status = $this->statusFactory->create();
        $status->setData([
            'status' => self::ORDER_STATUS_PICKED_UP,
            'label' => self::ORDER_STATUS_PICKED_UP_LABEL,
        ]);

        try {
            $statusResource->save($status);
        } catch (AlreadyExistsException $exception) {
            return;
        }

        $status->assignState(Order::STATE_COMPLETE, false, true);
    }

    /**
     * Create new order status and assign it to the existent state
     *
     * @return void
     *
     * @throws Exception
     */
    protected function addNewOrderReadyForPickUpStatus()
    {
        /** @var StatusResource $statusResource */
        $statusResource = $this->statusResourceFactory->create();
        /** @var Status $status */
        $status = $this->statusFactory->create();
        $status->setData([
            'status' => self::ORDER_STATUS_READY_FOR_PICK_UP,
            'label' => self::ORDER_STATUS_READY_FOR_PICK_UP_LABEL,
        ]);

        try {
            $statusResource->save($status);
        } catch (AlreadyExistsException $exception) {
            return;
        }

        $status->assignState(Order::STATE_PROCESSING, false, true);
    }

    protected function updateStatus()
    {
        $_orderCollection = $this->_orderCollectionFactory->create()
            ->addFieldToFilter('status', ['in' => ['packed','dispatched']]);
        foreach ($_orderCollection as $order) {
            $orderItems = $order->getAllVisibleItems();
            foreach ($orderItems as $orderItem) {
                $method = $orderItem->getDeliveryMethod();
                break;
            }

            if ($method == 'customer_pickup') {
                if ($order->getStatus() == 'packed') {
                    $order->setStatus('ready_for_pick_up');
                    $order->save();
                } elseif ($order->getStatus() == 'dispatched') {
                    $order->setStatus('picked_up');
                    $order->save();
                }
            }
        }
    }
}
