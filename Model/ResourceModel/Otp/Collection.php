<?php
namespace Cinovic\Otplogin\Model\ResourceModel\Otp;
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
	/**
     * Define resource model
     *
     * @return void
     */
	protected function _construct()
	{
		$this->_init('Cinovic\Otplogin\Model\Otp', 'Cinovic\Otplogin\Model\ResourceModel\Otp');
	}


}