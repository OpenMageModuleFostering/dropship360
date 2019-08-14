<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Sourcing extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_sourcing';
    $this->_blockGroup = 'dropship360';
    $this->_headerText = Mage::helper('dropship360')->__('PO Management');
    $this->_addButtonLabel = Mage::helper('dropship360')->__('Add Supplier');
    parent::__construct();
    $this->removeButton('add');
  }
  
  public function getLbOrderItemsDetails($item){
  	
  	$lbItemCollection = Mage::getModel('dropship360/orderitems')->getCollection()->addFieldTofilter('item_order_id',$item->getOrderId())->addFieldTofilter('sku',$item->getSku());
  	$lbItemCollection->getSelect()->joinLeft( array('lbr'=> Mage::getSingleton('core/resource')->getTableName('dropship360/ranking')), "main_table.lb_vendor_code = lbr.lb_vendor_code",array("lbr.lb_vendor_name"));
  	$lbItemCollection->getSelect()->limit(1);
  	return $lbItemCollection->getFirstItem ();
  }
}
