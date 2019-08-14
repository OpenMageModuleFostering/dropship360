<?php
/**
 * Logicbroker
 *

 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Model_System_Config_Source_Vendorlist
{
    public function toOptionArray($addEmpty = true)
    {
    	$options =array();
    	$collectionVendor = Mage::getModel ( 'logicbroker/inventory' )->getCollection ();
    	
    	
    	$collectionVendor->getSelect ()->joinleft ( array (
    			'lbRanking' => Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/ranking' )
    	), 'lbRanking.lb_vendor_code = main_table.lb_vendor_code', array (
    			'*'
    	) )->where('lbRanking.is_dropship = ?','yes');
    	
    	$collectionVendor->getSelect ()->group('main_table.lb_vendor_code');
    	if($collectionVendor->count() > 0){
    	foreach ($collectionVendor as $vendor) {
    		$options[] = array(
    				'label' => $vendor->getLbVendorName(),
    				'value' => $vendor->getLbVendorCode()
    		);
    	}
    	}
    	array_unshift($options,array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--')),array('value' => 'none', 'label' => Mage::helper('logicbroker')->__('None')));
 		return $options;
    }
    
    public function vendorList($addEmpty = true,$sku)
    {
    	$vendorModel = Mage::getModel('logicbroker/ranking')->getCollection();
    	$vendorModel->getSelect ()->where('main_table.lb_vendor_code not in (select lb_vendor_code from '.Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/inventory' ).' where product_sku = ?)',$sku);
    	$options = array();
    	if($vendorModel->count() > 0 ){
    	foreach ($vendorModel as $vendor) {
    		$options[] = array(
    				'label' => $vendor->getLbVendorCode().'--'.$vendor->getLbVendorName(),
    				'value' => $vendor->getLbVendorCode()
    		);
    	}
    	}
    	array_unshift($options,array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--'))
    	);
    	return $options;
    }
    
    public function vendorListSourcing($addEmpty = true,$sku)
    {
    	$options =array();
    	$collectionVendor = Mage::getModel ( 'logicbroker/inventory' )->getCollection ()->addFieldToFilter('product_sku',$sku);
    	$collectionVendor->getSelect ()->joinleft ( array (
    			'lbRanking' => Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/ranking' )
    	), 'lbRanking.lb_vendor_code = main_table.lb_vendor_code', array (
    			'*'
    	) )->where('lbRanking.is_dropship = ?','yes');
    	 
    	$collectionVendor->getSelect ()->group('main_table.lb_vendor_code');
    	
    	if($collectionVendor->count() > 0){
    	foreach ($collectionVendor as $vendor) {
    		$options[] = array(
    				'label' => $vendor->getLbVendorName(),
    				'value' => $vendor->getLbVendorCode()
    		);
    	}
    	}
    	array_unshift($options,array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--')));
 		return $options;
    }
    
    public function getAllVendor($addEmpty = true)
    {
    	$options =array();
    	$collectionVendor = Mage::getModel ( 'logicbroker/ranking' )->getCollection ();
    	
    	if($collectionVendor->count() > 0){
    		foreach ($collectionVendor as $vendor) {
    			$options[] = array(
    					'label' => $vendor->getLbVendorCode().'--'.$vendor->getLbVendorName(),
    					'value' => $vendor->getLbVendorCode()
    			);
    		}
    	}
    	array_unshift($options,array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--')));
    	return $options;
    }
}
