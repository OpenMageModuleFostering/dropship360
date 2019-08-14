<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 * Manual vendor inventory import
 * product attribute used upc,manufacturer_part_number,sku for insert new relation
 */

class Logicbroker_Dropship360_Model_Import_Manualimport extends Logicbroker_Dropship360_Model_Import_Base
{
	protected $manualCSVFormat = array('vendor_sku','qty','cost');
	protected $_csvParserObj;
	protected $_chunkSize = 1000;
	
	public function validateCsvHeader($colNames){
		$headerValidation = true;
		$result = array_diff($colNames,$this->manualCSVFormat);
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
			$changedCsvData = $this->_csvParserObj->getChangedValue($slicedData,$vendorCode);
			//condition used for update existing relation in logicbroker_inventory_table  
			if(array_key_exists('change_row',$changedCsvData)){
				$this->updateVendorInventory($changedCsvData);
			}
			//condition used for add new relation in logicbroker_inventory_table according to attribute selected
			if(array_key_exists('rel_not_exist',$changedCsvData) && $this->_linkAttribute){
				$this->addVendorInventory($changedCsvData);
			}
			$this->prepareLogHistory($changedCsvData);
			$this->_manualCsvError = array();
			$changedCsvData = array();
			} catch (Exception $e) {
				$this->_historyLog = $historyLogBefore;
				Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
				Mage::log('error in import :'.$e->getMessage(), null, 'logicbroker_manual_vendor_inventory_import.log');
				continue;
			}
		}
		$this->saveLogHistory();
	}	
}