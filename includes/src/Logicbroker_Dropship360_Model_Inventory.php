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
	const XML_PATH_INVENTORY_NOTIFICATION_EMAIL  = 'logicbroker_sourcing/inventory_notification/email';
	const XML_PATH_INVENTORY_NOTIFICATION_DAYS  = 'logicbroker_sourcing/inventory_notification/days';
	
	protected function _construct()
	{
       $this->_init("dropship360/inventory");
    }
	/**
	 * @param Logicbroker_Dropship360_Model_Api2_Inventory_Rest_Admin_V1 $restReqest 
	 * function used by REST for update vendor inventory
	 */
    public function prepareInventoryTable($restReqest)
	{   	
    	$result = $this->prepareData($restReqest);
    	$this->updateProductStock();
    	return $result;    	
    }
    /**
     * update vendor inventory using REST
     */
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
	
    /**
     * update catalogInventory for product
     * @param Logicbroker_Dropship360_Model_Inventory $sku
     * @param Logicbroker_Dropship360_Model_Inventory $qty
     */
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
    			}    			
    		}
    	}
	}

    /**
     * prepare and insert/update vendor data from REST request 
     * @param REST $restReqest
     */
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
    	// 		if($collection->getSize() >= 2 && empty($val['product_sku']))
    		// 		{
    		// 			return  'Multiple records found. Please provide Product SKU';
    		// 		}
    	$collection->getSelect()->limit(1);
    	$product_sku = $collection->getFirstItem()->getProductSku();
    	/* LBN - 935 change */
    	$val['stock'] = Mage::helper('dropship360')->getIsQtyDecimal($product_sku,$val['stock']);
    	 
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
    
    /**
     * Validate row data getting from REST 
     * @param json $val
     * @return boolean
     */
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
	
	/**
	 * get processed stock for vendor
	 * @param json $val
	 * @param core_config_data $buffer
	 * @return stock (request-stock + buffer)
	 */
    protected function updateStock($val,$buffer)
        {
    	if(!empty($buffer))
    		($buffer > $val['stock']) ? $val['stock'] = 0 : $val['stock'] = $val['stock'] - $buffer;
    	return $val['stock'];
        } 
  
    /**
     * prepare collection from vendor inventory table
     * @param jason $val
     * @return collection
     */
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
	
    /**
     * generate inline log message for product details page
     * @param array $ignoreData
     * @return array
     */        
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
     * create new vendor using REST if not exist 
     * @param Logicbroker_Dropship360_Model_Ranking $object
     * @param string $data
     */
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
	
