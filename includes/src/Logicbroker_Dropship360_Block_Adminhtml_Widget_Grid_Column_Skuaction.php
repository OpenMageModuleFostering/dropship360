<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Skuaction extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{
  
	/**
     * Renders column
     *
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
    	
    	if($row->getData('product_sku'))
    	{
    		$label = ($this->getColumn()->getIndex() == 'lb_vendor_sku') ? $row->getData('lb_vendor_sku'):$row->getData('product_sku');
    	}
    	else
    	{	$productid = Mage::getModel('catalog/product')->getIdBySku(trim($row->getData('sku')));
    		$vendorScreenUrl = ($this->getColumn()->getIndex() == 'lb_vendor_sku') ? 'back/edit/tab/product_info_tabs_vendor_tab':'';
    		$labeltxt = ($this->getColumn()->getIndex() == 'lb_vendor_sku') ? $row->getData('lb_vendor_sku'):$row->getData('sku');
    		$label = '<a href="'.$this->getUrl('adminhtml/catalog_product/edit',array('id'=>$productid)).$vendorScreenUrl.'" target="_blank"><strong>'.$labeltxt.'</strong></a>';
    	}
    		return $label;  
    }
}
