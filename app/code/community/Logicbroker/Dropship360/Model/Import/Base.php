<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 * Base file will contain comman functions which will used by vendor inventory import section i.e. 
 * @Manual Inventory Upload
 * @Ftp Inventory Upload
 * @Product Setup
 * In some cases we have override Base class function to achive funcntioanlity of above section
 */

abstract class Logicbroker_Dropship360_Model_Import_Base 
{
	abstract public function validateCsvHeader($colNames);
	protected $_vendorCode = '';
	protected $_linkAttribute;
	protected $_errorsCount = 0;
	protected $_successCount = 0;
	protected $_vendorInventory = array();
	protected $_productInventory = array();
	protected $_inlineLog = array();
	protected $_historyLog = array();
	protected $_csvParserObj;
	protected $_chunkSize = 1000;
	protected $_bufferStock ;
	protected $_dbConnection;
	protected $_updatedBy = '';
	protected $_vendorName;
	protected $_indexVendorSku = 0;
	protected $_indexStock = 1;
	protected $_indexPrice = 2;
	protected $_historyErrorType = 'Missing/Bad Data';
	protected $_manualCsvError = array();
	
	public function initVar($vendorCode){
		$this->_dbConnection = Mage::helper('dropship360')->getDatabaseConnection()->getConnection ( 'core_write' );
		$this->_bufferStock = Mage::getStoreConfig('logicbroker_sourcing/inventory/buffer');
		$this->_vendorCode = $vendorCode;
		$ranking = Mage::getModel('dropship360/ranking')->load($vendorCode,'lb_vendor_code');
		$this->_linkAttribute = $ranking->getLinkingAttribute();
		$this->_vendorName = $ranking->getLbVendorName();
		$this->_updatedBy = Mage::getSingleton('admin/session')->getUser()->getUsername();
	}
	/*
	 * @return value from core_config_data
	 */
	protected function getConfigValue($path)
	{
		return Mage::getStoreConfig($path);
	}
	
	/*
	 * check if attribute assign to catalog_product
	 */
	protected function checkAttributeAval($attr,$object = null){
		$isExist = false;
		$attrEav = Mage::getResourceModel('catalog/eav_attribute')->loadByCode('catalog_product',$attr);
		if ($attrEav->getId())
			$isExist = true;
		return $isExist;
	}
	
	/*
	 * @errorCode-type: history
	* @history : log for upload history page
	*/
	public function addRowError($errorCode, $errorRowNum, $colName = array())
	{
		$colCount = count($colName);
		$i = 0;
		while ($i < $colCount) {
			$this->_manualCsvError[$errorCode]['row_'.$colName[$i]][] = $errorRowNum + 1;
			$i++;
		}
		$this->_errorsCount ++;
		return $this;
	}
	