/* update or add vendor inventory using REST code ends here */
/**************************************************************/
    /**
     * create,update,delete vendor data from product details page
     * @param Mage_catalog_Model_Product $request
     * @return boolean|multitype:number boolean
     */        
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
    
    /**
     * validate frontend admin data provided by user on product detail page 
     * @param Mage_catalog_Model_Product $request
     * @return boolean
     */
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

    /**
     * check unique combination of vendorCode and vendorSku 
     * in logicbroker_vendor_inventory table
     * @param Logicbroker_Dropship360_Model_Inventory $vendorCode
     * @param Logicbroker_Dropship360_Model_Inventory $vendorSku
     * @return number
     */
    protected function checkCodeSkuCombination($vendorCode,$vendorSku)
    {
    	$collection = $this->getCollection()->addFieldTofilter('lb_vendor_code',$vendorCode)->addFieldTofilter('lb_vendor_sku',$vendorSku);
    	return $collection->count();
    }
    
    /**
     * add new vendor on product details page for SKU in request
     * @param Mage_catalog_Model_Product $request
     * @param Mage_catalog_Model_Product $productSku
     */
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
    
    /**
     * update vendor inventory data as per data from product details page for SKU in request
     * @param Logicbroker_Dropship360_Model_Inventory $id
     * @param Mage_Catalog_Model_Product $request
     * @return boolean
     */
    protected function _updateInventoryVendor($id,$request){
    	
    	$model = $this->load($id);
    	$vendorCode = $model->getLbVendorCode();
    	$vendorName = $model->getLbVendorName();
    	$DbValues['cost'] = $model->getCost();
    	//patch apply to check empty stock 
    	$DbValues['stock'] = ($model->getStock() == '') ? -9999999 : $model->getStock();
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
    
    /**
     * Delete vendor from product details page for SKU in request 
     * @param Logicbroker_Dropship360_Model_Inventory $vendorId
     */
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
    
    /**
     * add inline comment to dropship360 history tab on product details page
     * @param Logicbroker_Dropship360_Model_Inventorylog $type
     * @param Mage_Catalog_Model_Product $request
     */
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
    
    /**
     * update catalogInventory 
     * @param array $result
     * @param Mage_Catalog_Model_Product $sku
     */
    public function productInventoryUpdate($result,$sku)
    {
    	if(!$result['result']){
    		return;
    	}
    
    	$finalStock = $result['inventory'];
    	$finalStock = Mage::helper('dropship360')->getIsQtyDecimal($sku, $finalStock);
    	$conn = Mage::getSingleton ( 'core/resource' )->getConnection ( 'core_write' );
    
    	$tableNameStatus = Mage::getSingleton ( 'core/resource' )->getTableName ( 'cataloginventory/stock_status' );
    	$tableNameItem = Mage::getSingleton ( 'core/resource' )->getTableName ( 'cataloginventory/stock_item' );
    	$tableNameItemIdx = Mage::getSingleton ( 'core/resource' )->getTableName ( 'cataloginventory/stock_status_indexer_idx' );
    
    	$stockStatus = $finalStock ? 1 : 0;
    	$productId = Mage::getModel('catalog/product')->getIdBySku($sku);
    	if($productId){
    		$updateStatus = 'update '.$tableNameStatus.' SET qty = '.$finalStock.',stock_status = '.$stockStatus.' where product_id = '.$productId;
    		$updateItem = 'update '.$tableNameItem.' SET qty = '.$finalStock.',is_in_stock = '.$stockStatus.' where product_id = '.$productId;
    		$updateItemIdx =  'update '.$tableNameItemIdx.' SET qty = '.$finalStock.',stock_status = '.$stockStatus.' where product_id = '.$productId;
    		$conn->beginTransaction ();
    		$conn->query ($updateStatus);
    		$conn->query ($updateItem);
    		$conn->query ($updateItemIdx);
    		try {
    			$conn->commit ();
    		} catch ( Exception $e ) {
    			$conn->rollBack ();
    			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
    		}
    	}
    }
    
    /**
     * calculate final stock
     * @param request $stock
     * @param Logicbroker_Dropship360_Model_Inventory $dbCost
     * @return stock
     */
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
    /***************Vendor details save from product details page code ends here*****/
    
    /**
     * method use to send email notification to logicbroker 
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
    
    /**
     * send email notification to logicbroker support
     * @return boolean
     */
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
   
   	/**
   	 * send notification email to users mention in activity monitor section
   	 * @return Logicbroker_Dropship360_Model_Inventory
   	 */
   	public function notificationProductInventoryUpdate(){
   		$itemObject;
   		$fileInfo = array();
   		$ioAdapter = new Varien_Io_File();
   		$open_monitor_from = Date('Y-m-d h:i:s', strtotime('-'.Mage::getStoreConfig(self::XML_PATH_INVENTORY_NOTIFICATION_DAYS).' day'));
   		$open_monitor_to = Mage::getModel('core/date')->gmtDate();
   		$itemObject = Mage::getModel('dropship360/inventory')->getCollection()->addFieldTofilter('updated_at', array('from' => $open_monitor_from,'to' => $open_monitor_to));
   		if($itemObject->getSize() <= 0){
   			Mage::log('cannot send outdated product inventory email collection is empty for form :'.$open_monitor_from.' to :'.$open_monitor_to, null, 'notification_error.log');
   			return $this;
   		}
   		$fileInfo = Mage::getModel('dropship360/csvparser')->getCsvFile($itemObject);
   		$mailData['days'] = Mage::getStoreConfig(self::XML_PATH_INVENTORY_NOTIFICATION_DAYS);
   		$mailData['subject'] = 'dropship360 list of outdated product inventory';
   		$postObject = new Varien_Object();
   		$postObject->setData($mailData);
   		$email = trim(Mage::getStoreConfig(self::XML_PATH_INVENTORY_NOTIFICATION_EMAIL));
   		$templateId = 'logicbroker_outdated_product_inventory';
   		$isMailSent = Mage::helper('dropship360')->sendMail($postObject,$email,$templateId,$fileInfo['value']);
   		$ioAdapter->rm($fileInfo['value']);
   		return $this;
   	}
   	
   	/**
   	 * update vendor name 
   	 * @param Logicbroker_Dropship360_Model_Ranking $vendor
   	 */
   	public function upDateVendorName($vendor){
   		if(empty($vendor['code'])  || empty($vendor['name']))
   		{
   			return;
   		}
   		$helper = Mage::helper('dropship360'); 
   		$table =  Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/inventory' );
   		$conObj = Mage::getSingleton ( 'core/resource' )->getConnection('core_write');
   		try {
   			$conObj->update($table,array('lb_vendor_name'=>$helper->convertToHtmlcode($vendor['name'])),array('lb_vendor_code = ?'=>$vendor['code']));
   		} catch ( Exception $e ) {
   			Mage::throwException('Error occured while renaming vendor in inventory table : '.$e->getMessage());
   			//Mage::getSingleton ( 'adminhtml/session' )->addError($e->getMessage ());
   		}
   	}
}