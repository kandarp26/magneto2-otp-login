<?php

namespace Cinovic\Otplogin\Controller\Account;

use \Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Http\Context;
use Magento\Framework\Session\SessionManagerInterface;

class OtpPost extends \Magento\Framework\App\Action\Action
{
    protected $_pageFactory;
    protected $_customer;
    protected $_storemanager;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\ResultFactory $result,
        \Magento\Customer\Model\CustomerFactory $customer,
        \Magento\Store\Model\StoreManagerInterface $storemanager,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        SessionManagerInterface $session,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->_objectManager = $objectManager;
        $this->_pageFactory = $pageFactory;
        $this->resultRedirect = $result;
        $this->_customer = $customer;
        $this->_storemanager = $storemanager;
        $this->scopeConfig = $scopeConfig;
        $this->_sessionManager = $session;
        return parent::__construct($context);
    }

    public function execute()
    {
        //get session
        $sessionemail = $this->_sessionManager->getTestKey();
        //customer id
        $websiteID = $this->_storemanager->getStore()->getWebsiteId();
        $customer = $this->_customer->create()->setWebsiteId($websiteID)->loadByEmail($sessionemail);
        $sessioncustomerId = $customer->getId();
        //get otp
        $email = $this->getRequest()->getParam('otp');
        $otp = base64_encode($email);
        $category = $this->_objectManager->create('Cinovic\Otplogin\Model\Otp');
        $cate = $category->getCollection()->addFieldToFilter('otp', $otp)->getData();
        $status = $category->getCollection()->addFieldToFilter('otp', $otp)->addFieldToSelect('status')->getData();

        //config expire time
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
        $expiredtime = $this->scopeConfig->getValue("cinovic_otplogin/general/section_data", $storeScope);

        if (!empty($cate)) {
            $created_at = (int) strtotime($cate[0]['created_at']);
            $now = time();
            $now = (int) $now;
            $expire = $now -= $created_at;
            $customerId = $cate[0]['customer'];
            $otpstatus = $status[0]['status'];
            if ($sessioncustomerId == $customerId) {
                if ($otpstatus == 1) {
                    if ($expire <= $expiredtime) {
                        $customer = $customerId;
                        $customer = $this->_objectManager->create('Magento\Customer\Model\Customer')->load($customer);
                        $customerSession = $this->_objectManager->create('Magento\Customer\Model\Session');
                        $customerSession->setCustomerAsLoggedIn($customer);
                        $customerSession->regenerateId();
                        $resultRedirect = $this->resultRedirect->create(ResultFactory::TYPE_REDIRECT);
                        $resultRedirect = $this->resultRedirectFactory->create();
                        $resultRedirect->setPath('/');
                        return $resultRedirect;
                    } else {
                        $this->messageManager->addError(__('Otp Expiry.'));
                        $resultRedirect = $this->resultRedirectFactory->create();
                        $resultRedirect->setPath('otplogin/account/otp');
                        return $resultRedirect;
                    }
                } else {
                    $this->messageManager->addError(__('Wrong Otp.'));
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setPath('otplogin/account/otp');
                    return $resultRedirect;
                }
            } else {
                $this->messageManager->addError(__('Wrong Otp.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('otplogin/account/otp');
                return $resultRedirect;
            }
        } else {
            $this->messageManager->addError(__('Wrong Otp.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('otplogin/account/otp');
            return $resultRedirect;
        }
    }
}
