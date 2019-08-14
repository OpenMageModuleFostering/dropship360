<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 * Product setup with default cost = "" and price = ""
 * generate new relation between VSKU,VC and MSKU if already exists than ignore
 */

class Logicbroker_Dropship360_Model_Import_Productsetup extends Logicbroker_Dropship360_Model_Import_Base
{
	protected $productSetupCSVFormat = array('magento_sku','vendor_sku');
	protected $_indexMagentoSku = 0;
	protected $_indexVendorSku = 1;
	protected $_newVendorSkuForProductSetup = array();
	/*
	 * $_vendorSkuArray is used to store vendorSku this array
	 * should contain unique array value if not throw error
	 * @csv_productsetup_test_case - csv file has same vendor sku
	 * 
	 */
	protected $_vendorSkuArray = array();
	
	public function validateCsvHeader($colNames)
	{
		$headerValidation = true;
		$result = array_diff($colNames,$this->productSetupCSVFormat);
		if(count($result) > 0){
			$headerValidation = false;
		}
		return $headerValidation;
	}
	
	
	public function startImport($csvData,$vendorCode){
		$this->initVar($vendorCode);
		$this->_csvParserObj = Mage::getModel('dropship360/csvparser');
		//$changedCsvData = array();
		$sliceCsv = array_chunk($csvData, $this->_chunkSize,true);
		foreach($sliceCsv as $slicedData){
			/* 	@var $changedCsvData
			 * 	filter CSV row as we need to process only those records
			*	which are present in our inventory table
			*/
			$historyLogBefore = $this->_historyLog;
			try {
				$slicedData = $this->_filterRowData($slicedData);
				foreach($slicedData as $row){
					$validationStatus = $this->isVendorRelExists($row);
					if(!$validationStatus){
						$this->addVendorInventory($row);
					}
				}
				$this->updateVendorSkuForProductSetup();
				$this->insertVendorInventory();
				$this->saveInlineLog();
				$this->prepareLogHistory();
				$this->_manualCsvError = array();
				
			} catch (Exception $e) {
				$this->_historyLog = $historyLogBefore;
				Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
				Mage::log('error in import :'.$e->getMessage(), null, 'logicbroker_manual_vendor_inventory_import.log');
				continue;
			}
		}
		$this->saveLogHistory();
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
	
		// col magento_sku value is empty
		if(empty($rowData[$this->_indexMagentoSku])){
			$isValid = false;
			$colName[] =  'magento_sku';
		}
		
		// col vendor_sku value is empty
		if(empty($rowData[$this->_indexVendorSku])){
			$isValid = false;
			$colName[] =  'vendor_sku';
		}
		
		(!$isValid) ? $this->addRowError($errorCode,$key,$colName) : '';
		return 	$isValid;
	}
	/*
	 * remove rows which have invalid cost and Qty
	* @ it will not remove row which have partial correct cost or qty
	*/
	protected function _filterRowData($slicedData) {
		$changedCsvData = array();
		foreach($slicedData as $key=>$row){
			if(!$this->validate($row,$key))
				continue;
			$changedCsvData[$key] = $row;
		}
		return $changedCsvData;
	}
	protected function isVendorRelExists($row){
		$isRelFound = false;
		//check if magento sku exists
		if(!Mage::getModel('catalog/product')->getIdBySku(trim($row[$this->_indexMagentoSku]))){
			$isRelFound = true;//log error relation already exists
			$data = array (
					'magento_sku' =>$row[$this->_indexMagentoSku],
					'qty' => '',
					'vendor_sku' =>$row[$this->_indexVendorSku],
					'cost' => ''
			);
			$this->setLogError('history', $data, 'magento_sku_exists');
			return $isRelFound;
		}
		
		if($this->checkVCVSMSUnique($row)){
			return $isRelFound = true;
		}
		
		if($this->checkVCVSUnique($row)){
			return $isRelFound = true;
		}
		
		if(count($this->_vendorSkuArray) > 0 && in_array($row[$this->_indexVendorSku],$this->_vendorSkuArray )){
			$isRelFound = true;//log error relation already exists
			$data = array('vendor_sku'=>$row[$this->_indexVendorSku]);
			$this->setLogError('history', $data, 'already_assigned');
			return $isRelFound;
		}else{
			$this->_vendorSkuArray[] = $row[$this->_indexVendorSku];
		}
		/*
		 * check magento_sku has already assigned vendorCode
		 * in this case we need to update vendor_sku before updating
		 * we need to check vendorCode and vendorSku should have unique
		 * combination in logicbroker_vendor_inventory table
		*/
		$rowData = array('magento_sku'=>$row[$this->_indexMagentoSku],'productSetup'=>true);
		if($this->_checkDuplicateCombination(null,$rowData)){
			$errorCode = 'inline';
			$data = array (
					'magento_sku' => $row[$this->_indexMagentoSku],
					$this->_indexStock => '',
					$this->_indexPrice => '' 
			);
			$this->addInlineLog($errorCode, 'Product Setup - vendor sku updated', $data);
			$this->_newVendorSkuForProductSetup [Mage::registry ( 'product_setup_vendor_id' )] = array (
					'lb_vendor_sku' => $row[$this->_indexVendorSku],
					'updated_at' => now()
			);
			Mage::unregister('product_setup_vendor_id');
			return $isRelFound = true;
		}
		
		return $isRelFound;
	}
	//check lb_vendor_code && lb_vendor_sku && magento_sku is not duplicate
	protected function checkVCVSMSUnique($row){
		$isRelFound = false;
		$vendorCollection = Mage::getModel('dropship360/inventory')->getCollection();
		$vendorCollection->addFieldToFilter('lb_vendor_code',$this->_vendorCode)
		->addFieldToFilter('product_sku',$row[$this->_indexMagentoSku])
		->addFieldToFilter('lb_vendor_sku',$row[$this->_indexVendorSku]);
		if($vendorCollection->getSize() > 0){
			$isRelFound = true;//log error relation already exists
			$data = array (
					'vendor_sku' => $row [$this->_indexVendorSku],
					'vendor_code' => $this->_vendorCode,
					'magento_sku' => $row [$this->_indexMagentoSku]
			);
			$this->setLogError('history', $data, 'combination_exist');
			return $isRelFound;
		}
		return $isRelFound;
		
	}
	//check lb_vendor_code && lb_vendor_sku is unique
	// 
	protected function checkVCVSUnique($row){
		$isRelFound = false;
		$vendorCollection = Mage::getModel('dropship360/inventory')->getCollection();
		$vendorCollection->addFieldToFilter('lb_vendor_code',$this->_vendorCode)
		->addFieldToFilter('lb_vendor_sku',$row[$this->_indexVendorSku]);
		if($vendorCollection->getSize() > 0){
			$isRelFound = true;//log error relation already exists
			$data = array('vendor_sku'=>$row[$this->_indexVendorSku]);
			$this->setLogError('history', $data, 'already_assigned');
			return $isRelFound;
		}
		return $isRelFound;
	}
	protected function addVendorInventory($rowData){
		$errorCode = 'inline';
		$this->prepareVendorInventory($rowData,'add');
		$data = array (
				'magento_sku' => $rowData [$this->_indexMagentoSku],
				$this->_indexStock => '',
				$this->_indexPrice => '' 
		);
		$this->addInlineLog($errorCode, 'Product Setup', $data);
	}
	
