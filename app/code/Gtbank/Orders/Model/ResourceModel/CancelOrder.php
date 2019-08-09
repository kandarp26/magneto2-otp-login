<?php
namespace Gtbank\Orders\Model\ResourceModel;

class CancelOrder extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
	/**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(){
    	$this->_init('cancel_order','id');
    }
}