<?php
namespace Gtbank\Orders\Controller\Adminhtml;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class MassCancel extends \Magento\Sales\Controller\Adminhtml\Order\MassCancel
{
    protected function massAction(AbstractCollection $collection)
    {
        $error = 0;
        foreach ($collection->getItems() as $order) {
            if (!$order->getCustomerId()) {
                $error = 1;
                $message = "Can not cancel guest order(Order Number: #" . $order->getIncrementId() . ")";
                break;
            }

            if ($order->getStatus() != 'processing') {
                $error = 1;
                $message = "Your order in not in processing status (Order Number: #" . $order->getIncrementId() . ")";
                break;
            }

            if (!$order->getOrderNumber()) {
                $error = 1;
                $message = "Order not found (Order Number: #" . $order->getIncrementId() . ")";
                break;
            }

        }

        if ($error) {
            $this->messageManager->addError(__($message));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath($this->getComponentRefererUrl());
            return $resultRedirect;
        }

        foreach ($collection->getItems() as $order) {
            $status = $this->sendRefund($order);
            if ($status) {
                $invoices = $order->getInvoiceCollection();
                foreach ($invoices as $invoice) {
                    $invoiceIncrementId = $invoice->getIncrementId();
                }

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $invoice = $objectManager->create('Magento\Sales\Model\Order\Invoice');
                $invoiceobj = $invoice->loadByIncrementId($invoiceIncrementId);

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $creditmemoFactory = $objectManager->create('Magento\Sales\Model\Order\CreditmemoFactory');

                $creditmemo = $creditmemoFactory->createByOrder($order);
                $creditmemo->setInvoice($invoiceobj);

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $creditmemoService = $objectManager->create('Magento\Sales\Model\Service\CreditmemoService');
                $creditmemoService->refund($creditmemo);

                $order->setStatus('cancelled');
                $order->save();

            } else {
                $error = 1;
                $message = "Problem in send refund to merchant (Order Number: #" . $order->getIncrementId() . ")";
                break;
            }
        }

        if ($error) {
            $this->messageManager->addError(__($message));
        } else {
            $this->messageManager->addSuccess(__("Cancel order successfully."));
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($this->getComponentRefererUrl());
        return $resultRedirect;
    }

    public function sendRefund($order)
    {
        try {

			$transactionId = [];
			$productId = [];
            foreach ($order->getAllVisibleItems() as $item) {
                if ($item->getQtyRefunded() > 0) {
					$transactionId[] = $item->getCancelTransactionId();
					$productId[] = $item->getProductId();
                }
            }

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $orders = $objectManager->create('Webkul\Marketplace\Model\OrdersFactory')->create();
            $orders = $orders->getCollection()->addFieldToFilter('order_id', $order->getId())->getFirstItem();

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $sellerData = $objectManager->create('Webkul\Marketplace\Model\SellerFactory')->create()
                ->getCollection()
                ->addFieldToFilter('seller_id', $orders->getSellerId())->getFirstItem();

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $helper = $objectManager->create('Gtbank\Orders\Helper\Data');

            $body = [
                "orderId" 		=> $order->getOrderNumber(),
                "merchantIds" 	=> [$sellerData->getId()],
                "userId" 		=> $order->getCustomerId(),
				"transactionIds" => $transactionId,
				"productId" 	=> $productId,
            ];

            $responce = $helper->sendCancel($body);
            return $responce;
        } catch (\Exception $e) {
            return false;
        }
    }
}
