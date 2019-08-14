<?php

/**
 * Logicbroker
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Csvparser
{
	
	protected $dbConnection;
	protected $tableName;
	protected $vendorObject;
	protected $chunksize = 50;
	//protected $chnagedValue = array();
	protected $ftpCSVFormat =  array('vendor_code','vendor_sku','qty','cost');
	protected $manualCSVFormat = array('vendor_sku','qty','cost');
	protected $productSetupCSVFormat = array('magento_sku','vendor_sku');
	protected $_exportHeaders = array('Supplier Code','Supplier','Magento Sku','Vendor Sku','Cost','Inventory','Last Sync Date');
	protected $_indexVendorSku = 0;
	protected $_indexStock = 1;
	protected $_indexPrice = 2;

	public function __construct(){
		
		$this->dbConnection = Mage::helper('dropship360')->getDatabaseConnection();
		$this->tableName = Mage::helper('dropship360')->getTableName('dropship360/csvtmpdata');
		$this->vendorObject = Mage::getModel('dropship360/inventory');
	}
	
	// check is csv for manual or ftp other csv will not process data 
	protected function isProcessRequired($header)
	{
		$result = true;
		if($header[0] == 'vendor_code')
		{
			$diffArray=array_diff($this->ftpCSVFormat,$header);
			$result = (count($diffArray) == 0); 
		}else
		{
			$diffArray=array_diff($this->manualCSVFormat,$header);
			$result = (count($diffArray) == 0);
		}
		//var_dump($result);
		//die;
		return $result;
	}
	
	protected function checkFtpHeader($header)
	{
		if(count($header) == 4 )
		{
			$this->_indexVendorSku = 1;
			$this->_indexStock = 2;
			$this->_indexPrice = 3;
		}
	}
	
	public function getChangedValue($csvData,$vendorCode){

		$parsedData = array();
		if(count($csvData) <= 1 || !$this->isProcessRequired($csvData[0]))
		{
			return $csvData;
		}
	$this->emptyTable();
	$this->checkFtpHeader($csvData[0]);
	$insertQuery = $this->insertMultiple($csvData,$vendorCode);
	if($insertQuery/*$this->executeStm($insertQuery)*/){
		$parsedData = $this->prepareCsvjoin($csvData[0]);
		//array_unshift($parsedData,$csvData[0]);
	}	
	else{
		$parsedData =  $csvData;
	}
	return $parsedData;
	}
	
	protected function insertMultiple($csvData,$vendorCode){
		$connection = $this->dbConnection->getConnection ( 'core_write' );
		$csvArray = array();
		$chunkCsvData = array_chunk($csvData,$this->chunksize);
		foreach($chunkCsvData as $value)
		{
			foreach ($value as $key=>$data)
			{
				if($key == 0)
				continue;
				$csvArray[] = array('vendor_code'=>$vendorCode,'csv_vendor_sku'=>trim($data [$this->_indexVendorSku]),'csv_stock'=>$data [$this->_indexStock],'csv_price'=>$data [$this->_indexPrice]);
			}
			try {
				$connection->insertOnDuplicate($this->tableName,$csvArray,array('csv_vendor_sku','csv_stock','csv_price'));
			} catch (Exception $e) {
				Mage::log($e->getTrace(),null,'logicbroker_debug.log');
				return false;
			}
			$csvArray = array();
		}
		return true;
	}
	
	protected function executeStm($query){
		
		$write = $this->dbConnection->getConnection ( 'core_write' );
		$write->beginTransaction ();
		$write->query($query);
		try {
			$write->commit ();
			return true;
		} catch ( Exception $e ) {
			$write->rollBack ();
			Mage::log($e->getMessage(), null, 'vendor_inventory_import_error.log');
			return false;
		}
				
	}
	
	protected function prepareCsvjoin($header){
		$chnagedValue =array();
		$chnagedValue[] = $header;
		$collection = $this->vendorObject->getCollection()->addFieldToSelect(array('lb_vendor_code','lb_vendor_sku','stock','cost'));
		$collection->getSelect()->join(array('inventory'=>$this->tableName),
				'inventory.vendor_code = main_table.lb_vendor_code and inventory.csv_vendor_sku = main_table.lb_vendor_sku')->where('inventory.csv_stock != main_table.stock or inventory.csv_price != main_table.cost');

		 if($collection->getSize() > 0 ){

		 	foreach($collection as $data){
		 		$query = 'UPDATE '.$this->tableName.' set is_processed = 1 where csv_vendor_sku = "'.$data->getCsvVendorSku().'" and vendor_code = "'.$data->getLbVendorCode().'"';
		 		$chnagedValue[$data->getRowId()] = array($data->getCsvVendorSku(),$data->getCsvStock(),$data->getCsvPrice());
		 		$this->executeStm($query);
		 	}
		 }
		return $chnagedValue;
	}
	
	public function emptyTable()
	{
		$write = $this->dbConnection->getConnection ( 'core_write' );
		$query = 'TRUNCATE TABLE '.$this->tableName;
		try {
			$write->query($query);
		} catch ( Exception $e ) {
			
			Mage::log($e->getMessage(), null, 'vendor_inventory_import_error.log');
			return false;
		}
			
	}
	public function getCsvFile($itemObject)
	{
		$io = new Varien_Io_File();
		$path = Mage::getBaseDir('var') . DS . 'export' . DS;
		$name = md5(microtime());
		$file = $path . DS . $name . '.csv';
		$io->setAllowCreateFolders(true);
		$io->open(array('path' => $path));
		$io->streamOpen($file, 'w+');
		$io->streamLock(true);
		$io->streamWriteCsv($this->_exportHeaders);
		$this->_exportCsvItem($itemObject,$io);
		$io->streamUnlock();
		$io->streamClose();
		return array(
				'type'  => 'filename',
				'value' => $file,
				'rm'    => true // can delete file after use
		);
	}
	protected function _exportCsvItem($itemObject, Varien_Io_File $adapter)
	{
		$row = array();
		foreach ($itemObject as $item) {
			$row[] = $item->getLbVendorCode();
			$row[] = $item->getLbVendorName();
			$row[] = $item->getProductSku();
			$row[] = $item->getLbVendorSku();
			$row[] = $item->getCost();
			$row[] = $item->getStock();
			$row[] = $this->formatDate($item->getUpdatedAt());
			$adapter->streamWriteCsv($row);
			unset($row);
		}
	}
	public function formatDate($date){
		$format = Mage::app()->getLocale()->getDateTimeFormat(
				Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM
		);
		$date = Mage::app()->getLocale()
		->date($date, Varien_Date::DATETIME_INTERNAL_FORMAT)->toString($format);
		return $date;
	}
	public function generateManualCsvRow($csvData,$isProductSetupMode,$lb_vendor_code)
	{
		$records = array();
		foreach($csvData as $row => $csvRowData)
		{
			if($row == 0)
				continue;
			if(!$isProductSetupMode)
			{
				(count($csvRowData) <= 3) ? array_unshift($csvRowData, "") : $csvRowData;
				if(is_numeric($csvRowData[2])){
					/* LBN - 935 change */
					$magento_sku = Mage::getModel('dropship360/uploadvendor')->getMagentoSku($lb_vendor_code, trim($csvRowData[1]));
					$csvqty = (!empty($magento_sku)) ? Mage::helper('dropship360')->getIsQtyDecimal($magento_sku,$csvRowData[2]) : $csvRowData[2];;
				}
				else
				{
					$csvqty = $csvRowData[2];
				}
			}
			if(!$isProductSetupMode)
				$records[$row] = array('vendor_sku'=>trim($csvRowData[1]),'qty'=>$csvqty ,'cost'=>$csvRowData[3],'lb_vendor_code'=>$lb_vendor_code);
			else
				$records[$row] = array('magento_sku'=>trim($csvRowData[0]),'vendor_sku'=>trim($csvRowData[1]),'qty'=>0 ,'cost'=>0,'lb_vendor_code'=>$lb_vendor_code);
		}
		return $records;
	}
	public function generateFtpCsvRow($csvData,$vendorCode)
	{
		$records = array();
	foreach($csvData as $row => $csvRowData)
    	{
    		if($row == 0)
    			continue;
    		//patch for backwards compatible for ftp change lbn-1070
			(count($csvRowData) <= 3) ? array_unshift($csvRowData, "") : $csvRowData;
			if(is_numeric($csvRowData[2]) || $csvRowData[2] > 0){
                /* LBN - 935 change */
               $magento_sku = Mage::getModel('dropship360/uploadvendor')->getMagentoSku($vendorCode, trim($csvRowData[1]));
               (! empty ( $magento_sku )) ? $qty = Mage::helper ( 'dropship360' )->getIsQtyDecimal ( $magento_sku, $csvRowData [2] ) : $qty = $csvRowData [2];  
                /* End of LBN - 935 change */
			}else{
				$qty = $csvRowData[2];
			}
    		$records[$row] = array('lb_vendor_code'=>$vendorCode,'vendor_sku'=>trim($csvRowData[1]),'qty'=>$qty ,'cost'=>$csvRowData[3]);
    		//$vendorCode = $csvRowData[0];
    	}
		return $records;
	}
	 public function isCsvFileEmpty()
	 {
	 	return (count($this->getCsvRows()) > 0) ? false : true;
	 }
	 protected function getCsvRows()
	 {
	 	$rows = array();
	 	$conn = $this->dbConnection->getConnection ( 'core_read' );
	 	$select = $conn->select()->from($this->tableName)->where('is_processed = 0');
	 	$stmt = $conn->query($select);
	 	$rows = $stmt->fetchAll();
	 	return $rows;
	 }
	public function getUnprocessedCsvRows($vendorCode,$isFtp){
		$records = array();
		$rows = $this->getCsvRows();
		if(count($rows) > 0 ){
			foreach($rows as $row){
				$records[$row['row_id']] = array('lb_vendor_code'=>$vendorCode,'vendor_sku'=>trim($row['csv_vendor_sku']),'qty'=>$row['csv_stock'] ,'cost'=>$row['csv_price']);
			}
		}
		return $records;
	}
}
	 