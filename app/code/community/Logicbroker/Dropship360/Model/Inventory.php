<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Inventory extends Mage_Core_Model_Abstract
{
    protected $arrData = array();
	protected $_productInventoryAdd = 0;
	protected $_productInventorySubtract = 0;
	protected $_productInventoryUpdate = 0;
	protected function _construct(){

       $this->_init("logicbroker/inventory");
    }

    public function prepareInventoryTable($restReqest){
    	
    	$result = $this->prepareData($restReqest);
    	$this->updateProductStock();
    	return $result;
    	
    }
    
    protected function updateProductStock(){
    	
    	$dataCollection = Mage::getModel('logicbroker/inventory')->getCollection();
    	$stockData = array();
    	if($dataCollection->count() < 0){
    		return;
    	}
    	foreach($dataCollection as $stock){
    		if(array_key_exists($stock->getProductSku(),$stockData)){
    			 
    			$stockData[$stock->getProductSku()] = $stockData[$stock->getProductSku()] + $stock->getStock();
    		}else{
    			$stockData[$stock->getProductSku()] = $stock->getStock();
    		}
    			
    	}
    	
    	
    	if(empty($stockData)){
    		return;
    	}
    	foreach ($stockData as $sku=>$qty) {    		
    		$this->saveStockData($sku, $qty);
    	}
    	
    }
	protected function saveStockData($sku, $qty){
		$productId = Mage::getModel('catalog/product')->getIdBySku($sku);
		if($productId){
    		$stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
    		if (!$stockItem->getId()) {
    			$stockItem->setData('product_id', $productId);
    			$stockItem->setData('stock_id', 1);
    		}
    		
    		if ($stockItem->getQty() != $qty) {
    			$stockItem->setData('qty', $qty);
    			$stockItem->setData('is_in_stock', $qty ? 1 : 0);
    			try {
    				$stockItem->save();
    			} catch (Exception $e) {
    				echo $e->getMessage();
    			}
    			
    		}
    	}
	}

    
    protected function prepareData($restReqest){

    	$result = array();
    	$buffer = Mage::getStoreConfig('logicbroker_sourcing/inventory/buffer');
    	$vendorModel = Mage::getModel('logicbroker/ranking')->getCollection();
    	//$arrVendorCode collect all available vendors 
    	if($vendorModel->count() > 0){
    		foreach ($vendorModel as $vendorCode) {
    			$arrVendorCode[] = $vendorCode->getLbVendorCode();
    		}
    	}
    	foreach ($restReqest as $value) {
    		foreach ($value as $key=>$val) {
				$result[$val['lb_vendor_code']] = $this->saveLbInventory($val, $buffer);
			}
    	}
		
    return 	$result;
    }
    
	/** This function is used to save Vendor inventory
	* @param array $val
	* @return array $msg
	*/
	protected function saveLbInventory($val, $buffer){
		if(!is_numeric($val['cost']) || $val['cost'] < 0){
			$ignoreData[]= 'cost';
		}
		if(!is_numeric($val['stock']) || $val['stock'] < 0){			
			$ignoreData[]= 'stock';
		}
		if((!is_numeric($val['cost']) || $val['cost'] < 0) && (!is_numeric($val['stock']) || $val['stock'] < 0)){
			if($val['cost']!="" && $val['stock']!="")
			$invalidData = true;
		}
		$arrayUpdate1 = array();	
		$arrayUpdate1['cost'] = $val['cost'];
		$arrayUpdate1['stock']= $val['stock'];
    
		// jira ticket LBN-928 , LBN-914 , LBN-916      

        $costFlag = true;
        $stockFlag = true;

        if(!is_numeric($val['stock']) || $val['stock'] < 0 || trim($val['stock']) == "")
        {
          $stockFlag = false;
        } 
  
        if(!is_numeric($val['cost']) || $val['cost'] < 0 || trim($val['cost']) == "")
        {
           $costFlag = false;
        }        
		
		$checkVendorCode = trim($val['lb_vendor_code']);
		$checkProductSku = trim($val['product_sku']);
		if(empty($val['product_sku']) && empty($val['lb_vendor_sku']))
		{			
		 return  $msg = 'Please provide the Vendor SKU or Product SKU details';
		}
		
		if(empty($checkVendorCode))
		{
			return $msg = 'Error In Importing "lb_vendor_code" Cannot Be Empty';
		}
        
         
		
		if(!empty($buffer)){
			if($buffer > $val['stock']){
				$val['stock'] = 0;
			}else{
				
				$val['stock'] = $val['stock'] - $buffer;
			}
		}
        
        // jira ticket LBN - 909 change       
		
		$dataCollection = Mage::getModel('logicbroker/inventory');
		$vendorObject =  Mage::getModel('logicbroker/ranking');
		$vendorCollection =  $vendorObject->load($val['lb_vendor_code'],'lb_vendor_code');       
        
        $lb_vendor_sku = trim($val['lb_vendor_sku']);
        
        
		if($lb_vendor_sku != '')
		{
			$collection = $dataCollection->getCollection()->addFieldToFilter('lb_vendor_code',$val['lb_vendor_code'])->addFieldToFilter('lb_vendor_sku',$lb_vendor_sku);
           
            $cntCollection = $collection->count();
            if($cntCollection == 0 && $checkProductSku != '')
            {                  
                    if(!(Mage::getModel('catalog/product')->getIdBySku($val['product_sku'])))
		{
                        return $msg = 'Can not import vendor inventory for non-existing product';
                    }
            
                $collection = $dataCollection->getCollection()->addFieldToFilter('lb_vendor_code',$val['lb_vendor_code'])->addFieldToFilter('product_sku',$checkProductSku);
		}      
		}
        else
        
        {
            if($checkProductSku != '')
           
            {                
                   if(!(Mage::getModel('catalog/product')->getIdBySku($val['product_sku'])))
                {
                        return $msg = 'Can not import vendor inventory for non-existing product';
                }
                    $collection = $dataCollection->getCollection()->addFieldToFilter('lb_vendor_code',$val['lb_vendor_code'])->addFieldToFilter('product_sku',$val['product_sku']);
            }            
            else
            {
                return $msg = 'Can not import vendor inventory for blank product sku'; 
        }      
		}        
        $product_sku = $collection->getFirstItem()->getProductSku();
        $collectionCount = count($collection);        
        if($collectionCount >= 2 && empty($checkProductSku))
        {
            return  $msg = 'Multiple records found. Please provide Product SKU';  
        }
        
		if($collection->getSize() > 0){		
			$arrFirst = $collection->getFirstItem ()->getData ();
			$collection->getFirstItem ()->setUpdatedAt(now());
			if($stockFlag == true)
			{
			   $collection->getFirstItem ()->setStock($val['stock']);
			}
            
			if($costFlag == true)
			{
			$collection->getFirstItem ()->setCost($val['cost']);
			}
           
            
			
			$collection->getFirstItem ()->save();
			
			 $arrayUpdate2 = array('updated_by'=>'system','product_sku'=>$product_sku,'lb_vendor_code'=>$val['lb_vendor_code']);                                                 			$arrayUpdate = array_merge($arrayUpdate1, $arrayUpdate2);	
			if(count($ignoreData)>0){
					if(in_array('stock', $ignoreData)) {
						$msg  = 'Cost Updated Successfully, Stock Ignored due to invalid data for';
						$type = 'Cost Updated , Qty Ignored';
			}
					if(in_array('cost', $ignoreData)){
                            
						$msg  =  'Stock Updated Successfully, Cost Ignored due to invalid data for';
						$type = 'Qty Updated, Cost Ignored';
					}					
				if(count($ignoreData)==2){
					$msg = 'Cost & Stock Update Failed due to invalid data for';
					$type = 'ignore';
            }
			}else{
				$msg = 'Cost & Stock Updated Successfully for';
				$type = 'update';
            }            
			$this->_saveInventoryLog($type,$arrayUpdate);
				$this->_updateVendorList($vendorCollection,$val,false);
			return $msg;
		}
	}
    
    public function saveTabVendorData($request){
    	
    	$inventoryStock = array();
    	$update = $request['vendor_update'];
    	$addNew = $request['vendor_new'];
    	$sku = $request['sku'];
    	$result = true;
    	
    	$error = $this->_validate($request);
    	
    	if($error){
    		
    		return $result = false;
    	}
    	
    	
    	foreach($update as $key => $data){
    		if($data['is_delete'] == 1){
    			$this->_deleteInvendorVendor($key);
    			
    			
    		}else{
    			$result = $this->_updateInventoryVendor($key,$data);
    			
    		}
    	}
    	
    	if(!empty($addNew)){
    	
    		foreach($addNew as $key => $data){
    		if($data['is_delete'] == 1){
    			continue;
    		}
    		$this->_addNewInventoryVendor($data,$sku);
    		
    	}
    	}
    	$finalStock = 0;
    	$finalStock = $this->_productInventoryAdd + $this->_productInventoryUpdate;
    	
    	//$finalStock = $this->_updateBuffer($finalStock);
    	//$this->_inventoryUpdate($sku);
    	return array('inventory'=>$finalStock,'result' => $result);
    }
    
    protected function _validate($request)
    {
    	$arrVendorCode = array();
    	$isError = true;
    	$errorArr = array();
    	if(!empty($request['vendor_new'])){
    	foreach ($request['vendor_new'] as $key => $data){
    			
    		$arrVendorCode[] = $data['lb_vendor_code'];
    		//patch for unique combination keys vendor_code and vendor_sku
    		if($this->checkCodeSkuCombination($data['lb_vendor_code'],$data['lb_vendor_sku']) > 0 )
    		{
    			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('logicbroker')->__('Duplicate entry found for %s , %s',$data['lb_vendor_code'],$data['lb_vendor_sku']));
    			$errorArr[] = 'yes';
    		}else
    		{
    			$errorArr[] = 'no';
    		}
    	}
    	
    	$isUnique = (array_unique($arrVendorCode) == $arrVendorCode);
    	$isEntrySame = $isUnique ? false : true;
    	if($isEntrySame)
    		Mage::getSingleton('adminhtml/session')->addError(Mage::helper('logicbroker')->__('Duplicate Vendor Entry'));
    	$isUniqueCombination = in_array('yes',$errorArr);
    	}
    	
    	//patch for unique combination keys vendor_code and vendor_sku
    	(!$isUniqueCombination && !$isEntrySame) ? $isError = false : $isError;
    	
    	return $isError;
    }
    
    
    
    protected function checkCodeSkuCombination($vendorCode,$vendorSku)
    {
    	$collection = $this->getCollection()->addFieldTofilter('lb_vendor_code',$vendorCode)->addFieldTofilter('lb_vendor_sku',$vendorSku);
    	return $collection->count();  //0) ? true : false;
    }
    
    protected function _addNewInventoryVendor($request,$productSku){
    	//$model = $this->load($vendorId);
    	$vendorCollection =  Mage::getModel('logicbroker/ranking')->load($request['lb_vendor_code'],'lb_vendor_code');
    	
    	$request['updated_at'] = now();
    	$request['product_sku'] = $productSku;
    	$request['updated_by'] = Mage::getSingleton('admin/session')->getUser()->getUsername();
    	$request['lb_vendor_name'] = $vendorCollection->getLbVendorName();
    	$request['stock'] = $this->_updateBuffer($request['stock']);
    	$this->setData($request);
    	try{
    		$this->save();
    		$this->_saveInventoryLog('add',$request);
    		$this->_productInventoryAdd = $this->_productInventoryAdd +  $request['stock'];
    		Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('logicbroker')->__('%s Added Successfully ',$request['lb_vendor_name']));
    	}catch(Exception $e){
    		Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
    	}
    }
    
    protected function _updateInventoryVendor($id,$request){
    	
    	$model = $this->load($id);
    	$vendorCode = $model->getLbVendorCode();
    	$vendorName = $model->getLbVendorName();
    	$vendorCollection =  Mage::getModel('logicbroker/ranking')->load($vendorCode,'lb_vendor_code');
    	$DbValues['cost'] = $model->getCost();
    	$DbValues['stock'] = $model->getStock();
    	$DbValues['lb_vendor_sku'] = $model->getLbVendorSku();
    	
    	if($DbValues['lb_vendor_sku'] != $request['lb_vendor_sku']){
    	//patch for unique combination keys vendor_code and vendor_sku
    	if($this->checkCodeSkuCombination($vendorCode,$request['lb_vendor_sku']) > 0){
    		Mage::getSingleton('adminhtml/session')->addError(Mage::helper('logicbroker')->__('Duplicate entry found for %s , %s',$vendorCode,$request['lb_vendor_sku']));
    		return false;
    	}
    	}
    	if($DbValues['cost'] != $request['cost'] || $DbValues['stock'] != $request['stock'] || $DbValues['lb_vendor_sku'] != $request['lb_vendor_sku']){

    	
    		if($DbValues['stock'] != $request['stock'])
    		$request['stock'] = $this->_updateBuffer($request['stock']);
    		
    		$request['updated_at'] = now();
    		$model->addData($request);
    	try{
    		$model->save();
    		
    		$this->_saveInventoryLog('update',array('lb_vendor_name'=>$vendorCollection->getLbVendorName(),'updated_by'=>Mage::getSingleton('admin/session')->getUser()->getUsername(),'product_sku'=>$model->getProductSku(),'lb_vendor_code'=>$model->getLbVendorCode(),'cost'=>$model->getCost(),'stock'=>$model->getStock()));
    		//if($DbValues['stock'] != $request['stock'])
    		$this->_productInventoryUpdate = $this->_productInventoryUpdate +  $request['stock'];
    		
    		Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('logicbroker')->__('%s Updated Successfully',$vendorName));
    	}catch(Exception $e){
    		Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
    	}
    	}else{
    		
    		$this->_productInventoryUpdate = $this->_productInventoryUpdate +  $request['stock'];
    	}
    	return true;
    }
    
    protected function _deleteInvendorVendor($vendorId){
    	
    	$model = $this->load($vendorId);
    	
    	$vendorCode = $model->getLbVendorCode();
    	$vendorCollection =  Mage::getModel('logicbroker/ranking')->load($vendorCode,'lb_vendor_code');
    	$request = array('lb_vendor_name'=>$vendorCollection->getLbVendorName(),'updated_by'=>Mage::getSingleton('admin/session')->getUser()->getUsername(),'product_sku'=>$model->getProductSku(),'lb_vendor_code'=>$model->getLbVendorCode(),'cost'=>$model->getCost(),'stock'=>$model->getStock(),'updated_at' => now());
    	try{
    	$model->delete();
    	$this->_saveInventoryLog('delete',$request);
    	$this->_productInventorySubtract = $this->_productInventorySubtract +  $request['stock'];
    	Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('logicbroker')->__('%s Deleted Successfully ',$request['lb_vendor_name']));
    	}catch(Exception $e){
    		Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
    	}
    }
    
    public function _saveInventoryLog($type,$request){
    	$modelLog = Mage::getModel('logicbroker/inventorylog');
    	$request['activity'] = $type;
    	$request['updated_at'] = now();
    	$modelLog->setData($request);
    	try{
    		$modelLog->save();
    		
    	}catch(Exception $e){
    		Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
    	}
    }
    
    protected function _updateVendorList($object,$data = '',$isNew = false){
    	
    	
    	if(!empty($data)){
    		$object->setUpdatedAt(now());
    		$object->setLbVendorType('enhanced');
    		if(!$object->getId()) $object->setIsDropship('no');
    		$object->setLbVendorCode($data['lb_vendor_code']);
    		
    	}
    	
    try{
    		$object->save();
    		
    	}catch(Exception $e){
    		Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
    	}
    }
    
    protected function _updateBuffer($stock){
    	$buffer = Mage::getStoreConfig('logicbroker_sourcing/inventory/buffer');
    	$finalStock = 0;
    	if(!empty($buffer)){
    		if($buffer > $stock){
    			$stock = 0;
    		}else{
    	
    			$finalStock = $stock - $buffer;
    		}
    	}else
    	{
    		$finalStock = $stock;
    	}
    	return $finalStock;
    }
   
     
}