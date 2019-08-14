<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Ranking extends Mage_Core_Model_Abstract
{
    protected function _construct(){

       $this->_init("logicbroker/ranking");
    }
	
    
    public function setVendorRanking($reqData,$model)
    {
    	
    	
    	
    }
    public function rearrangeRank($value, $rank){		
		if($rank > 0 && $rank < $value->getRanking() ){
			$rankData = Mage::getModel('logicbroker/ranking')->load($value->getId());
			$rankData->setRanking($rank);
			$rankData->save();
			$rank++;	
		}	
    }
    
    public function _checkDuplicateVendor($postData) {
    
    	
    }
    
    public function getVendorCollection($type)
    {
    	//$vendorCollection = Mage::getModel('logicbroker/supplier')->getCollection();
    	//$vendorCollection->getSelect ()->joinleft ( array ('lbRanking' => Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/ranking' )), 'lbRanking.lb_vendor_code = main_table.lb_vendor_code', array ('*') )->where('lbRanking.is_dropship = "'.$type.'" and lbRanking.is_active = "yes"');
    	$orderBy = ($type == 'yes') ? 'ranking asc':'id asc';
    	$vendorCollection =  $this->getCollection()->addFieldToFilter('is_dropship',$type)->addFieldToFilter('is_active','yes');
    	$vendorCollection->getSelect()->order($orderBy);
    	
    	$arrVendor = array();
    	if($vendorCollection->count() > 0 ){
    	
    		foreach ($vendorCollection as $vendor) {
    			$arrVendor[] = array('name'=>$vendor->getLbVendorName(),'code'=>$vendor->getLbVendorCode());
    			//$arrVendor[]['code'] = $vendor->getLbVendorCode();
    			
    		}
    	}
    	return $arrVendor;
    	
    }
    
    public function addAttributeOptions(){
    	
    	$optionsBackup = array();
    	$valueDeafult = array ('MagVendID1','MagVendID2','MagVendID3','MagVendID4','MagVendID5','MagVendID6','MagVendID7','MagVendID8','MagVendID9','MagVendID10','MagVendID11','MagVendID12','MagVendID13','MagVendID14','MagVendID15','MagVendID16','MagVendID17','MagVendID18','MagVendID19','MagVendID20');
    	$eavModel=Mage::getModel('eav/entity_setup','core_setup');
    	$attributeId = $eavModel->getAttributeId(Mage::getModel ( 'eav/config' )->getEntityType ( 'catalog_product' )->getEntityTypeId (), 'lb_vendor_code_list');
    	
    	$optionCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')->setAttributeFilter($attributeId)
    	->setPositionOrder('asc', true)
    	->load();
    	if($optionCollection->count() > 0)
    	{
    		foreach($optionCollection as $option){
    			//remove if options value is empty
    			$optionsBackup[] = $option->getValue();
    			$option->delete();
    		}
    	}
    	$value = array_unique(array_merge($valueDeafult,$optionsBackup));
    	$option = array (
    			'values' =>$value
    	);
    	$option['attribute_id'] = $attributeId;
    	$eavModel->addAttributeOption($option);
    }
    
    public function addPreDefineVendorList(){
    	
    	$preDefinedArr = array(array('MagVendID1','Vendor 1',1),array('MagVendID2','Vendor 2',2),array('MagVendID3','Vendor 3',3),array('MagVendID4','Vendor 4',4),array('MagVendID5','Vendor 5',5),array('MagVendID6','Vendor 6',6),array('MagVendID7','Vendor 7',7),array('MagVendID8','Vendor 8',8),array('MagVendID9','Vendor 9',9),array('MagVendID10','Vendor 10',10),array('MagVendID11','Vendor 11',11),array('MagVendID12','Vendor12',12),array('MagVendID13','Vendor 13',13),array('MagVendID14','Vendor 14',14),array('MagVendID15','Vendor15',15),array('MagVendID16','Vendor 16',16),array('MagVendID17','Vendor 17',17),array('MagVendID18','Vendor 18',18),array('MagVendID19','Vendor 19',19),array('MagVendID20','Vendor 20',20));
    	
    	$arrRequired = array();
    	foreach ($preDefinedArr as $data)
    	{
    		$arrRequired[] = array('lb_vendor_code'=>$data[0] ,'lb_vendor_name'=>$data[1],'ranking'=>$data[2]);
    	}



    	foreach($arrRequired as $value){
    		$this->saveVendorDetails($value);
    	}	
    }
	
	protected function saveVendorDetails($value){
		$vendorDetail = Mage::getModel('logicbroker/ranking')->load($value['lb_vendor_code'],'lb_vendor_code');    		
		if(!$vendorDetail->getId())
		{
			$vendorDetail->setLbVendorCode($value['lb_vendor_code']);
			$vendorDetail->setLbVendorName($value['lb_vendor_name']);
			$vendorDetail->setRanking($value['ranking']);
            $vendorDetail->setIsDropship('no');
			$vendorDetail->setUpdatedAt(now());
			$vendorDetail->save();
		}
	}
}
	 