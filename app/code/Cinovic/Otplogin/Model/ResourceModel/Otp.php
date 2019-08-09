<?php
namespace Cinovic\Otplogin\Model\ResourceModel;

class Otp extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
	/**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(){
    	$this->_init('user_otp','entity_id');
    }
}