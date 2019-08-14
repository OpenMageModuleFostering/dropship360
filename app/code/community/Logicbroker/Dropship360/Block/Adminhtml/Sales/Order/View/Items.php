<?php
/**
 * Adminhtml order items grid overwrite for Adding Logicbroker order item details
 *
 * @category   Logicbroker
 * @package    Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Sales_Order_View_Items extends Mage_Adminhtml_Block_Sales_Order_View_Items
{
    /**
     * Retrieve order items collection
     *
     * @return unknown
     */
    public function getItemsCollection()
    {
		$id = $this->getRequest()->getParam('order_id');
		$collection = Mage::getResourceModel('sales/order_item_collection');
		$collection->getSelect()->joinLeft( array('lbs'=> Mage::getSingleton('core/resource')->getTableName('logicbroker/orderitems')), "main_table.item_id = lbs.item_id",array("lbs.lb_item_status", "lbs.lb_vendor_sku","lbs.lb_vendor_code"))->where("main_table.order_id =".$id);
		$collection->getSelect()->joinLeft( array('lbr'=> Mage::getSingleton('core/resource')->getTableName('logicbroker/ranking')), "lbs.lb_vendor_code = lbr.lb_vendor_code",array("lbr.lb_vendor_name"));
		//return $this->getOrder()->getItemsCollection();	
		return $collection;
    }
}