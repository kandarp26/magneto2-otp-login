<?php

namespace Gtbank\Orders\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;


class SalesOrderPlaceAfter implements ObserverInterface
{
    // protected $_logger;

    // public function __construct(
    //     \Magento\Backend\Block\Template\Context $context,
    //     \Psr\Log\LoggerInterface $logger,
    //     array $data = []
    // )
    // {
    //     $this->_logger = $logger;
    //     parent::__construct($context, $data);
    // }



public function execute(\Magento\Framework\Event\Observer $observer)
{

$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
$cart = $objectManager->get('\Magento\Checkout\Model\Cart');
$grandTotal = $cart->getQuote()->getGrandTotal();

            $commissionFlag = $grandTotal;
            $percent = 5;
            $commission = (($commissionFlag * $percent) / 100);
            $order = $observer->getEvent()->getOrder();
    $order->setCommission($commission);
    $order->save();
}
}