	/* arry_filter function
	 * 
	 */
	protected function validateGenerateProduct($product){
		$isValid = true;
		
		$helper = Mage::helper('dropship360');
		if(!array_key_exists('magento_sku', $product))
		{
			$errorType = ($this->_linkAttribute == $helper::LOGICBROKER_PRODUCT_LINK_CODE_SKU) ? 'magento_sku_exists' : $this->_linkAttribute.'_notexist';
			$data = array (
							'magento_sku' => $product [$this->_indexVendorSku],
							'qty' => $product [$this->_indexStock],
							'vendor_sku' => $product [$this->_indexVendorSku],
							'cost' => $product [$this->_indexPrice] 
					);
			$this->setLogError('history',$data,$errorType);
			$isValid = false;
			//$this->_errorsCount ++;
		}elseif(count($product['product_id']) > 1 )
		{	
			$data = array (
					'magento_sku' => $product['magento_sku'],
					'qty' => $product [$this->_indexStock],
					'vendor_sku' => $product [$this->_indexVendorSku],
					'cost' => $product [$this->_indexPrice]
			);
			$this->setLogError('history',$data,$this->_linkAttribute.'_multiple');
			
			$isValid = false;
			//$this->_errorsCount ++;
		}elseif($this->_checkDuplicateCombination(null,$product)){
			$data = array (
					'magento_sku' => $product['magento_sku'],
					'qty' => $product [$this->_indexStock],
					'vendor_sku' => $product [$this->_indexVendorSku],
					'cost' => $product [$this->_indexPrice]
			);
			$this->setLogError('history',$data,'combination_exist');
			$isValid = false;
		}
		else{
			$isValid = $this->validate($product); //validating row values if all above cases passed
		}
			
		return $isValid;
	}
	/*
	 * prepare data for insert new relation in inventory table
	 */
	protected function prepareAddData(&$changedCsvData) {
		$magentoSkuArray = array();
		$helper = Mage::helper('dropship360');
		$stockItemTable = $helper->getTableName('cataloginventory/stock_item');
		$tableVendorInventory = $helper->getTableName('dropship360/inventory');
		$collection = Mage::getMOdel('catalog/product')->getCollection();
		$qtyQuery = '(select IFNULL(SUM(stock),0) from '.$tableVendorInventory.' where product_sku = e.sku and lb_vendor_code != "'.$this->_vendorCode.'") as qty';
		$isDecimalQuery = '(select is_qty_decimal from '.$stockItemTable.' where product_id = e.entity_id) as is_decimal';
		foreach($changedCsvData as $key=>$rowData){
			($rowData[$this->_indexVendorSku]) ? $magentoSkuArray[] = $rowData[$this->_indexVendorSku] : '';  
		}
		$collection->addAttributeToFilter($this->_linkAttribute,array('in'=>$magentoSkuArray));
		$collection->getSelect()->columns($isDecimalQuery);
		$collection->getSelect()->columns($qtyQuery);
		if($collection->getSize() > 0){
			foreach($changedCsvData as $rowNum => &$rowData){
				foreach($collection as $data){
					if($data->getData($this->_linkAttribute) == $rowData[$this->_indexVendorSku]){
							$rowData[$this->_indexStock] = ($data->getIsDecimal() || !is_numeric($rowData[$this->_indexStock])) ? $rowData[$this->_indexStock] : floor($rowData[$this->_indexStock]);
							$rowData['row_id'] =	$rowNum;
							$rowData['magento_sku'] = $data->getSku ();
							$rowData['product_qty'] = $data->getQty ();
							$rowData['product_id'][] = $data->getEntityId ();
							continue;
					}
				}
				
			}
		}	
	}
	/*
	 * create new entry in inventory table and update product inventory
	 */
	protected function generateNonExistingRel($validRow ){
		foreach($validRow as $rowNumber=>$rowData){
			$this->_validateCostQty($rowNumber,$rowData,'Add');
			if($isQtyVal = $this->prepareVendorInventory($rowData,'add')){//check if row have valid qty value.
				$this->prepareProductInventory($rowData);
			}
			$this->_successCount++;
		}
		$this->saveProductInventory();
		$this->insertVendorInventory();
		$this->saveInlineLog();
	}
	/*
	 * insert vendor detail in vendor inventory table
	 */
	protected function insertVendorInventory(){
		if($this->_vendorInventory):
	
		$vendorInventoryTable = Mage::helper('dropship360')->getTableName('dropship360/inventory');
		$dbFields = array('lb_vendor_code','lb_vendor_name','product_sku','lb_vendor_sku','stock','cost','created_at','updated_at');
		
		try {
			$this->_dbConnection->insertArray($vendorInventoryTable,$dbFields,$this->_vendorInventory);
			
			$this->_vendorInventory = array();
		} catch ( Exception $e ) {
			Mage::throwException('Error in inserting vendor inventory : '.$e->getMessage());
		}
	endif;
	}
	/*
	 * @return data for operation
	 * 1) update
	 * 2) insert
	 */
	protected function getArray($unsetItem = null,$values,$opr){
		if($opr == 'update'){
			$cost = (array_key_exists('cost', $values)) ? $values['cost'] : '';
			$stock = (array_key_exists('stock', $values)) ? $values['stock'] : '';
			$array = array('stock'=>$stock,'cost'=>$cost,'updated_at'=>now());
			if($unsetItem)
				unset($array[$unsetItem]);
		}else{
			$array = array(
					$this->_vendorCode, //0
					$this->_vendorName, //1
					$values ['magento_sku'],//2
					$values['vendor_sku'],//3
					$values['stock'],//4
					$values['cost'],//5
					now (),//6
					now () //7
			);
			if($unsetItem == 'cost')
				$array[5] = ''; //set cost
			if($unsetItem == 'stock')
				$array[4] = '';//set stock
			if($unsetItem == 'stock_cost'){
				$array[4] = ''; //set stock
				$array[5] = ''; //set cost
			}
		}
	return $array;
	}
	

