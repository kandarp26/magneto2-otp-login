<?php

namespace Gtbank\Orders\Model;

use Gtbank\Orders\Api\OrderInterface;
use Magento\Framework\App\Request\Http;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\InputException;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Gtbank\Products\Model\QuoteCouponFactory;
use Webkul\Rmasystem\Model\ResourceModel\Rmaitem\CollectionFactory as ItemCollectionFactory;

 
class Order implements OrderInterface
{
	public function __construct(
		Http $request,
		\Magento\Framework\App\ResourceConnection $resource,
		\Gtbank\Orders\Helper\Data $emailHelper,
		\Magento\Sales\Model\RefundOrder $refundOrder,
		\Magento\Sales\Model\Order\Creditmemo\ItemCreationFactory $itemCreationFactory,
		\Magento\Sales\Model\Order\Creditmemo\CreationArguments $creationArguments,
		\Webkul\Rmasystem\Model\AllrmaFactory $allrma,
		\Magento\Sales\Model\Order\CreditmemoFactory $creditMemoFacory,
		\Magento\Sales\Model\Service\CreditmemoService $creditmemoService,
		ItemCollectionFactory $itemCollectionFactory,
		QuoteCouponFactory $quoteCouponFactory,
		CartManagementInterface $quoteManagement,
		\Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $statusCollectionFactory,
		\Magento\Directory\Model\CountryFactory $countryFactory,
		\Magento\Sales\Api\OrderManagementInterface $orderManagement,
		\Gtbank\Products\Model\Products $products,
		\Gtbank\Orders\Model\CancelOrderFactory $cancelOrder,
		\Magento\Sales\Model\OrderFactory $orderFactory,
		\Magento\Sales\Model\Order\ItemFactory $itemFactory,
		ProductFactory $productFactory,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		QuoteIdMaskFactory $quoteIdMaskFactory,
		CustomerFactory $customer,
		\Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezoneInterface,
		\Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
		\Magento\Framework\Pricing\Helper\Data $priceHelper,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Webkul\Marketplace\Model\Product $MarketplaceProduct,
		\Webkul\Marketplace\Helper\Data $helper,
		\Webkul\Rmasystem\Model\ReasonFactory $reasonFactory,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
		\Webkul\MpSellerCoupons\Helper\Data $dataHelper,
		\Gtbank\GuestRestriction\Helper\Data $tokenHelper
	)
	{
		$this->resource = $resource;
		$this->creditMemoFacory = $creditMemoFacory;
		$this->creditmemoService = $creditmemoService;
		$this->emailHelper = $emailHelper;
		$this->refundOrder = $refundOrder;
		$this->itemCreationFactory = $itemCreationFactory;
		$this->creationArguments = $creationArguments;
		$this->orderManagement = $orderManagement;
		$this->allrma = $allrma;
		$this->_dataHelper = $dataHelper;
		$this->itemCollectionFactory = $itemCollectionFactory;
		$this->scopeConfig = $scopeConfigInterface;
		$this->reasonFactory = $reasonFactory;
		$this->quoteCouponFactory = $quoteCouponFactory;
		$this->statusCollection = $statusCollectionFactory;
		$this->quoteManagement = $quoteManagement;
		$this->_countryFactory = $countryFactory;
		$this->_objectManager = $objectManager;
		$this->cancelOrder = $cancelOrder;
		$this->customer = $customer;
		$this->itemFactory = $itemFactory;
		$this->timezoneInterface = $timezoneInterface;
		$this->tokenHelper = $tokenHelper;
		$this->_storeManager = $storeManager;
		$this->order = $orderFactory;
		$this->products = $products;
		$this->marketplaceProduct = $MarketplaceProduct;
		$this->_priceHelper = $priceHelper;
		$this->_productFactory = $productFactory;
		$this->request = $request;
		$this->sellerHelper = $helper;
		$this->quoteRepository = $quoteRepository;
		$this->quoteIdMaskFactory = $quoteIdMaskFactory;

	}
	