	/*
	 * @var $_vendorInventory
	* use to update cost and qty possible case
	* invalid_qty : ignore qty and update cost
	* invalid_cost : ignore cost and update qty
	* both invalid : no update will perform
	* both valid : update cost and qty
	*/
	protected function prepareVendorInventory($rowData) {
		$this->_vendorInventory [] = array($this->_vendorCode, //VC
					$this->_vendorName, //VN
					$rowData [$this->_indexMagentoSku],//MSKU
					$rowData[$this->_indexVendorSku],//VSKU
					'',//stock
					'',//cost
					now (),//Create
					now () //update
			);
		$this->_successCount++;
	}
	
	protected function updateVendorSkuForProductSetup(){
		$vendorInventoryTable = Mage::helper('dropship360')->getTableName('dropship360/inventory');
		foreach($this->_newVendorSkuForProductSetup as $key=>$rowData){
			try{
				if(is_array($rowData)){
					$this->_dbConnection->update($vendorInventoryTable,$rowData,array('id = ?'=>$key));
					$this->_successCount++;
				}
			}catch (Exception $e){
				Mage::throwException('Error in updating vendor sku for product setup : '.$e->getMessage());
				$this->_dbConnection->rollBack();
			}
		}
		$this->_newVendorSkuForProductSetup = array();
	}
}