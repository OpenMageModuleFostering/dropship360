<?php
/**
 * Adminhtml sourcing history view panel
 *
 * @category    Logicbroker
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Block_Adminhtml_Sourcing_History_View_Form extends Mage_Adminhtml_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('logicbroker/item_order_history.phtml');
    }
	
	/*
	 * To get the order item status history from serialized to unserialized form
	 * 
	 * @return array
     */	 
	public function getHistory()
	{
		$lbItemId = Mage::app()->getRequest()->getParam('lb_item_id');
		$itemStatusHistory = Mage::getModel ( 'dropship360/orderitems' )->load($lbItemId, 'item_id')->getItemStatusHistory();
		return unserialize($itemStatusHistory);
	}
	
	
}	