	/**
     * Returns greeting message to user
     * @api
     * @return message.
     */
    public function getOrders()
	{
		$customerId = $this->tokenHelper->validateCustomer();
        if (empty($customerId) || !isset($customerId) || $customerId == "") {
            throw new InputException(__('Unauthorized Token'));
        }
	
		$orders = $this->order->create()->getCollection()->addFieldToFilter('customer_id',$customerId);
		$orders->getSelect()->order("created_at desc");		
		$data = [];
		$itemsdata = [];

		foreach($orders as $o){
			$orderStatus = $o->getStatus();
			$date = $this->timezoneInterface->date($o->getCreatedAt())->format ("h:i A d M Y");
			$items = array();
			//$itemsdata = array();
			foreach($o->getAllItems() as $items){
				$productData = $this->_productFactory->create()->load($items->getProductId());
				$marketplaceProduct = $this->marketplaceProduct->getCollection()
								->addFieldToFilter('mageproduct_id',$productData->getId())
								->getFirstItem();
				
				$shoptitle = '';
				if($sellerId = $marketplaceProduct->getSellerId()){
					
					$sellerData = $this->sellerHelper->getSeller($sellerId);
					$shoptitle = $sellerData['shop_title'];
				}
				
				if ( !$items->getParentItemId()) {
					$options = $items->getProductOptions();
					$result = array();
					if ($options) {
						if (isset($options['options'])) {
							$result = array_merge($result, $options['options']);
						}
						if (isset($options['additional_options'])) {
							$result = array_merge($result, $options['additional_options']);
						}
						if (isset($options['attributes_info'])) {
							$result = array_merge($result, $options['attributes_info']);
						}
					}
					
					$collection = $this->cancelOrder->create()->getCollection();
					$collection = $collection->addFieldToFilter('item_id',$items->getId());
		
					if(!empty($collection->getData())){
						$is_cancelled = 1;
					}else{
						$is_cancelled = 0;
					}
					
					
					if ($o->getStatus() == 'cancelled'){
						$is_cancelled = 1;
					}
					

					
					if($this->allowReturn($items, $o)){
						$allowReturn = 1;
						//$maxReturnQty = (int)$this->allowQtyForReturn($o,$items);
						$maxReturnQty = (int)($items->getQtyOrdered() - $items->getQtyRefunded());
					}else{
						$allowReturn  = 0;
						$maxReturnQty = 0;
					}
					
					if($items->getDeliveryMethod() == 'customer_pickup' && $o->getStatus() == 'dispatched'){
						$itemStatus = 'Picked Up';
						$orderStatus = 'delivered';
					}else if($items->getDeliveryMethod() == 'customer_pickup' && $o->getStatus() == 'packed'){
						$itemStatus = 'Ready for Pick Up';
					} else {
						$itemStatus = $o->getStatusLabel();	
					}

					if($items->getProductType() == 'configurable'){
						$childItem = $this->itemFactory->create()->getCollection()->addFieldToFilter('parent_item_id',$items->getId())->getFirstItem();
						$childProduct = $this->_productFactory->create()->load($childItem->getProductId());
						if($childProduct->getImage()){
							$imgUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $childProduct->getImage();
						}else{
							$imgUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $productData->getImage();						
						}
						}else{
						$imgUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $productData->getImage();
					}


					if($is_cancelled == 0 && $orderStatus == 'closed'){
						$itemStatus = 'Closed';
					}
					
					$itemsdata[] = [
						"seller_id"			=> $sellerId,
						"rma_status"		=> $this->getRmaStatus($o,$items),
						"allow_return"   	=> $allowReturn,
						"max_return_qty" 	=> $maxReturnQty,
						"item_id" 	   		=> $items->getItemId(),
						"is_cancelled" 		=> $is_cancelled,
						"product_id"  		=> $items->getProductId(),
						"name"        		=> $items->getName(),
						"qty_ordered" 		=> (int)$items->getQtyOrdered(),
						"price"       		=> $this->_priceHelper->currency($items->getRowTotal(), true, false),
						"image"			  	=> $imgUrl,
						"shop_title" => $shoptitle,
						"custom_option" 	=> $result,
						"created_at" 		=> $o->getCreatedAt(),
						"order_data" 		=> [
								'increment_id' 	=> $o->getIncrementId(),
								'status'		=> $orderStatus,
								'status_lable'	=> $itemStatus,
								'created_at'	=> $date,
								"total_amount_to_paid" 	=> $this->_priceHelper->currency($items->getRowTotal() - $items->getDiscountAmount()+$items->getDeliveryCost(), true, false)
								
							]
					];
				}
			}
		}
		
		return $itemsdata;
	}
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $cartId cartId.
	 * @param string $message.
     * @return customarray
     */
    public function deliveryCommnent($cartId,$message)
	{
		$quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
		$cartId = $quoteIdMask->getQuoteId();
		$quote = $this->quoteRepository->getActive($cartId);		
		$quote->setDeliveryComment($message);
		$quote->save();
		$data['status'] = true;
		
		return $data;
	}
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $cartId cartId.
	 * @return customarray
     */
    public function getDeliveryCommnent($cartId)
	{
		$quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
		$cartId = $quoteIdMask->getQuoteId();
		$quote = $this->quoteRepository->getActive($cartId);		
		$data['delivery_comment'] = $quote->getDeliveryComment();
		return $data;
	}
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $cartId cartId.
     * @return customarray
     **/
	public function getOrderConfirmation($cartId)
	{
		$quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
		$cartIdOrg = $quoteIdMask->getQuoteId();
		$quote = $this->quoteRepository->getActive($cartIdOrg);
		
		$items = $this->products->getItemList($cartId);		
		$shippingData = [];
		foreach($items as $item){
			$quoteItem = $quote->getItemById($item['data'][0]['item_id']);
			
			$item['shipping_option'] = array(
				'delivery_method' =>  $this->getMethodTitle($quoteItem->getDeliveryMethod()),
				'delivery_courier' => $this->getMethodCourier($quoteItem->getDeliveryCourier())
			);
				
				$shippingData['items'][] = $item;
		}
		
		$totels = $quote->getShippingAddress()->getTotals();
		
		foreach($totels as $t){
			
			$temp = array(
				'code' => $t->getData('code'),
				'title' => $t->getData('title'),
				'value' => $t->getData('value')
				
			);
			
			$shippingData['totels'][] = $temp;
		}
		
		
		$shippingData['shipping_address'] = $quote->getShippingAddress()->getData();
		$addresData = $quote->getShippingAddress();
		$country = $this->_countryFactory->create()->loadByCode($addresData->getCountry());
        
		
		$shippingData['address'] = array(
				"name"		=> $addresData->getFirstname().' '.$addresData->getLastname(),
				"street"	=> $addresData->getStreet(),
				"city"		=> $addresData->getCity(),
				"state"		=> $addresData->getRegion(),
				"country"	=> $country->getName(),
				"postcode"	=> $country->getPostcode(),
				"phone"		=> $country->getTelephone()
		);
		
		
		return $shippingData;
	}
	
	public function getMethodCourier($methodCode)
	{
		$map = array(
			"star_door_to_door" 			=> "Star Door to Door",
			"dhl"							=> "DHL courier",
			"chips_international_courier" 	=> "Chips International courier"
		);
		
		return isset($map[$methodCode])? $map[$methodCode] : $methodCode;
	}
	