	/*
	 * function used to update vendor inventory cost and qty
	*/
	
	protected function updateVendorInventory($changedCsvData){
		$this->_filterRowData($changedCsvData['change_row']);
		if($changedCsvData){
				foreach($changedCsvData['change_row'] as $rowNumber=>$rowData){
					$this->validateRowData($rowNumber,$rowData);
					if($isQtyVal = $this->prepareVendorInventory($rowData))//check if row have valid qty value.
						$this->prepareProductInventory($rowData);
				}
				$this->saveProductInventory();
				$this->saveVendorInventory();
				$this->saveInlineLog();
				//$this->prepareLogHistory($changedCsvData);
			}
	}
	
	/*
	 * create new relation for vendor_sku,vendor_code,magento_sku
	*/
	protected function addVendorInventory($changedCsvData){
		if(!$this->checkAttributeAval($this->_linkAttribute)){
			$data = implode(';',array_keys($changedCsvData['rel_not_exist']));
			$this->_manualCsvError['history']['attribute_notexist'][] = $data;
			//$this->setLogError('history',$data,'attribute_notexist');
			$this->_errorsCount = $this->_errorsCount + count($changedCsvData['rel_not_exist']);
			return ;
		}
		$this->prepareAddData($changedCsvData['rel_not_exist']);
		$validRow = array_filter($changedCsvData['rel_not_exist'],array($this,'validateGenerateProduct'));
		$this->generateNonExistingRel($validRow);
	}
	
	/*
	 * remove rows which have invalid cost and Qty
	* @ it will not remove row which have partial correct cost or qty
	*/
	protected function _filterRowData(&$changedCsvData) {
		$changedCsvData = array_filter($changedCsvData,array($this,'validate'));
	}
	
	/*	filter valid data
	 * 	csv row format
	*  $row[$this->_indexStock] = vendor_sku
	* 	$row[1] = qty
	*  $row[2] = cost
	*/
	protected function validate($rowData,$key = null) {
		$isValid = true;
		$colName = array();
		$errorCode = 'history';
	
		// col vendor_sku value is empty
		if(!isset($rowData[$this->_indexVendorSku])){
			$isValid = false;
			$colName[] =  'vendor_sku';
		}
	
		// col cost and qty value is non-numeric,-ve, #empty value allowed
		if((!is_numeric($rowData[$this->_indexStock]) || $rowData[$this->_indexStock] < 0) && (!is_numeric($rowData[$this->_indexPrice]) || $rowData[$this->_indexPrice] < 0)){
			$isValid = false;
			$colName[] =  'qty';
			$colName[] = 'cost';
			//for product details page inline error log
			$logdesc =  'Ignore';
			$this->addInlineLog('inline', $logdesc, $rowData);
			
			
		}
		(!$isValid) ? $this->addRowError($errorCode,$rowData['row_id'],$colName) : '';
		return 	$isValid;
	}
	/*
	 * validating cost or qty for partial update
	* @generating error for partial update
	*/
	protected function validateRowData($rowNumber,$rowData) {
		$this->_validateCostQty($rowNumber,$rowData);
		//$this->_validateMagentoSku($rowNumber,$rowData);
		//$this->_checkCombination($rowNumber,$rowData);
	}
	
	/*	filter valid data for partial update
	 * 	csv row format
	*   $rowData[0] = vendor_sku
	* 	$rowData[1] = qty
	*   $rowData[2] = cost
	*/
	protected function _validateCostQty($rowNumber,$rowData,$type = 'Update') {
		$ignoreData = array();
		$errorCode = 'inline';
	
		if((!is_numeric($rowData[$this->_indexStock]) || $rowData[$this->_indexStock] < 0) && (!is_numeric($rowData[$this->_indexPrice]) || $rowData[$this->_indexPrice] < 0)){
			$logdesc =  'Ignore';
		}elseif(!is_numeric($rowData[$this->_indexPrice]) || $rowData[$this->_indexPrice] < 0){
			$logdesc =  'Qty '.$type.', Cost Ignored';
		}elseif(!is_numeric($rowData[$this->_indexStock]) || $rowData[$this->_indexStock] < 0){
			$logdesc =  'Cost '.$type.', Qty Ignored';
		}else{
			$logdesc = $type;
		}
		$this->addInlineLog($errorCode, $logdesc, $rowData);
	}
	
