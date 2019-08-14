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
	protected $chunksize = 100;
	protected $_vendorCode;
	protected $ftpCSVFormat =  array('vendor_code','vendor_sku','qty','cost');
	protected $manualCSVFormat = array('vendor_sku','qty','cost');
	protected $productSetupCSVFormat = array('magento_sku','vendor_sku');
	protected $_exportHeaders = array('Supplier Code','Supplier','Magento Sku','Vendor Sku','Cost','Inventory','Last Sync Date');
	protected $_indexVendorSku = 0;
	protected $_indexStock = 1;
	protected $_indexPrice = 2;
	protected $_vendorName;

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
	
	/*
	 * check ftp backward compatibility header
	 */
	protected function checkFtpHeader($header)
	{
		if(count($header) == 4 )
		{
			$this->_indexVendorSku = 1;
			$this->_indexStock = 2;
			$this->_indexPrice = 3;
		}
	}
	
	/*
	 * set vendorCode and vendorName
	 */
	protected function setVendorDetails($vendorCode) {
		if(isset($this->_vendorCode) && isset($this->_vendorAttribute))
			return;
		$ranking = Mage::getModel('dropship360/ranking')->load($vendorCode,'lb_vendor_code');
		$this->_vendorCode = $vendorCode;
		//$this->_vendorAttribute = $ranking->getLinkingAttribute();
		$this->_vendorName = $ranking->getLbVendorName();
	}
	/*
	 * @return array in following from
	 * @change_row => values has been chnaged
	 * @rel_not_exist => relation not exist in vendor inventory table   
	 */
	public function getChangedValue($csvData,$vendorCode,$header = array()){

	$parsedData = array();
	$this->setVendorDetails($vendorCode);
	$this->emptyTable();
	$this->checkFtpHeader($header);
	//insert csv data in csv_temp_table 
	$insertQuery = $this->insertMultiple($csvData,$vendorCode);
	//if error than return orignal csvdata
	if($insertQuery){
		$parsedData = $this->prepareCsvjoin();
	}	
	else{
		$parsedData =  $csvData;
	}
	return $parsedData;
	}
	/*
	 * Insert records in csv tmp table.
	 */
	protected function insertMultiple($csvData,$vendorCode){
		$connection = $this->dbConnection->getConnection ( 'core_write' );
		$csvArray = array();
		
		foreach ($csvData as $key=>$data)
			{
				//if row is empty
				if(count(array_filter($data, "strlen")) == 0 )
				continue;
				$csvArray[] = array('row_id'=>$key,'vendor_code'=>$vendorCode,'csv_vendor_sku'=>trim($data [$this->_indexVendorSku]),'csv_stock'=>$data [$this->_indexStock],'csv_price'=>$data [$this->_indexPrice]);
			}
			try {
				$connection->insertOnDuplicate($this->tableName,$csvArray,array('csv_vendor_sku','csv_stock','csv_price'));
			} catch (Exception $e) {
				Mage::log($e->getTrace(),null,'logicbroker_debug.log');
				return false;
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
	
	/*
	 * prepare CSV data with additional column along CSV are totalQty,isDecimal,sku,product_id
	 * @return array change_row,unchange_row,rel_not_exist
	 */
	protected function prepareCsvjoin(){
		$changedValue =array();
		//$changedValue[] = $header;
		$helper = Mage::helper('dropship360');
		$collection = $this->vendorObject->getCollection();
		$stockItemTable = $helper->getTableName('cataloginventory/stock_item');
		$catalogProductTable = $helper->getTableName('catalog/product');
		$isDecimalQuery = '(select is_qty_decimal from '.$stockItemTable.' where product_id = catalog.entity_id) as is_decimal';
		$qtyQuery = '(select IFNULL(SUM(stock),0) from '.$collection->getMainTable().' where product_sku = main_table.product_sku and lb_vendor_code != main_table.lb_vendor_code) as qty';
		
		$collection->addFieldToSelect(array('lb_vendor_code','lb_vendor_sku','stock','cost','product_sku'));
		$collection->getSelect()
		->join(array('inventory'=>$this->tableName),'inventory.vendor_code = main_table.lb_vendor_code and inventory.csv_vendor_sku = main_table.lb_vendor_sku')
		->where('inventory.csv_stock != main_table.stock or inventory.csv_price != main_table.cost');
		$collection->getSelect()
		->join(array('catalog'=>$catalogProductTable),'catalog.sku = main_table.product_sku',array('entity_id'));
		$collection->getSelect()->columns($qtyQuery);
		$collection->getSelect()->columns($isDecimalQuery);
		
		if($collection->getSize() > 0 ){
			foreach($collection as $data){
		 		$csvArray[] = array('vendor_code'=>$data->getLbVendorCode(),'csv_vendor_sku'=>$data->getCsvVendorSku());
		 		$stock = ($data->getIsDecimal() || !is_numeric($data->getCsvStock()) ) ? $data->getCsvStock() : floor($data->getCsvStock());
		 		$changedValue['change_row'][$data->getRowId()] = array($data->getCsvVendorSku(),$stock,$data->getCsvPrice(),'row_id'=>$data->getRowId(),'magento_sku'=>$data->getProductSku(),'product_qty'=>$data->getQty(),'product_id'=>$data->getEntityId(),'vendor_id'=>$data->getId());
		 	}
		 	$dbobj = $this->dbConnection->getConnection('core_write');
		 	$dbobj->insertOnDuplicate($this->tableName,$csvArray,array('is_processed'=>1));
		 	$csvArray = array();
		 }
		 $this->addExtraCol($changedValue);
		 return $changedValue;
	}
	//adding extra col in array to minimize DB call
	public function addExtraCol(&$changedValue){
		$helper = Mage::helper('dropship360');
		$inventoryTable = Mage::helper('dropship360')->getTableName('dropship360/inventory');
		$catalogProductTable = $helper->getTableName('catalog/product');
		$connection = $this->dbConnection->getConnection('core_write');
		$select = $connection->select()
		->from(array('csvtmpdata' => $this->tableName))
    	->joinleft(
    			array('vendor_inventory' => $inventoryTable),
    			'csvtmpdata.vendor_code = vendor_inventory.lb_vendor_code and csvtmpdata.csv_vendor_sku = vendor_inventory.lb_vendor_sku',array('lb_vendor_code','lb_vendor_sku','stock','cost','product_sku'))
		->joinleft(
    			array('catalogproduct'=>$catalogProductTable),
    			'catalogproduct.sku = vendor_inventory.product_sku',array('sku'))		
    			->where('csvtmpdata.is_processed = ?',0);
		$csvtmpdata = $connection->fetchAll($select);
		if(count($csvtmpdata) > 0 ){
			foreach($csvtmpdata as $data){
				if($data['lb_vendor_code'] || $data['lb_vendor_sku']){
		 			$changedValue['unchange_row'][$data['row_id']] = array($data['csv_vendor_sku'],$data[csv_stock],$data['csv_price'],'magento_sku'=>$data['product_sku'],'sku'=>$data['sku']);
				}else{
					$changedValue['rel_not_exist'][$data['row_id']] = array($data['csv_vendor_sku'],$data[csv_stock],$data['csv_price']);
				}
		 	}
		 }
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
	 