	public function getMethodTitle($methodCode)
	{
		$map = array(
			"merchant_delivery" => "Merchant Delivery",
			"customer_pickup"	=> "Customer Pickup",
			"gpd" => "Habari Partner Delivery"
		);
		
		return isset($map[$methodCode]) ? $map[$methodCode] : $methodCode;
	}
	
	public function allowQtyForReturn($order,$item)
	{
		$collection = $this->itemCollectionFactory->create()->addFieldToFilter('order_id',['eq' => $order->getId()])
							->addFieldToFilter('item_id', ['eq' => $item->getId()]);
		
		if ($collection->getSize()) {
			$remainsQty = $item->getQtyOrdered() - $collection->getFirstItem()->getQty();
			
		}else {
			$remainsQty = $item->getQtyOrdered();
		}
		
		return $remainsQty;
	}
	
	public function getRmaStatus($order,$item)
	{
		$collection = $this->itemCollectionFactory->create();
		$collection->getSelect()->where('main_table.order_id='.$order->getId());
		$collection->getSelect()->where('main_table.item_id='.$item->getId());
		$joinConditions = 'main_table.rma_id = wk_rma.rma_id';

		$collection->getSelect()->joinLeft(['wk_rma'],$joinConditions,[])->columns("wk_rma.final_status");
			
		$collection->getSelect()->where("wk_rma.final_status in (0,1)");
		$status = (array)$this->allrma->create()->getAvailableStatuses();
		
		
		
		$data = [];
		foreach($collection->getData() as $c)
		{
			$temp = $c;
			$temp['final_status'] = $status[$c['final_status']];
			$data[] = $temp;
		} 

		return $data;
	}
	