	/*	if duplicate entry find discard row
	 * 	csv row format
	*  $rowData[0] = vendor_sku
	* 	$rowData[$this->_indexPrice] = qty
	*  $rowData[2] = cost
	*/
	protected function _checkDuplicateCombination($rowNumber = null,$rowData) {
		$isDuplicate = false;
		try {
		if(array_key_exists('magento_sku', $rowData)){
		$inventoryCollection = Mage::getModel('dropship360/inventory')->getCollection();
		$inventoryCollection->addFieldToFilter('product_sku',$rowData['magento_sku'])
		->addFieldToFilter('lb_vendor_code',$this->_vendorCode);
		$inventoryCollection->getSelect()->limit(1);
		if($inventoryCollection->getSize() > 0){
			$isDuplicate = true;
		if(array_key_exists('productSetup', $rowData))
			Mage::register('product_setup_vendor_id', $inventoryCollection->getFirstItem()->getId());
		}
		}
		return $isDuplicate;
		} catch (Exception $e) {
			Mage::throwException('Error in checking duplicate vendor : '.$e->getMessage());
			return;
		}
	}
	
	/*
	 * @var $_vendorInventory
	* use to update cost and qty possible case
	* invalid_qty : ignore qty and update cost
	* invalid_cost : ignore cost and update qty
	* both invalid : no update will perform
	* both valid : update cost and qty
	*/
	protected function prepareVendorInventory($rowData,$opration = 'update') {
		$isQtyVal = true;
		$qty = (!is_numeric($rowData[$this->_indexStock]) || $rowData[$this->_indexStock] < 0 || trim($rowData[$this->_indexStock]) =='') ? 'invalid_qty' : $rowData[$this->_indexStock];
		$cost = (!is_numeric($rowData[$this->_indexPrice]) || $rowData[$this->_indexPrice] < 0 || trim($rowData[$this->_indexPrice]) =='') ? 'invalid_cost' : $rowData[$this->_indexPrice];
		$rowId = array_key_exists('vendor_id',$rowData) ? $rowData['vendor_id'] : null;
		if((string)$qty == 'invalid_qty' && (string)$cost == 'invalid_cost'){
			if($rowId)
			$this->_vendorInventory[$rowId] = $this->getArray('stock_cost',null,'add');//no-update only insert
			else
				$this->_vendorInventory[] = $this->getArray('stock_cost',null,'add');//no-update only insert
			$isQtyVal = false;
		}elseif((string)$qty == 'invalid_qty'){
			if($rowId)
				$this->_vendorInventory[$rowId] = $this->getArray('stock',array('cost'=>$cost,'vendor_sku'=>$rowData[$this->_indexVendorSku],'magento_sku'=>$rowData['magento_sku']),$opration);
			else
				$this->_vendorInventory[] = $this->getArray('stock',array('cost'=>$cost,'vendor_sku'=>$rowData[$this->_indexVendorSku],'magento_sku'=>$rowData['magento_sku']),$opration);
			$isQtyVal = false;
		}elseif( (string)$cost == 'invalid_cost'){
			$qty = $this->calculateQty($rowData);
			if($rowId)
				$this->_vendorInventory[$rowId] = $this->getArray('cost',array('stock'=>$qty,'vendor_sku'=>$rowData[$this->_indexVendorSku],'magento_sku'=>$rowData['magento_sku']),$opration);
			else
				$this->_vendorInventory[] = $this->getArray('cost',array('stock'=>$qty,'vendor_sku'=>$rowData[$this->_indexVendorSku],'magento_sku'=>$rowData['magento_sku']),$opration);
		}else{
			$qty = $this->calculateQty($rowData);
			if($rowId)
				$this->_vendorInventory[$rowId] =	$this->getArray(null,array('stock'=>$qty,'cost'=>$cost,'vendor_sku'=>$rowData[$this->_indexVendorSku],'magento_sku'=>$rowData['magento_sku']),$opration);
			else
				$this->_vendorInventory[] =	$this->getArray(null,array('stock'=>$qty,'cost'=>$cost,'vendor_sku'=>$rowData[$this->_indexVendorSku],'magento_sku'=>$rowData['magento_sku']),$opration);
		}
		return $isQtyVal;
	}
	
