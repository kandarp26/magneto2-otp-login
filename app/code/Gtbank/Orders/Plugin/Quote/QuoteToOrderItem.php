<?php
namespace Gtbank\Orders\Plugin\Quote;

use Closure;

class QuoteToOrderItem
{
    
  public function aroundConvert(
        \Magento\Quote\Model\Quote\Item\ToOrderItem $subject,
        Closure $proceed,
        \Magento\Quote\Model\Quote\Item\AbstractItem $item,
        $additional = []
    ) {
		
        $orderItem = $proceed($item, $additional);//result of function 'convert' in class 'Magento\Quote\Model\Quote\Item\ToOrderItem' 
		$orderItem->setDeliveryMethod($item->getDeliveryMethod());
		$orderItem->setDeliveryCourier($item->getDeliveryCourier());
		$orderItem->setDeliveryComment($item->getDeliveryComment());
		$orderItem->setDeliveryCost($item->getDeliveryCost());
		$orderItem->setRateId($item->getRateId());
		$orderItem->setGiftWrap($item->getGiftWrap());

        return $orderItem;// return an object '$orderItem' which will replace result of function 'convert' in class 'Magento\Quote\Model\Quote\Item\ToOrderItem'
    }

}