	/**
     * Returns greeting message to user
     * @api
	 * @param string $itemId itemId.
     * @return customarray
     */
	public function getItemDetails($itemId)
	{

		$item = $this->itemFactory->create()->load($itemId);		

		if(!$item->getId()){
			throw new LocalizedException(__('Item not found.'));
		}

		$baseUrl 			= $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

		$orders  			= $this->order->create()->load($item->getOrderId());
		$productData 		= $this->_productFactory->create()->load($item->getProductId());		
		$billingAddress 	= $orders->getBillingAddress();
		$shippingAddress 	= $orders->getShippingAddress();
		
		$payment = $orders->getPayment();
		$method = $payment->getMethodInstance();
		$methodTitle = $method->getTitle();
		
		$orderHistory = $orders->getStatusHistoryCollection();
		$orderHistory->getSelect()->reset("order");
		
		
		$marketplaceProduct = $this->marketplaceProduct->getCollection()
								->addFieldToFilter('mageproduct_id',$item->getProductId())
								->getFirstItem();
		
		$sellerId = '';
		if($marketplaceProduct->getData()){
			$sellerId = $marketplaceProduct->getSellerId();
		}
		
		foreach($orderHistory->getData() as $h){
			$status = $this->statusCollection->create();
			$status = $status->addFieldToFilter('status',$h['status'])->getFirstItem();
			
			$orderHistoryData = array(
				'status' 	=> $status->getStatus(),
				'label' 	=> $status->getLabel(),
				'date' 		=> $this->timezoneInterface->date($h['created_at'])->format ("d M Y"),
			);
			
			$history[$status->getStatus()] = $orderHistoryData;
		}
		
		$collection = $this->cancelOrder->create()->getCollection();
		$collection = $collection->addFieldToFilter('item_id',$item->getId());
		if(!empty($collection->getData())){
			$data['order_items']['is_cancelled'] = 1;
		}else{
			$data['order_items']['is_cancelled'] = 0;
		}
		
		$shipment = $orders->getShipmentsCollection()->getFirstItem();
		$orderHistoryData = [];
		$orderHistoryData[] = array(
			'status' 	=> 'processing',
			'label' 	=> 'Order Placed',
			'date' 		=> $this->timezoneInterface->date($orders->getCreatedAt())->format ("d M Y")
		);
		
		
		if(!empty($collection->getData())){
		    
		    $cancel = $collection->getFirstItem();
		    
		    $orderHistoryData[] = array(
    			'status' => 'cancel',
    			'label' => 'Cancel',
    			'date' => $this->timezoneInterface->date($cancel->getCreatedAt())->format ("d M Y")
    		);
		}
		


		if(!$data['order_items']['is_cancelled'] && $item->getDeliveryMethod() != 'customer_pickup' ){
    		$orderHistoryData[] = array(
    			'status' => 'packed',
    			'label' => 'Packed',
    			'date' => isset($history['packed']['date']) && $orders->getStatus() != 'processing'?$history['packed']['date']:''
    		);
		}else if($item->getDeliveryMethod() == 'customer_pickup' && !$data['order_items']['is_cancelled'] ){
			$orderHistoryData[] = array(
    			'status' => 'packed',
    			'label' => 'Ready for Pick Up',
    			'date' => isset($history['packed']['date'])?$history['packed']['date']:''
    		);
		}
		
		if(!$data['order_items']['is_cancelled'] && $item->getDeliveryMethod() != 'customer_pickup'){
		    $orderHistoryData[] = array(
				'status' => 'dispatched',
				'label' => 'Dispatched',
				'date' => !empty($shipment->getData()) ? $this->timezoneInterface->date($shipment->getCreatedAt())->format ("d M Y") :'',
			);
		}else if($item->getDeliveryMethod() == 'customer_pickup' && !$data['order_items']['is_cancelled'] ){
			$orderHistoryData[] = array(
				'status' => 'delivered',
				'label' => 'Picked Up',
				'date' => !empty($shipment->getData()) ? $this->timezoneInterface->date($shipment->getCreatedAt())->format ("d M Y") :'',
			);	
		}
		
		if(!$data['order_items']['is_cancelled'] && $item->getDeliveryMethod() != 'customer_pickup'){
			$orderHistoryData[] = array(
				'status' => 'delivered',
				'label' => 'Delivered',
				'date' => isset($history['delivered']['date'])  ? $history['delivered']['date']:'',
			);		
		}

		//@todo update shipping method table
		$courier = [
			"customer_pickup" 	=> "Customer Pickup",
			"merchant_delivery" => "Merchant Delivery",
			"gpd" 				=> "Habari Partner Delivery",
		];		

		$data['order_history'] = $orderHistoryData;
		$data['order_items']['item_id'] = $item->getId();
		
		
		if($this->allowReturn($item, $orders)){
			$data['order_items']['allow_return'] = 1;
			//$data['order_items']['max_return_qty'] = (int)$this->allowQtyForReturn($orders,$item);
			$data['order_items']['max_return_qty'] = (int)($item->getQtyOrdered() - $item->getQtyRefunded());
		}else{
			$data['order_items']['allow_return'] = 0;
			$data['order_items']['max_return_qty'] = 0;
		}
		
		$data['order_items']['seller_id'] = $sellerId;
		$data['order_items']['rma_status'] = $this->getRmaStatus($orders,$item);

		if($item->getProductType() == 'configurable'){
			$childItem = $this->itemFactory->create()->getCollection()
				->addFieldToFilter('parent_item_id',$item->getId())
				->getFirstItem();
			$childProduct = $this->_productFactory->create()->load($childItem->getProductId());
			if($childProduct->getImage()){
				$data['order_items']['image'] = $baseUrl . 'catalog/product' . $childProduct->getImage();
			}else{
				$data['order_items']['image'] = $baseUrl . 'catalog/product' . $productData->getImage();
		
			}
		}else{
			$data['order_items']['image'] = $baseUrl . 'catalog/product' . $productData->getImage();
		
		}

		$data['order_items']['name'] = $productData->getName();
		$data['order_items']["product_id"]  = $item->getProductId();
		$data['order_items']["qty_ordered"] = (int)$item->getQtyOrdered();
		$data['order_items']["price"]       = $this->_priceHelper->currency($item->getRowTotal(), true, false);
		$data['order_items']["discounted_amount"] = $this->_priceHelper->currency($item->getDiscountAmount(), true, false);
		$data['order_items']["delivery_comment"] = $item->getDeliveryComment();
		$data['order_items']["delivery_method"] = isset($courier[$item->getDeliveryMethod()])?$courier[$item->getDeliveryMethod()]:'';
		
		$data['order_items']["applied_coupen"] = "";

		if($item->getDiscountAmount() > 0){
			$quoteCoupon = $this->quoteCouponFactory->create();
			$quoteCoupon = $quoteCoupon->getCollection()->addFieldToFilter('item_id',$item->getQuoteItemId());
			$quoteCoupon = $quoteCoupon->getFirstItem();
			
			if($quoteCoupon->getData()){
				$data['order_items']["applied_coupen"] = $quoteCoupon->getCouponCode();
			}
		}
		
		$date = $this->timezoneInterface->date($orders->getCreatedAt())->format ("h:i A d M Y");

		$orderStatus = $orders->getStatus();
		if($item->getDeliveryMethod() == 'customer_pickup' && $orders->getStatus() == 'dispatched'){
            $itemStatus = 'Picked Up';
			$orderStatus = 'delivered';
		}else{
			$itemStatus = $orders->getStatusLabel();
		}
		
		$data["order_data"] = [
			'increment_id' 	=> $orders->getIncrementId(),
			'status'		=> $itemStatus,
			'status_code'	=> $orderStatus,
			'created_at'	=> $date,
			'packed_date'   => isset($history['packed']['date'])?$history['packed']['date']:'',
			'payment_method' => $methodTitle,
			"traking_code"	=> $orders->getTrakingCode()
		];
		
		$data["order_total"] = [
			"total_amount"			=> $this->_priceHelper->currency($item->getRowTotal(), true, false),
			"discounted_amount" 	=> $this->_priceHelper->currency($item->getDiscountAmount(), true, false),
			"delivery_charge" 		=> $this->_priceHelper->currency($item->getDeliveryCost(), true, false), 
			"total_amount_to_paid" 	=> $this->_priceHelper->currency($item->getRowTotal() - $item->getDiscountAmount()+$item->getDeliveryCost(), true, false)
		];
		
		$data['shipping_address'] = $shippingAddress->getData();
		
		$marketplaceProduct = $this->marketplaceProduct->getCollection()->addFieldToFilter('mageproduct_id',$productData->getId())->getFirstItem();

		$sellerId = $marketplaceProduct->getSellerId();

		if($sellerId){
			$sellerData = $this->sellerHelper->getSeller($sellerId);
			$customer 	= $this->customer->create()->load($sellerId);
			$email 		= $customer->getEmail();
			$data['seller'] = $sellerData;
			$data['seller']["email"]  = $email;
		}else {
			$data['seller']["shop_title"]  = "Habari Admin";
			$data['seller']["company_locality"]  = "Lagos,Nigeria ";
			$data['seller']["email"]  = "";
		}
		
		return $data;
	}