	/*
	 * calculate vendor qty on the basis of buffer value
	* calculate complete product qty including current vendor qty
	*/
	protected function calculateQty($rowData,$productQty = false){
		$checkQty = (!is_numeric($rowData[$this->_indexStock]) || $rowData[$this->_indexStock] < 0 || trim($rowData[$this->_indexStock]) =='') ? 'invalid_qty' : $rowData[$this->_indexStock];
		if($checkQty == 'invalid_qty')
			return $rowData[$this->_indexStock];
		
		$qty = 0;
		if ($this->_bufferStock >= 0 && isset ( $this->_bufferStock )) {
			$qty = (($uploadQty = $checkQty - $this->_bufferStock) < 0) ? 0 : $uploadQty;
		} else {
			$qty = $checkQty;
		}
		if ($productQty) {
			$qty = $qty + $rowData ['product_qty'];
		}
		return $qty;
	}
	
	/*
	 * @var $_productInventory as array to do multiple update query for
	* product inventory
	*/
	protected function prepareProductInventory($rowData){
		$stockItem = Mage::getModel('cataloginventory/stock_item');
		$stockItem->loadByProduct($rowData['product_id']);
		$existStockData = $stockItem->getData();
		$existStockData['qty'] = $this->calculateQty($rowData,true);
		$existStockData['is_in_stock'] = ($existStockData['qty']) ? 1 : 0;
		unset($existStockData['type_id']);
		unset($existStockData['stock_status_changed_automatically']);
		unset($existStockData['use_config_enable_qty_increments']);
		$this->_productInventory[] = $existStockData;
	}
	
	/*
	 * @var $_inlineLog as array contain error string for vendor inline log
	 * $log = array(0=>$activity,1=>$rowData)
	*/
	protected function prepareInlineLog(){
		if (!array_key_exists('inline',$this->_manualCsvError)) {
			return;
		}
		$inline = $this->_manualCsvError['inline'];
		foreach($inline as $log){
			$this->_inlineLog[] =
			array (
					$this->_vendorCode,
					$this->_vendorName,
					$log [1] ['magento_sku'] ,//magento_sku
					$log [1] [$this->_indexPrice],//cost
					$this->calculateQty($log[1]),//stock
					$this->_updatedBy,//updateby
					$log[0],//activity
					now(),//created
					now()//update
			);
		}
	}
	
	/*
	 * @var $_historyLog log array which will be displayed on vendor upload history
	*/
	protected function prepareLogHistory($changedCsvData = array()){
		if (array_key_exists('history',$this->_manualCsvError)) {
			$rowError = $this->_manualCsvError['history'];
			foreach($rowError as $type=>$error){
				$this->_historyLog [] = array (
						'error_type' => $type,
						'value' => is_array($error) ? implode(';',$error) : $error
				);
			}
			//$this->_manualCsvError['history'] = array();
		}
		if (array_key_exists('unchange_row',$changedCsvData)){
			$unchangedData = $changedCsvData['unchange_row'];
			foreach ($unchangedData as $key=>$data){
				$this->_errorsCount ++;
				$this->_historyLog []  = array (
						'error_type' => ($data['sku']) ? 'data_notchnage' : 'magento_sku_exists',
						'value' => array (
								'magento_sku' => $data ['magento_sku'],
								'qty' => $data [$this->_indexStock],
								'vendor_sku' => $data [$this->_indexVendorSku],
								'cost' => $data [$this->_indexPrice]
						)
				);
					
			}
		}
		if (array_key_exists('rel_not_exist',$changedCsvData)){
			if(!isset($this->_linkAttribute)):
			$relNotExist = $changedCsvData['rel_not_exist'];
			foreach ($relNotExist as $key=>$data){
				$this->_errorsCount ++;
				$this->_historyLog []  = array (
						'error_type' => 'combination_notexist',
						'value' => array (
								'magento_sku' => '',
								'qty' => $data [$this->_indexStock],
								'vendor_sku' => $data [$this->_indexVendorSku],
								'cost' => $data [$this->_indexPrice]
						)
				);
					
			}
			endif;
		}
	
	}
	
