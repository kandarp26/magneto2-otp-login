<?php
namespace Gtbank\Orders\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Exception\InputException;
use Magento\Framework\UrlInterface;

class Data extends AbstractHelper
{
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        UrlInterface $_urlInterFace
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->customerFactory = $customerFactory;
        $this->logger = $logger;
        $this->_urlInterFace = $_urlInterFace;
    }

    public function getToken()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue('gtbank_sso/general/api_token', $storeScope);
    }

    public function sendRefund($body)
    {
        try {
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $url = $this->scopeConfig->getValue('gtbank_sso/general/api_base_url', $storeScope);
            $url = $url . '/orders/refund';

            $client = new \GuzzleHttp\Client();
            $body = json_encode($body);

            $payload = ['body' => $body, 'headers' => ['Content-Type' => 'application/json','Authorization' => $this->getToken()]];
            $res = $client->post($url, $payload);
            return json_decode($res->getBody()->getContents());
        } catch (\Exception $e) {
            $this->logger->error('Failed to refund', ['message' => $e->getMessage()]);
            throw new InputException(__($e->getMessage()));
        }
    }

    public function sendCancel($body)
    {
        try {
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $url = $this->scopeConfig->getValue('gtbank_sso/general/api_base_url', $storeScope);

            $url = $url . '/orders/cancel';

            $client = new \GuzzleHttp\Client();
            $body = json_encode($body);

            $payload = ['body' => $body, 'headers' => ['Content-Type' => 'application/json','Authorization' => $this->getToken()]];
            $res = $client->post($url, $payload);
            return json_decode($res->getBody()->getContents());
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel', ['message' => $e->getMessage()]);
            throw new InputException(__($e->getMessage()));
        }
    }

    public function sendEmail($body, $apiurl = '')
    {
        try {
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $url = $this->scopeConfig->getValue('gtbank_sso/general/api_base_url', $storeScope);

            if ($apiurl != '') {
                $url = $url . $apiurl;
            } else {
                $url = $url . '/notification/notify';
            }
            $client = new \GuzzleHttp\Client();
            $body = json_encode($body);
            $payload = ['body' => $body, 'headers' => ['Content-Type' => 'application/json','Authorization' => $this->getToken()]];
            $res = $client->post($url, $payload);
            return json_decode($res->getBody()->getContents());
        } catch (\Exception $e) {
            $this->logger->error('Failed to send notification', ['message' => $e->getMessage()]);
            throw new InputException(__($e->getMessage()));
        }
    }
}
