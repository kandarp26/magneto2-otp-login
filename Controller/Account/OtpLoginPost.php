<?php
namespace Cinovic\Otplogin\Controller\Account;


class OtpLoginPost extends \Magento\Framework\App\Action\Action
{
    protected $_customer;
    protected $_storemanager;
    protected $_coreSession;
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\CustomerFactory $customer,
        \Magento\Store\Model\StoreManagerInterface $storemanager,
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->_objectManager = $objectManager;
        $this->_customer = $customer;
        $this->_storemanager = $storemanager;
        parent::__construct($context);
    }
    public function execute()
    {
        //get customer id
        $email = $this->getRequest()->getParam('email');
        $websiteID = $this->_storemanager->getStore()->getWebsiteId();
        $customer = $this->_customer->create()->setWebsiteId($websiteID)->loadByEmail($email);
        $customerId = $customer->getId();

        //set session
        $session = $this->_objectManager->get('Magento\Framework\Session\SessionManagerInterface');
        $session->setTestKey($email);

        $category = $this->_objectManager->create('Magento\Customer\Model\customer');
        $ct = $category->getCollection()->addFieldToFilter('email', $email)->addFieldToSelect('email')->getData();
        if (!empty($ct)) {
            //update status
            $category = $this->_objectManager->create('Cinovic\Otplogin\Model\Otp');
            $customerstatus = $category;
            $customerstatus->load($customerId, 'customer');
            $customerstatus->setStatus('0');
            $customerstatus->save();
            //send otp
            $otp_code = mt_rand(10000, 99999);
            $otp = base64_encode($otp_code);
            $question = $this->_objectManager->create('Cinovic\Otplogin\Model\Otp');
            $question->setOtp($otp);
            $question->setCustomer($customerId);
            $question->save();

            //send email
            $receiverInfo = [
                'name' => 'name',
                'email' => $email,
            ];
            $senderInfo = [
                'name' => 'name',
                'email' => 'sender@address.com'
            ];
            $emailTemplateVariables = array();
            $emailTempVariables['myvar1'] = $otp_code;
            $this->_objectManager->get('Cinovic\Otplogin\Helper\Email')->yourCustomMailSendMethod(
                $emailTempVariables,
                $senderInfo,
                $receiverInfo
            );
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('otplogin/account/otp');
            return $resultRedirect;
        } else {
            $this->messageManager->addError(__('Wrong Email.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/account/login');
            return $resultRedirect;
        }
    }
}
