<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Inventory extends Mage_Core_Model_Abstract
{
	protected $_productInventoryAdd = 0;
	protected $_productInventorySubtract = 0;
	protected $_productInventoryUpdate = 0;
	protected $_stockBeforeQtyDecimalCheck = ''; 
	protected $_iserror = true;
	protected $_errorMsg = '';
	protected function _construct()
	{
       $this->_init("dropship360/inventory");
    }

    public function prepareInventoryTable($restReqest)
	{   	
    	$result = $this->prepareData($restReqest);
    	$this->updateProductStock();
    	return $result;    	
    }
    
    protected function updateProductStock()
	{    	
    	$dataCollection = Mage::getModel('dropship360/inventory')->getCollection();
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
	
	protected function saveStockData($sku, $qty)
	{
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
    				Mage::helper('dropship360')->genrateLog(0,'mgento inventory update started','mgento inventory update ended','Section :Error In Setting/update magento inventory: '.$e->getMessage().' sku : '.$sku);
    				echo $e->getMessage();
    			}    			
    		}
    	}
	}

    
    protected function prepareData($restReqest)
	{
    	$result = array();
    	$buffer = trim(Mage::getStoreConfig('logicbroker_sourcing/inventory/buffer'));
    	$vendorModel = Mage::getModel('dropship360/ranking')->getCollection();
    	foreach ($restReqest as $value) {
    		foreach ($value as $key=>$val) {
				$result[$val['lb_vendor_code']] = $this->saveLbInventory($val, $buffer);
			}
    	}		
		return 	$result;
    }
    
    protected function validateRowData($val)
	{
    	if(empty($val['product_sku']) && empty($val['lb_vendor_sku'])){
    		$this->_errorMsg = 'Please provide the Supplier SKU or Product SKU details';
    		$this->_iserror = false;
    	}elseif(empty($val['lb_vendor_code'])){
    		$this->_errorMsg = 'Error In Importing "lb_vendor_code" Cannot Be Empty';
    		$this->_iserror = false;
		}
    	return $this->_iserror;
		}

    protected function updateStock($val,$buffer)
        {
    	if(!empty($buffer))
    		($buffer > $val['stock']) ? $val['stock'] = 0 : $val['stock'] = $val['stock'] - $buffer;
    	return $val['stock'];
        } 
  
    
    protected function _prepareCollection($val)
        {
		$dataCollection = Mage::getModel('dropship360/inventory');
    	$collection = null;
    	$lb_vendor_sku = $val['lb_vendor_sku'];
		if($lb_vendor_sku != ''){
			$collection = $dataCollection->getCollection()->addFieldToFilter('lb_vendor_code',$val['lb_vendor_code'])->addFieldToFilter('lb_vendor_sku',$lb_vendor_sku);          
    		if($collection->getSize() == 0 && $val['product_sku'] != ''){
                if(!(Mage::getModel('catalog/product')->getIdBySku($val['product_sku']))){
    				$this->_errorMsg = 'Can not import Supplier inventory for non-existing product';
    				return $collection;
                }
    			$collection = $dataCollection->getCollection()->addFieldToFilter('lb_vendor_code',$val['lb_vendor_code'])->addFieldToFilter('product_sku',$val['product_sku']);
			}      
		}
        else{
    		if($val['product_sku'] != ''){
			   if(!(Mage::getModel('catalog/product')->getIdBySku($val['product_sku']))){
    				$this->_errorMsg = 'Can not import Supplier inventory for non-existing product';
    				return $collection;
                }
				$collection = $dataCollection->getCollection()->addFieldToFilter('lb_vendor_code',$val['lb_vendor_code'])->addFieldToFilter('product_sku',$val['product_sku']);
            } else{
    			$this->_errorMsg = 'Can not import Supplier inventory for blank product sku';
    			return $collection;
    		}
        }        
    	return $collection;
			}
            
    protected function getLogMsg($ignoreData)
    {
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
    	return array('msg'=>$msg,'type'=>$type);
    }
    
	/** 
	 * This function is used to save Vendor inventory
	 * @param array $val
	 * @return array $msg
	 */
	protected function saveLbInventory($rowVal, $buffer)
	{
		$val = array_map('trim',$rowVal);
		$originalStock = $val['stock'];
		$vendorObject =  Mage::getModel('dropship360/ranking');
		$vendorCollection =  $vendorObject->load($val['lb_vendor_code'],'lb_vendor_code');
		if(!$this->validateRowData($val))
			{
				return $this->_errorMsg;
			}
		
		$ignoreData = array();
		(!is_numeric($val['cost']) || $val['cost'] < 0) ? $ignoreData[]= 'cost' : '';
		(!is_numeric($val['stock']) || $val['stock'] < 0) ? $ignoreData[]= 'stock' : '';
		(!is_numeric($val['stock']) || $val['stock'] < 0 || $val['stock'] == "") ? $stockFlag = false : $stockFlag = true; 
    	(!is_numeric($val['cost']) || $val['cost'] < 0 || $val['cost'] == "") ? $costFlag = false : $costFlag = true;
		$val['stock'] = $this->updateStock($val,$buffer);
		$collection = $this->_prepareCollection($val);
		if(is_null($collection)){
			return $this->_errorMsg;
		}
		$product_sku = $collection->getFirstItem()->getProductSku();
        /* LBN - 935 change */
        $val['stock'] = Mage::helper('dropship360')->getIsQtyDecimal($product_sku,$val['stock']);
        if($collection->getSize() >= 2 && empty($val['product_sku']))
        {
            return  'Multiple records found. Please provide Product SKU';  
        }        
		if($collection->getSize() > 0){		
			$collection->getFirstItem ()->setUpdatedAt(now());
			($stockFlag == true) ? $collection->getFirstItem ()->setStock($val['stock']) : '';
			($costFlag == true) ? $collection->getFirstItem ()->setCost($val['cost']) : '';
			$arrayUpdate = array('updated_by'=>'system','product_sku'=>$product_sku,'lb_vendor_code'=>$val['lb_vendor_code'],'cost'=>$val['cost'],'stock'=>$originalStock);
			$logDetail = $this->getLogMsg($ignoreData);
			$this->_saveInventoryLog($logDetail['type'],$arrayUpdate);
			if(count($ignoreData)!=2){
				$collection->getFirstItem ()->save();
				$this->_updateVendorList($vendorCollection,$val,false);
			}
			return $logDetail['msg'];
		}else{
			return 'Vendor Sku "'.$val['lb_vendor_sku'].'" and Magento SKU "'.$val['product_sku'].'" combination does not exist for vendor ';
		}
	}
    
    public function saveTabVendorData($request)
	{   	
  	$update = isset($request['vendor_update']) ? $request['vendor_update'] : '';
    	$addNew = isset($request['vendor_new']) ? $request['vendor_new'] : '';
    	$sku = isset($request['sku']) ? $request['sku'] : '';
    	$result = true;   	
    	$error = $this->_validate($request);   	
    	if($error){    		
    		return $result = false;
    	} 	
    if(!empty($update)){	
    	foreach($update as $key => $data){
    		if($data['is_delete'] == 1){
    			$this->_deleteInvendorVendor($key);	
    		}else{
    			$result = $this->_updateInventoryVendor($key,$data);		
    		}
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
    	return array('inventory'=>$finalStock,'result' => $result);
    }
    
    protected function _validate($request)
    {
    	$arrVendorCode = array();
    	$isUniqueCombination = false;
		$isEntrySame = false;	
    	$isError = true;
    	$errorArr = array();
    	if(!empty($request['vendor_new'])){
			foreach ($request['vendor_new'] as $key => $data){   			
				$arrVendorCode[] = $data['lb_vendor_code'];
				//patch for unique combination keys vendor_code and vendor_sku
				if($this->checkCodeSkuCombination($data['lb_vendor_code'],$data['lb_vendor_sku']) > 0 )
				{
					Mage::getSingleton('adminhtml/session')->addError(Mage::helper('dropship360')->__('Duplicate entry found for %s , %s',$data['lb_vendor_code'],$data['lb_vendor_sku']));
					$errorArr[] = 'yes';
				}else{
					$errorArr[] = 'no';
				}
			}    	
			$isUnique = (array_unique($arrVendorCode) == $arrVendorCode);
			$isEntrySame = $isUnique ? false : true;
			if($isEntrySame)
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('dropship360')->__('Duplicate Supplier Entry'));
			$isUniqueCombination = in_array('yes',$errorArr);
    	}	
    	//patch for unique combination keys vendor_code and vendor_sku
    	(!$isUniqueCombination && !$isEntrySame) ? $isError = false : $isError;   	
    	return $isError;
    }
     
    protected function checkCodeSkuCombination($vendorCode,$vendorSku)
    {
    	$collection = $this->getCollection()->addFieldTofilter('lb_vendor_code',$vendorCode)->addFieldTofilter('lb_vendor_sku',$vendorSku);
    	return $collection->count();
    }
    
    protected function _addNewInventoryVendor($request,$productSku){
    	$vendorCollection =  Mage::getModel('dropship360/ranking')->load($request['lb_vendor_code'],'lb_vendor_code');
		$request['created_at'] = now();
    	$request['updated_at'] = now();
    	$request['product_sku'] = $productSku;
    	$request['updated_by'] = Mage::getSingleton('admin/session')->getUser()->getUsername();
    	$request['lb_vendor_name'] = $vendorCollection->getLbVendorName();
		$request['lb_vendor_sku'] = trim($request['lb_vendor_sku']);
    	if(!empty($productSku)){                    
		   $qty = Mage::helper('dropship360')->getIsQtyDecimal($productSku, $request['stock']);  
		}
		else{
		   $qty = $request['stock'];  
		}
    	$request['stock'] = $this->_updateBuffer($qty);
    	$this->setData($request);
    	try{
    		$this->save();
    		$this->_saveInventoryLog('add',$request);
    		$this->_productInventoryAdd = $this->_productInventoryAdd +  $request['stock'];
    		Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('dropship360')->__('%s Added Successfully ',$request['lb_vendor_name']));
    	}catch(Exception $e){
    		Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
    	}
    }
    
    protected function _updateInventoryVendor($id,$request){
    	
    	$model = $this->load($id);
    	$vendorCode = $model->getLbVendorCode();
    	$vendorName = $model->getLbVendorName();
    	$DbValues['cost'] = $model->getCost();
    	$DbValues['stock'] = $model->getStock();
    	$DbValues['lb_vendor_sku'] = $model->getLbVendorSku();
		$productSku = $model->getProductSku();
		$request['lb_vendor_sku'] = trim($request['lb_vendor_sku']);
		if(!empty($productSku)){                    
			$this->_stockBeforeQtyDecimalCheck =  $request['stock'];
		   $request['stock'] = Mage::helper('dropship360')->getIsQtyDecimal($productSku, $request['stock']);  
		}
		
    	if($DbValues['lb_vendor_sku'] != $request['lb_vendor_sku']){
			//patch for unique combination keys vendor_code and vendor_sku
			if($this->checkCodeSkuCombination($vendorCode,$request['lb_vendor_sku']) > 0){
				Mage::getSingleton('adminhtml/session')->addError(Mage::helper('dropship360')->__('Duplicate entry found for %s , %s',$vendorCode,$request['lb_vendor_sku']));
				return false;
			}
    	}
    	if($DbValues['cost'] != $request['cost'] || $DbValues['stock'] != $request['stock'] || $DbValues['lb_vendor_sku'] != $request['lb_vendor_sku']){	
    		if($DbValues['stock'] != $request['stock'])
    		$request['stock'] = $this->_updateBuffer($request['stock'],$DbValues['stock']);    		
    		$request['updated_at'] = now();
    		$model->addData($request);
			try{
				$model->save();			
				$this->_saveInventoryLog('update',array('updated_by'=>Mage::getSingleton('admin/session')->getUser()->getUsername(),'product_sku'=>$model->getProductSku(),'lb_vendor_code'=>$model->getLbVendorCode(),'cost'=>$model->getCost(),'stock'=>$model->getStock()));
				$this->_productInventoryUpdate = $this->_productInventoryUpdate +  $request['stock'];
				
				Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('dropship360')->__('%s Updated Successfully',$vendorName));
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
    	$vendorCollection =  Mage::getModel('dropship360/ranking')->load($vendorCode,'lb_vendor_code');
    	$request = array('lb_vendor_name'=>$vendorCollection->getLbVendorName(),'updated_by'=>Mage::getSingleton('admin/session')->getUser()->getUsername(),'product_sku'=>$model->getProductSku(),'lb_vendor_code'=>$model->getLbVendorCode(),'cost'=>$model->getCost(),'stock'=>$model->getStock(),'updated_at' => now());
    	try{
			$model->delete();
			$this->_saveInventoryLog('delete',$request);
			$this->_productInventorySubtract = $this->_productInventorySubtract +  $request['stock'];
			Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('dropship360')->__('%s Deleted Successfully ',$request['lb_vendor_name']));
    	}catch(Exception $e){
    		Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
    	}
    }
    
    public function _saveInventoryLog($type,$request){
    	$modelLog = Mage::getModel('dropship360/inventorylog');
    	$request['activity'] = $type;
		if($type=='add')
		$request['created_at'] = now();
    	$request['updated_at'] = now();
		if(!isset($request['lb_vendor_name'])){
			$vendorRankModel = Mage::getModel('dropship360/ranking')->load($request['lb_vendor_code'],'lb_vendor_code');
			$request['lb_vendor_name'] = $vendorRankModel->getLbVendorName();
		}	
    	$modelLog->setData($request);
    	try{
    		$modelLog->save();   		
    	}catch(Exception $e){
    		Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
    	}
    }
    
    protected function _updateVendorList($object,$data = ''){
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
    
    protected function _updateBuffer($stock,$dbCost = null){
    	$buffer = Mage::getStoreConfig('logicbroker_sourcing/inventory/buffer');
    	$finalStock = 0;
    	if(!empty($dbCost) && $this->_stockBeforeQtyDecimalCheck == $dbCost)
    	{
    		return $stock;
    	}
    	if(!empty($buffer)){
    		if($buffer > $stock){
    			$stock = 0;
    		}else{
    	
    			$finalStock = $stock - $buffer;
    		}
    	}else{
    		$finalStock = $stock;
    	}
    	return $finalStock;
    }
    /* method use to send email notification to logicbroker 
     * that first vendor has been added to logicbroker_vendor_inventory
     */
    protected function _afterSave()
    {
    	$colSize = $this->getCollection()->getSize();
    	$notifyVs = Mage::getStoreConfigFlag('logicbroker/notification/vendor_setup');
    	if($colSize == 1 && !$notifyVs)
    	{
    		$this->sendVendorNotification();
    		Mage::getModel('dropship360/logicbroker')->saveNotificationValue(1,'logicbroker/notification/vendor_setup');
    	}
    	parent::_afterSave();
    	return;
    }
    
   protected function sendVendorNotification(){
   	
   			try {
   				$fieldsetData['subject'] = 'DS360 Product Setup completed on Magento';
   				$postObject = new Varien_Object();
   				$postObject->setData($fieldsetData);
   				$templateId = 'logicbroker_productsetup_notification';
   				$email = Mage::helper('dropship360')->getConfigObject('apiconfig/email/toaddress');
   				$isMailSent = Mage::helper('dropship360')->sendMail($postObject,$email,$templateId);
   				if (!$isMailSent) {
   					Mage::helper('dropship360')->genrateLog(0,'Order notification started','Order notification ended','First product setup complete successfully but email sending failed');
   				}
   				return true;
   			} catch (Exception $e) {
   			return false;//$e->getMassage();
   		}
   	}
   	public function upDateVendorName($vendor){
   		if(empty($vendor['code'])  || empty($vendor['name']))
   		{
   			return;
   		}
   		$table =  Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/inventory' );
   		$update = 'UPDATE '.$table.' SET lb_vendor_name = "'.$vendor['name'].'" WHERE lb_vendor_code = "'.$vendor['code'].'"';
   		$conObj = Mage::getSingleton ( 'core/resource' )->getConnection('core_write');
   		$conObj->beginTransaction();
   		$conObj->query($update);
   		try {
   			$conObj->commit ();
   		} catch ( Exception $e ) {
   			$conObj->rollBack ();
   			Mage::getSingleton ( 'adminhtml/session' )->addError($e->getMessage ());
   		}
   	}
}