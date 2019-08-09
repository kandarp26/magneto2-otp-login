<?php
namespace Gtbank\Orders\Model\ResourceModel\CancelOrder;
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
	/**
     * Define resource model
     *
     * @return void
     */
	protected function _construct()
	{
		$this->_init('Gtbank\Orders\Model\CancelOrder', 'Gtbank\Orders\Model\ResourceModel\CancelOrder');
	}


}