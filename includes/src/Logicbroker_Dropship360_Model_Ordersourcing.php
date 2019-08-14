<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Ordersourcing extends Mage_Core_Model_Abstract
{
    protected function _construct()
	{
       $this->_init("logicbroker/ordersourcing");
    }
	
    public function prepareOrderCollection($crontype)
    {
    	$processingOrders = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('status',$crontype);
    	$processingOrders->getSelect()->limit('20');
    	return $processingOrders; 	 
    }
    public function prepareItemCollection($crontype)
    {
    	$processingItem = Mage::getModel('logicbroker/orderitems')->getCollection()->addFieldToFilter('lb_item_status',$crontype);
    	$processingItem->getSelect()->limit('20');
    	return $processingItem;
    }
	
}
	 