	/*
	 * save cost and qty for vendor
	*/
	protected function saveVendorInventory() {
		$vendorInventoryTable = Mage::helper('dropship360')->getTableName('dropship360/inventory');
		foreach($this->_vendorInventory as $key=>$rowData){
			try{
				if(is_array($rowData)){
					$this->_dbConnection->update($vendorInventoryTable,$rowData,array('id = ?'=>$key));
					$this->_successCount++;
				}
			}catch (Exception $e){
				Mage::throwException('Error in saving vendor inventory : '.$e->getMessage());
				$this->_dbConnection->rollBack();
			}
		}
		$this->_vendorInventory = array();
	}
	
	/*
	 * save product inventory for magento
	*/
	protected function saveProductInventory() {
		try{
			$vendorInventoryTable = Mage::helper('dropship360')->getTableName('cataloginventory/stock_item');
			if($this->_productInventory){
				$this->_dbConnection->insertOnDuplicate($vendorInventoryTable,$this->_productInventory,array('qty','is_in_stock'));
				$this->_productInventory = array();
			}
		}catch (Exception $e){
			Mage::throwException('Error in saving product inventory : '.$e->getMessage());
			$this->_dbConnection->rollBack();
		}
	}
	
	/*
	 * save inline log available on product detail page
	*/
	protected function saveInlineLog() {
		try{
			$this->prepareInlineLog();
			$tableVendorInventoryLog = Mage::helper('dropship360')->getTableName('dropship360/inventorylog');
			$dbFields = array('lb_vendor_code','lb_vendor_name','product_sku','cost','stock','updated_by','activity','updated_at','created_at');
			if($this->_inlineLog){
				$this->_dbConnection->insertArray($tableVendorInventoryLog,$dbFields,$this->_inlineLog);
				$this->_inlineLog = array();
				unset($this->_manualCsvError['inline']);
			}
		}catch (Exception $e){
			Mage::throwException('Error in saving inline log : '.$e->getMessage());
			$this->_dbConnection->rollBack();
		}
	}
	
	/*
	 * save vendor complete csv upload error history
	*/
	protected function saveLogHistory() {
		try{
			$helper = Mage::helper('dropship360');
			$tableVendorImportLog = $helper->getTableName('dropship360/vendor_import_log');
			$tableVendorImportLogDesc = $helper->getTableName('dropship360/vendor_import_log_desc');
			$errType = ($this->_errorsCount > 0)  ? $this->_historyErrorType : '';
			$success = $this->_successCount;
			$failed = $this->_errorsCount;
			$dbFields = array('lb_vendor_code','updated_by','success','failure','ftp_error','created_at');
			$dbValue = array($this->_vendorCode,$this->_updatedBy,$success,$failed,$errType,now());
			$this->_dbConnection->insertArray($tableVendorImportLog,$dbFields,array($dbValue));
			$entityId = $this->_dbConnection->lastInsertId($tableVendorImportLog);
			foreach($this->_historyLog as $data)
				$this->_dbConnection->insertArray($tableVendorImportLogDesc,array('error_id','description'),array(array($entityId,Mage::helper('core')->jsonEncode($data))));
			$this->_historyLog = array();
		}catch (Exception $e){
			Mage::throwException('Error in saving history log : '.$e->getMessage());
			$this->_dbConnection->rollBack();
		}
		return $entityId;
	}
	/*
	 * @return data to be insert in vendor inventory history log 
	 */
	protected function setLogError($errorCode,$data,$type) {
		$this->_historyLog [] = array(
				'error_type' => $type,
				'value' => $data
		);
		$this->_errorsCount ++;
	}
	protected function setHistoryErrorType($type){
		return $this->_historyErrorType = $type;
	}
	/*
	 * 	@errorCode-type: inline
	* 	@inline : vendor inventory update log for product details page
	*/
	public function addInlineLog($errorCode, $logdesc,$rowData)
	{
		$this->_manualCsvError[$errorCode][] = array($logdesc,$rowData);
		return $this;
	}
}