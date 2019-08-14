<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Uploadvendor extends Mage_Core_Model_Abstract
{
	
	protected function _construct()
	{
		$this->_init("dropship360/uploadvendor");
	}
	
	public function getDatabaseConnection() 
	{
		return Mage::getSingleton ( 'core/resource' )->getConnection ( 'core_write' );
	}
	
    /* method for bulk assignment of vendor code to all product*/
    public function prepareBulkassignmentCollection($vendorCode)
    {
		$numberOfRecords = 200;
    	$Lbsku = array();
    	$magentoSku = array();
    	$magentoSkuCollection = Mage::getModel('catalog/product');
    	if(count($Lbsku) > 0)
   	 		$productCollection = $magentoSkuCollection->getCollection()->addAttributeToSelect('sku')->addAttributeToFilter('sku', array('nin' => $Lbsku));
    	else
    		$productCollection = $magentoSkuCollection->getCollection()->addAttributeToSelect('sku')->addAttributeToFilter('type_id','simple');
    	
    	if($productCollection->getSize() > 0){
    		$chunkSkus = array_chunk($productCollection->getData(), $numberOfRecords);
    		
    		foreach($chunkSkus as $skus)
    		{
    		foreach($skus as $mageSku){
    			$magentoSku[] = $mageSku['sku'];
    				}
    			}
    		}
    	return $magentoSku;
    }
	}