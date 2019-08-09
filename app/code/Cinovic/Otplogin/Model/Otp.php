<?php
namespace Cinovic\Otplogin\Model;
use Magento\Framework\Model\AbstractModel;
class Otp extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('Cinovic\Otplogin\Model\ResourceModel\Otp');
    }
}