<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Block_Adminhtml_Inventory_Edit_Tab_Vendortabhistory extends Mage_Adminhtml_Block_Widget
{
    public function __construct()
    {
        parent::__construct();
    }


    protected function _prepareLayout()
    {
       return parent::_prepareLayout();
    }

    public function getInventoryLog(){
    	
    	$collection =  Mage::getModel('dropship360/inventorylog')->getCollection()->addFieldToFilter('product_sku',Mage::getModel('catalog/product')->load($this->getProductId())->getSku());
    	$collection->getSelect()->order('updated_at desc');
    	
    	return $collection;
    }   
}