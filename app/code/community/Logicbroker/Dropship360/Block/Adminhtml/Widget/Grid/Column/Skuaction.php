<?php

/**
 * Adminhtml grid item renderer
 *
 * @category   Orange
 * @package    Orange_Prepaidcards
 * @author     Anil Pawar <anilpa@cybage.com>
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
    	$vendorScreenUrl = ($this->getColumn()->getIndex() == 'lb_vendor_sku') ? 'back/edit/tab/product_info_tabs_vendor_tab':'';
    	$label = ($this->getColumn()->getIndex() == 'lb_vendor_sku') ? $row->getData('lb_vendor_sku'):$row->getData('product_sku');
        return $label;     // this fix is for ticket 734
     /*	$sku = $row->getData('product_sku');
    	$productObject = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku); 
    	if ($productObject) 
    		$out = '<a target="_blank" rel="external" href="'.Mage::helper('adminhtml')->getUrl('adminhtml/catalog_product/edit', array('id' => $productObject->getId())).$vendorScreenUrl.'">'.$label.'</a>';
    	else
    		$out = $label;
    	return $out; */ 
        
        
		
        
    }
}