	/**
     * Returns greeting message to user
     * @api
     * @param string[] $orderIds orderIds
     * @return customarray
     */
	public function cancelOrders($orderIds)
	{
		try{
		//	$orderIds = explode(',',$orderIds);
			foreach ($orderIds as $id){
				$order = $this->order->create()->getCollection()->addFieldToFilter('increment_id',$id)->getFirstItem();
				
				$invoices = $order->getInvoiceCollection();
				foreach ($invoices as $invoice) {
					$invoiceincrementid = $invoice->getIncrementId();
				}

				$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
				$invoice = $objectManager->create('Magento\Sales\Model\Order\Invoice');

				$invoiceobj = $invoice->loadByIncrementId($invoiceincrementid);
				$creditmemo = $this->creditMemoFacory->createByOrder($order);
				
				// Don't set invoice if you want to do offline refund
				$creditmemo->setInvoice($invoiceobj);			
				$this->creditmemoService->refund($creditmemo); 
				$data['status'] = 1;
			} 
		}catch (\Exception $e) {
				$message = $e->getMessage();
				$data['status'] = 0;
				$data['message'] = $message;
			}

		return $data;

	}
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $itemId itemId.
	 * @param string $reason.
     * @return customarray
     */
    public function cancelOrder($itemId,$reason)
	{
		$customerId = $this->tokenHelper->validateCustomer();
        if (empty($customerId) || !isset($customerId) || $customerId == "") {
            throw new InputException(__('Unauthorized Token'));
        }
			
		$item    = $this->itemFactory->create()->load($itemId);
		$orders  = $this->order->create()->load($item->getOrderId());
		
		if($customerId != $orders->getCustomerId()){
			throw new InputException(__('Unauthorized Customer.'));
		}
		
		$collection = $this->cancelOrder->create()->getCollection()->addFieldToFilter('item_id',$itemId);
		if($collection->getData())
		{
			throw new InputException(__('Order already canceled.'));
		}

		try{
			$amount = $item->getDeliveryCost() + ($item->getRowTotal() - $item->getDiscountAmount()) ;
			
			$reponce =  $this->sendRefund($orders,$item);
			$status = isset($reponce->transactionId)?$reponce->transactionId:0;
			if(!$status){
				throw new InputException(__("Can not able to refundOrder."));
			}
			
			
			$item->setCancelTransactionId($status);
			$item->save();
			
			$creditmemoItem = $this->itemCreationFactory->create();
			$creditmemoItem->setQty($item->getQtyOrdered())->setOrderItemId($item->getId());	
			$itemIdsToRefund[] = $creditmemoItem;
			$creationArguments = $this->creationArguments->setShippingAmount($item->getDeliveryCost());
			
			$this->refundOrder->execute(
				$orders->getId(),
				$itemIdsToRefund,
				false,
				false,
				null,
				$creationArguments
			);

			
			$cancelOrder = $this->cancelOrder->create();
			$sellerData = $this->sellerHelper->getSellerProductDataByProductId($item->getProductId());		
			$cancelOrder->setItemId($itemId);
			$cancelOrder->setOrderId($orders->getId());
			$cancelOrder->setCreatedAt(date('Y-m-d H:i:s'));
			if($sellerData){
				$sellerData = $sellerData->getFirstItem();
				$cancelOrder->setSellerId($sellerData->getSellerId());
			}
			
			$cancelOrder->setReason($reason);
			$cancelOrder->save();
			$data['status'] = 1;
			
			return $data;
		}catch(\Exception $e) {
			$message = $e->getMessage();
			throw new InputException(__("Unable to cancel order."));
		}
	}
	
