<?php
namespace Gtbank\Orders\Observer;

use Magento\Framework\Event\ObserverInterface;

class SalesOrderAfterSave implements ObserverInterface
{
    public function __construct(
        \Gtbank\Orders\Helper\Data $emailHelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
         \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->emailHelper = $emailHelper;
        $this->_objectManager = $objectManager;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        if ($order->getStatus() == 'packed') {
            return;
        }

        if ($order->getOrigData('status') != $order->getStatus()) {
            if (!$order->getCustomerId()) {
                return;
            }

            $orderItems = $order->getAllVisibleItems();
            $method = '';

            foreach ($orderItems as $orderItem) {
                $method = $orderItem->getDeliveryMethod();
                $deliveryCourier = $orderItem->getDeliveryCourier();
                $rateId = $orderItem->getRateId();
                $item = [];
                $item['name'] = $orderItem->getName();
                $item['weight'] = $orderItem->getRowWeight();
                $item['item_type_code'] = "letter_or_document";
                $item['package_size_code'] = "medium";
                $item['quantity'] = (int) $orderItem->getQtyOrdered();
                $body['items'][] = $item;
                $productId = $orderItem->getProductId();
                $itemIds[] = ['item_id' => $orderItem->getId(),'name' => $orderItem->getName()];
            }

            $marketplaceCollection = $this->_objectManager
                ->create('Webkul\Marketplace\Model\Product')->getCollection()->addFieldToFilter('mageproduct_id', $productId)->getFirstItem();

            $sellerId = 0;
            if ($marketplaceCollection->getSellerId()) {
                $sellerId = $marketplaceCollection->getSellerId();
            }

            $address = [];
            $magentoToken = 0;
            if ($sellerId) {
                $sellerData = $this->_objectManager->create('Webkul\Marketplace\Model\Seller')
                    ->getCollection()
                    ->addFieldToFilter('seller_id', $sellerId)
                    ->getFirstItem();

                $magentoToken = $sellerData->getId();
                $region = $this->_objectManager->create('Magento\Directory\Model\Region')->getCollection();
                $region = $region->addFieldToFilter('default_name', $sellerData->getAddressState())->getFirstItem();
                $state = '';
                $stateCode = '';
                if ($region->getData()) {
                    $state = $region->getDefaultName();
                    $stateCode = $region->getCode();
                }

                $address['origin_name'] = $sellerData->getShopTitle();
                $address['origin_phone'] = $sellerData->getContactNumber();
                $address['origin_street'] = $sellerData->getCompanyLocality();
                $address['origin_city'] = $sellerData->getAddressCity();
                $address['origin_state'] = $state;
                $address['origin_state_code'] = $stateCode;
                $address['origin_country'] = "nigeria";
            }

            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $url = $this->scopeConfig->getValue('gtbank_sso/general/api_base_url', $storeScope);
            $token = $this->scopeConfig->getValue('gtbank_sso/general/api_token', $storeScope);
            $url = $url . '/notification/send-order-status-notification';

            if ($order->getStatus() == 'dispatched' && $method == 'merchant_delivery') {
                $body = [
                    "orderId" => $order->getIncrementId(),
                    "merchantAddress" => $address,
                    "type" => "order-on-the-way-merchant-delievery",
                    "sendBoxApi" => "",
                    "items" => $itemIds
                ];

                $client = new \GuzzleHttp\Client();
                $body = json_encode($body);
                $payload = ['body' => $body, 'headers' => ['Content-Type' => 'application/json', 'Authorization' => $token]];
                $res = $client->post($url, $payload);
            }

            if ($order->getStatus() == 'processing' &&  $method == 'gpd_cancel') {
                $body = [
                    "userId" => $order->getCustomerId(),
                    "templateName" => "shipment-cancellation",
                    "orderId" => $order->getIncrementId(),
                ];

                $responce = $this->emailHelper->sendEmail($body);

                $body = [
                    "userId" => $magentoToken,
                    "templateName" => "merchant-shipment-cancellation",
                    "orderId" => $order->getIncrementId(),
                ];

                $responce = $this->emailHelper->sendEmail($body, '/notification/notify-merchant');
            }

            if ($order->getStatus() == 'dispatched' && $method == 'gpd') {
                $body = [
                    "orderId" => $order->getIncrementId(),
                    "merchantAddress" => $address,
                    "type" => "order-on-the-way-sendbox",
                    "sendBoxApi" => "",
                    "items" => $itemIds
                ];

                $client = new \GuzzleHttp\Client();
                $body = json_encode($body);
                $payload = [
                    'body' => $body,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => $token
                    ]
                ];
                $res = $client->post($url, $payload);
            }

            if ($order->getStatus() == 'dispatched' &&  $method == 'customer_pickup') {
                $body = [
                    "userId" => $order->getCustomerId(),
                    "templateName" => "order-delivered",
                    "additionalData" => ["status" => 'delivered'],
                    "orderId" => $order->getIncrementId(),
                ];

                $responce = $this->emailHelper->sendEmail($body);
            }

            if ($order->getStatus() == 'delivered') {
                $body = [
                    "userId" => $order->getCustomerId(),
                    "templateName" => "order-delivered",
                    "additionalData" => ["status" => $order->getStatus()],
                    "orderId" => $order->getIncrementId(),
                ];

                $responce = $this->emailHelper->sendEmail($body);
            }
        }

        return $this;
    }
}