	public function sendRefund($order,$item)
	{
		$quoteCoupon = $this->quoteCouponFactory->create();
		$quoteCoupon = $quoteCoupon->getCollection()->addFieldToFilter('item_id',$item->getQuoteItemId())->getFirstItem();
		
		$couponCreatedBy = '';
		if($quoteCoupon->getData()){
			if($quoteCoupon->getCouponId()){
				$couponCreatedBy = 'merchant';
			}else{
				$couponCreatedBy = 'habariadmin';
			}
		}
		
		$marketplaceProduct = $this->marketplaceProduct->getCollection();
		$marketplaceProduct->addFieldToFilter('mageproduct_id',$item->getProductId());
		$joinConditions = 'main_table.seller_id = marketplace_userdata.seller_id';
		$marketplaceProduct->getSelect()->joinLeft(['marketplace_userdata'],$joinConditions,[])
			->columns(["marketplace_userdata.entity_id as userentity_id","marketplace_userdata.shop_title"]);
		
		$marketplaceProduct = $marketplaceProduct->getFirstItem();
		
		$marchantId = 0;
		$shopTitle = '';
		if($marketplaceProduct->getData()){
			$marchantId = $marketplaceProduct->getUserentityId();
			$shopTitle = $marketplaceProduct->getShopTitle();
		}

		$options = $item->getProductOptions();
		$result = array();
		if ($options) {
			if (isset($options['options'])) {
				$result = array_merge($result, $options['options']);
			}
		
			if (isset($options['additional_options'])) {
				$result = array_merge($result, $options['additional_options']);
			}

			if (isset($options['attributes_info'])) {
				$result = array_merge($result, $options['attributes_info']);
			}
		}

		$productData = $this->_productFactory->create()->load($item->getProductId());
		if($item->getProductType() == 'configurable'){
			$childItem = $this->itemFactory->create()->getCollection()->addFieldToFilter('parent_item_id',$item->getId())->getFirstItem();
			$childProduct = $this->_productFactory->create()->load($childItem->getProductId());
			if($childProduct->getImage()){
				$imgUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $childProduct->getImage();
			}else{
				$imgUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $productData->getImage();						
			}
		}else{
			$imgUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $productData->getImage();
		}

		$shippingAddress 	= $order->getShippingAddress();


		
		$body = [
			"userId" 		=> $order->getCustomerId(),
			"productPrice"  => $item->getRowTotal(),
			"productPriceAfterDiscont"  => $item->getRowTotal() - $item->getDiscountAmount(),
			"discount"  	=> $item->getDiscountAmount(),
			"createdby"		=> $couponCreatedBy,
			"marchantId"	=> $marchantId,
			"shipping"		=> (int)$item->getDeliveryCost(),
			"orderId" 		=> $order->getIncrementId(),
			"type"			=> 'cancel',
			"itemId" 		=> $item->getId(),
			"productName"	=> $item->getName(),
			"paymentMethod" => $order->getPaymentMode(),
			"deliveryMethod" => $this->getMethodTitle($item->getDeliveryMethod()),
			"sellerName" 	=> $shopTitle,
			"productImage" => $imgUrl,
			"shippingAddress" => $shippingAddress->getData(),
			"customOption"	=> $result,
		];
			
		$responce = $this->emailHelper->sendRefund($body);
		return $responce;
		
	}
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $itemId itemId.
     * @return customarray
     **/
	public function cancelOrderDetail($itemId)
	{
		$customerId = $this->tokenHelper->validateCustomer();
        if (empty($customerId) || !isset($customerId) || $customerId == "") {
            throw new InputException(__('Unauthorized Token'));
        }
		
		$item    = $this->itemFactory->create()->load($itemId);
		$orders  = $this->order->create()->load($item->getOrderId());
		
		if(!$item->getId()){
			throw new InputException(__('Item Not found.'));
		}
		
		if($customerId != $orders->getCustomerId()){
			throw new InputException(__('Unauthorized Customer.'));
		}
		
		$productData = $this->_productFactory->create()->load($item->getProductId());
		$images = $productData->getMediaGalleryImages();
		
		$data['item_id'] = $itemId;
		$data['name'] = $item->getName();
		foreach($images as $img) {
			$data['image'][] = $img->getUrl();
		}


		if($item->getProductType() == 'configurable'){
			$data['image'] = array();
			$childItem = $this->itemFactory->create()->getCollection()->addFieldToFilter('parent_item_id',$item->getId())->getFirstItem();
			$childProduct = $this->_productFactory->create()->load($childItem->getProductId());
			
			$imagesChild = $childProduct->getMediaGalleryImages();

			if(!empty($imagesChild)){
				$data['image'] = [];
				foreach($imagesChild as $img) {
					$data['image'][] = $img->getUrl();
				}
			}
		}
		
		$collection = $this->reasonFactory->create()->getCollection();
		$collection->addFieldToFilter('type','cancel');
		
		$data['reasons'] =  $collection->getData();
		
		return $data;
	}
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $itemId itemId.
     * @return customarray
     **/
	public function printInvice($itemId)
	{
		$item    = $this->itemFactory->create()->load($itemId);
		$orders  = $this->order->create()->load($item->getOrderId());
		
		$invoices = $this->_objectManager->create('Magento\Sales\Model\ResourceModel\Order\Invoice\Collection')
						->addAttributeToSelect('*')
						->addAttributeToFilter('order_id',$orders->getId())
						->load();
					
		$pdf = $this->_objectManager->create('Webkul\Marketplace\Model\Order\Pdf\Invoice')->getPdf($invoices);
		$date = $this->_objectManager->get('Magento\Framework\Stdlib\DateTime\DateTime')->date('Y-m-d_H-i-s');
		header('Content-Transfer-Encoding: binary');  // For Gecko browsers mainly
		header("Content-type: application/pdf");
		echo $pdf->render();exit;
	}
	
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $itemId itemId.
     * @return customarray
     **/
	public function getRepeatItemOrder($itemId)
	{
		$customerId = $this->tokenHelper->validateCustomer();
        if (empty($customerId) || !isset($customerId) || $customerId == "") {
            throw new InputException(__('Unauthorized Token'));
        }
		
		
		$item    = $this->itemFactory->create()->load($itemId);
		$orders  = $this->order->create()->load($item->getOrderId());
		
		if(!$item->getId()){
			throw new InputException(__('Item Not found.'));
		}
		
		if($customerId != $orders->getCustomerId()){
			throw new InputException(__('Unauthorized Customer.'));
		}
		
		try{
			$quoteIdMask = $this->quoteIdMaskFactory->create();
			$cartId = $this->quoteManagement->createEmptyCart();
			$quoteIdMask->setQuoteId($cartId)->save();
			
			$quote = $this->quoteRepository->getActive($cartId);		
			$product = $this->_objectManager->create('Magento\Catalog\Model\Product')->load($item->getProductId());	
			$quote->setCheckoutMethod('guest');
			$request = [];
			foreach ($item->getProductOptions() as $key =>$option) {
				if($key=="info_buyRequest"){
					$request  = $option;
				}
			}
			
			$itemObject = $quote->addProduct($product, new \Magento\Framework\DataObject($request), 'full');
			$itemObject->setDeliveryMethod($item->getDeliveryMethod());
			$itemObject->setDeliveryCourier($item->getDeliveryCourier());
			$itemObject->setDeliveryComment($item->getDeliveryComment());
			$itemObject->setDeliveryCost($item->getDeliveryCost());
			$itemObject->setGiftWrap($item->getGiftWrap());
			
			$billingAddress1 = $orders->getBillingAddress()->getData();
			$shippingAddress1 = $orders->getShippingAddress()->getData();
			
			$billingAddress = $quote->getBillingAddress()->addData(
				[
					'customer_id' => $billingAddress1['customer_id'],
					'address_type' => $billingAddress1['address_type'],                   
					'firstname' => $billingAddress1['firstname'],
					'lastname' =>$billingAddress1['lastname'],
					'email' => $billingAddress1['email'],
					'street' => $billingAddress1['street'],
					'city' => $billingAddress1['city'],
					'country_id' => $billingAddress1['country_id'],
					'region_id' => $billingAddress1['region_id'],
					'postcode' => $billingAddress1['postcode'],
					'telephone' => $billingAddress1['telephone'],
				]
					);
					
					// Set Sales Order Shipping Address
			$shippingAddress = $quote->getShippingAddress()->addData(
				[
					'customer_id' => $shippingAddress1['customer_id'],
					'address_type' => $shippingAddress1['address_type'],
					'firstname' => $shippingAddress1['firstname'],             
					'lastname' =>$shippingAddress1['lastname'],
					'email' => $shippingAddress1['email'],
					'street' => $shippingAddress1['street'],
					'city' => $shippingAddress1['city'],
					'country_id' => $shippingAddress1['country_id'],
					'region_id' => $shippingAddress1['region_id'],
					'postcode' => $shippingAddress1['postcode'],
					'telephone' => $shippingAddress1['telephone'],
				]
			);
					
			$quote->setCustomerId(null)
				  ->setCustomerEmail($billingAddress1['email'])
				  ->setCustomerIsGuest(true)
				  ->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);
				  
			$shippingAddress = $quote->getShippingAddress();
			$shippingAddress->setCollectShippingRates(true)
							->collectShippingRates()->setShippingMethod($orders->getShippingMethod());
			$quote->setInventoryProcessed(false);
			$quote->collectTotals()->save();
			$quote->save();

			return [
				"cart_id" => $quoteIdMask->getMaskedId(),
				"confurmation" => $this->getOrderConfirmation($quoteIdMask->getMaskedId())
			];
		} catch (\Exception $e) {
			$message = $e->getMessage();
			throw new InputException(__($message));
		}
	}
	
	public function allowReturn($item,$order)
	{
		
		if($order->getState() != 'complete'){
			return false;
		}
		
		$allowedDays = $this->scopeConfig->getValue('rmasystem/parameter/days',\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
	
		$orderDate = $this->timezoneInterface->date($order->getCreatedAt())->format ("Y/m/d h:i:s");
		$orderDate = strtotime($orderDate);
		
		$currentDate = $this->timezoneInterface->date()->format ("Y/m/d h:i:s");
		$currentDate = strtotime($currentDate);

        $allowedSeconds = $allowedDays * 86400;
        $pastSecondFromToday = $orderDate + $allowedSeconds;
		

        if($pastSecondFromToday < $currentDate){
			return false;
		}
		
		return true;		
	}
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $trakingId trakingId.
     * @return customarray
     **/
	public function OrderTrakingDetails($trakingId)
	{
		try{
			$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
			$url = $this->scopeConfig->getValue('gtbank_sso/general/habari_partner_url',$storeScope);		
			$url = $url."/tracking";
			$client = new \GuzzleHttp\Client();
			$body['code'] = $trakingId;
			$body = json_encode($body);
			$payload = ['body' => $body, 'headers' => ['Content-Type' => 'application/json','Authorization' => 'Basic YXBpOm1jaF9WZGFlMlp3MWFISjRpWmIzaGZMdTZvcjhoWHd5NU1LNA==']];
			$res = $client->post($url, $payload);
			$data = json_decode($res->getBody()->getContents(),true);
			return $data;
		} catch (\Exception $e) {
			$message = $e->getMessage();
			throw new InputException(__($message));
		}
	}
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $quoteId quoteId.
	 * @param string $itemId itemId.
	 * @param string $discount discount.
	 * @param string $couponcode couponcode.
	 * @param string $type type
	 * @param string $redemptionType type
	 * @param float $maxRedemption maxRedemption
	 * @param int $limit limit
     * @return customarray
     **/
	public function applyDiscount($quoteId,$itemId,
	$discount,$couponcode,
	$type,
	$redemptionType="",
	$maxRedemption=0,
	$limit=0
	)
	{
		$quoteIdMask = $this->quoteIdMaskFactory->create()->load($quoteId, 'masked_id');
		$cartId = $quoteIdMask->getQuoteId();
		$quote = $this->quoteRepository->getActive($cartId);		
		$quoteItem = $quote->getItemById($itemId);		
		$quoteCoupon = $this->quoteCouponFactory->create();
		$quoteCoupon = $quoteCoupon->getCollection()->addFieldToFilter("item_id",$quoteItem->getId());
		$quoteCoupon = $quoteCoupon->getFirstItem();
		
		
		
		if($redemptionType == 'One time'){
			$quoteCouponTest = $this->quoteCouponFactory->create()->getCollection();
			$quoteCouponTest->addFieldToFilter('quote_id',$cartId)
			->addFieldToFilter('coupon_code',$couponcode);
			if(count($quoteCouponTest->getData()) > 0){
				throw new InputException(__("Coupon is not valid"));
			}
		}
		
		if($redemptionType == 'Limited'){
			$quoteCouponTest = $this->quoteCouponFactory->create()->getCollection();
			$quoteCouponTest->addFieldToFilter('quote_id',$cartId)
			->addFieldToFilter('coupon_code',$couponcode);
			if(count($quoteCouponTest->getData()) >= $limit){
				throw new InputException(__("Coupon is not valid"));
			}
		}
		
		
		if($quoteCoupon->getId()){
			$quoteCouponFactory = $quoteCoupon;
		}else{
			$quoteCouponFactory = $this->quoteCouponFactory->create();
		}
		
		$quoteCouponFactory->setItemId($quoteItem->getId());
		$quoteCouponFactory->setCouponId(0);			
		$quoteCouponFactory->setCouponCode($couponcode);
		$quoteCouponFactory->setProductId($quoteItem->getProductId());
		$quoteCouponFactory->setQuoteId($cartId);
		$quoteCouponFactory->setCreatedAt(date("Y-m-d H:i:s"));
		$quoteCouponFactory->setCouponValue($discount);
		$quoteCouponFactory->setMaxRedemption($maxRedemption);
		$quoteCouponFactory->setDiscountType($type);
		$quoteCouponFactory->save();
		
		
		$quote->save();
		$quote->collectTotals()->save();
		return ['status' => true];
	}
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $quoteId quoteId.
     * @return customarray
     **/
	public function marchantTotal($quoteId)
	{
	    $data = $this->products->getItemList($quoteId);
		
		$quoteIdMask = $this->quoteIdMaskFactory->create()->load($quoteId, 'masked_id');
		$cartId = $quoteIdMask->getQuoteId();
		$quote = $this->quoteRepository->getActive($cartId);
        $address = $quote->getShippingAddress();
	    
		$data = $this->products->getItemList($quoteId);
		$sellerData = [];
		
		$courier = [
			"customer_pickup" 	=> "Customer Pickup",
			"merchant_delivery" => "Merchant Delivery",
			"gpd" 				=> "Habari Partner Delivery",
		];	
		
		foreach($data as $d){
			$temp['shop_id'] = $d['shop_id'];			
			if($d['shop_id']){
				$marchantId = $this->getMerchentEntityId($d['shop_id']);
				$temp['merchant_id'] = $marchantId;
			}else{
				$temp['merchant_id'] = 0;
			}
			
			$total_discount = 0;
			$total_amount = 0;
			$total_amount_discount = 0;
			$delivery_cost = 0;
			$temp['item'] = [];
			foreach($d['data'] as $item)
			{
				$tempItem['product_id'] = $item['id'];
				$tempItem['item_id'] = $item['item_id'];
				$tempItem['name'] = $item['name'];
				$tempItem['quote_qty'] = $item['quote_qty'];
				$tempItem['quote_price'] = $item['quote_price_total_plain'];
				$tempItem['quote_price_discount'] = $item['quote_price_total_discount_plain'];
				$tempItem['applied_coupon'] = $item['applied_coupon'];
				$tempItem['discount'] = $item['discount'];
				$tempItem['option_data'] = $item['option_data'];
				$tempItem['image_url'] = $item['image_url'];
	
				$temp['item'][] = $tempItem;
				$delivery_method = $item['delivery_method'];
				$delivery_courier = $item['delivery_courier'];

				$total_discount = $total_discount + $item['discount'];
				$total_amount = $total_amount + $item['quote_price_total_plain'];
				$total_amount_discount = $total_amount_discount + $item['quote_price_total_discount_plain'];
				$delivery_cost = $delivery_cost + $item['delivery_cost'];
			}
			
			$temp['delivery_method'] = isset($courier[$delivery_method])?$courier[$delivery_method]:'';
			$temp['delivery_courier'] = isset($courier[$delivery_courier])?$courier[$delivery_courier]:'';

			
			$temp['total_amount'] = $total_amount;
			$temp['delivery_cost'] = $delivery_cost;
			$temp['total_amount_discount'] = $total_amount_discount;
			$temp['total_discount'] = $total_discount;
			
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
			$region = $objectManager->create('Magento\Directory\Model\Region')
                        ->load($address->getRegionId());
            
            $street = $address->getStreet();
            $streetAddress_1 = $street[0];
			$streetAddress_2 = isset($street[1])?$street[1]:"";
          
            $temp['shipping_details']['name'] = $address->getFirstname()." ".$address->getLastname();
			$temp['shipping_details']['phone'] = $address->getTelephone();
			$temp['shipping_details']['street'] = $streetAddress_1." ".$streetAddress_2;
			$temp['shipping_details']['city'] = $address->getCity();
			$temp['shipping_details']['state'] = $region->getDefaultName();
			$temp['shipping_details']['state_code'] = $region->getCode();
			$temp['shipping_details']['country'] = "nigeria";
			
			$sellerData[] = $temp;
		}
		
		return $sellerData;
	}
	
	public function getMerchentEntityId($shopId){
		$seller = $this->_objectManager->create('Webkul\Marketplace\Model\Seller')->getCollection()->addFieldToFilter('seller_id',$shopId)->getFirstItem();
		return $seller->getEntityId();
	}
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $orderIds orderIds.
     * @return customarray
     **/
	public function orderDetailsById($orderIds)
	{
		$orderIds = explode(',',$orderIds);
		 //print_r($orderIds);exit;
		
		foreach($orderIds as $id){
			$order = $this->order->create()->load($id);
			//print_r($order->debug());exit;
			$temp['orderid'] = $id;
			foreach($order->getAllVisibleItems() as $item)
			{
				$tempdata['product_name'] = $item->getName();
				$tempdata['product_price'] = $item->getBasePrice();
				$tempdata['product_price_total'] = $item->getRowTotal();
				$tempdata['discount'] = $item->getDiscountAmount();
				$tempdata['shipping_cost'] = $item->getDeliveryCost();
				$tempdata['couponcode'] = $this->getCouponCode($item->getQuoteItemId());
				$tempdata['qty'] = $item->getQtyOrdered();
				$tempdata['merchant_name'] = $this->getMerchantName($item->getProductId());
				$temp['data'][] = $tempdata;
			}
			
			$orderData[] = $temp;
		}
		
		return $orderData;
	}
	
	public function getMerchantName($productId)
	{
		$marketplaceProduct = $this->marketplaceProduct->getCollection()
								->addFieldToFilter('mageproduct_id',$productId)
								->getFirstItem();
								
		if($sellerData = $marketplaceProduct->getData()){
			$sellerData = $this->sellerHelper->getSeller($sellerData['seller_id']);
			$shop_title = isset($sellerData['shop_title']) ? $sellerData['shop_title']: '';
		}else{
			$shop_title = '';
		}
		return $shop_title;
	}
	
	public function getCouponCode($quoteItemId)
	{
		$quoteCoupon = $this->quoteCouponFactory->create();
		$quoteCoupon = $quoteCoupon->getCollection()->addFieldToFilter("item_id",$quoteItemId)->getFirstItem();
		
		if($quoteCoupon = $quoteCoupon->getData()){
			return $quoteCoupon['coupon_code'];
		}
	}
}
