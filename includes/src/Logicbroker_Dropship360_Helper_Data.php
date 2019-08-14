<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Helper_Data extends Mage_Core_Helper_Abstract
{
	const LOGICBROKER_ITEM_STATUS_TRANSMITTING   = 'Transmitting';
	const LOGICBROKER_ITEM_STATUS_SOURCING   = 'Sourcing';
	const LOGICBROKER_ITEM_STATUS_REPROCESS   = 'Reprocess';
	const LOGICBROKER_ITEM_STATUS_BACKORDER   = 'Backorder';
	const LOGICBROKER_ITEM_STATUS_SENT_TO_SUPPLIER   = 'Sent to Supplier';
	const LOGICBROKER_ITEM_STATUS_CANCELLED   = 'Cancelled';
	const LOGICBROKER_ITEM_STATUS_NO_DROPSHIP   = 'No Dropship';
	const LOGICBROKER_ITEM_STATUS_COMPLETED   = 'Completed';
	protected $_maxtime = 60; 	// time in minutes
    public function getConfigObject($nodeName = null)
    {
	return trim(Mage::getConfig()->getNode($nodeName)->__toString());
    }

    public function getItemStatuses()
    {
	return array('Sourcing','Reprocess','Backorder','Transmitting','Sent to Supplier','Cancelled','No Dropship','Completed');
    }


    /* LBN - 935 change */
    public function getIsQtyDecimal($product_sku,$inventory)
    {
		if($product_sku){
	      $is_qty_decimal = $this->checkIsQtyDecimal($product_sku);
	      if($is_qty_decimal == 1)
	      {
		$inventory = $inventory;
	      }
	      else
	      {
		$inventory = floor($inventory);
	      }

		}
	return $inventory;
    }

    public function checkIsQtyDecimal($product_sku){

		$is_qty_decimal = 0;
	    $product_collection = Mage::getResourceModel('catalog/product_collection');
	    $product_collection->getSelect()->join(Mage::getConfig()->getTablePrefix().'cataloginventory_stock_item', 'e.entity_id ='.Mage::getConfig()->getTablePrefix().'cataloginventory_stock_item.product_id');
	    $product_collection->getSelect()->where('e.sku = ' ."'" .$product_sku."'");
	    $product_collection_count =  count($product_collection);
	    if($product_collection_count > 0)
	    {
	      $is_qty_decimal = $product_collection->getFirstItem ()->getData('is_qty_decimal');

	    }

	return $is_qty_decimal;
    }
    /* End of LBN - 935 change */

     protected function getDatabaseConnection() {
	return Mage::getSingleton ( 'core/resource' );
    }

    protected function getTableName($name)
    {
	return $this->getDatabaseConnection()->getTableName ( $name );
    }

    public function isProcessRunning($type){

	$isProcessRunning = false;
	if($this->selectTmpTableData($type))
	{
		if(!$this->isProcessHault($type))
		{
			$isProcessRunning = true;
		}
	}

	return $isProcessRunning;
    }

    public function startProcess($type){

	if(!$this->selectTmpTableData($type))
	{
		$this->insertTmpTableData($type);
	}
	 return;
    }


    public function finishProcess($type){

	$this->deleteTmpTableData($type);

    }

    protected function insertTmpTableData($type){

	$write = $this->getDatabaseConnection()->getConnection ( 'core_write' );
	$updatedAt = Mage::getModel('core/date')->gmtDate();
	$createAt = Mage::getModel('core/date')->gmtDate();
	$tmpTableName = $this->getTableName ( 'logicbroker/tmpdata' );
	$insert = 'insert into '.$tmpTableName.' (tmpdata,created_at,updated_at) values("'.$type.'","'.$createAt.'","'.$updatedAt.'")';
	$write->beginTransaction ();
	$write->query($insert);
	try {
		$write->commit ();
	} catch ( Exception $e ) {
		$write->rollBack ();
	}


    }

    protected function updateTmpTableData($type){

	$write = $this->getDatabaseConnection()->getConnection ( 'core_write' );
	$updatedAt = Mage::getModel('core/date')->gmtDate();
	$tmpTableName = $this->getTableName ( 'logicbroker/tmpdata' );
	$update = 'update '.$tmpTableName.' set updated_at  = '.$updatedAt.' where tmpdata = "'.$type.'"' ;
	$write->beginTransaction ();
	$write->query($update);
	try {
		$write->commit ();

	} catch ( Exception $e ) {
		$write->rollBack ();
	}
    }

    protected function deleteTmpTableData($type){

	$write = $this->getDatabaseConnection()->getConnection ( 'core_write' );
	$tmpTableName = $this->getTableName ( 'logicbroker/tmpdata' );
	$delete = 'delete from '.$tmpTableName.' where tmpdata = "'.$type.'"' ;
	$write->beginTransaction ();
	$write->query($delete);
	try {
		$write->commit ();

	} catch ( Exception $e ) {
		$write->rollBack ();
	}
    }

    public function selectTmpTableData($type){

	$read = $this->getDatabaseConnection()->getConnection ( 'core_read' );
	$tmpTableName = $this->getTableName ( 'logicbroker/tmpdata' );
	$select = 'Select * from '.$tmpTableName.' where tmpdata = "'.$type.'" ORDER BY id DESC limit 1';
	$result = $read->fetchAll($select);

	if(count($result) >= 1)
	$this->_lastUpdateTime = $result[0]['updated_at'];

	return count($result) <= 0 ? false : true;



    }

    protected function isProcessHault($type){
	$now = strtotime(Mage::getModel('core/date')->date('Y-m-d H:i:s'));
		$lastupdate = Mage::getModel('core/date')->timestamp(strtotime($this->_lastUpdateTime));


	$isProcessHault = true;
	$timePassed = ($now - $lastupdate)/60;

	if($timePassed <= $this->_maxtime){

		$isProcessHault = false;
	}else{
		
		$this->finishProcess($type);
	}

	return $isProcessHault;

    }
    /*
     *
     * $state 0,1,2 // 0= start without sepration text,1=Start logging section sepration,2=end logging section sepration
     *
     *
     * */
    public function genrateLog($state,$startMessage = null,$endMessage =null,$message = null){
	if($state == 1){
		Mage::log('******'.$startMessage.'******', null, 'logicbroker_debug.log');
	}
	if($state == 0 && !empty($startMessage) ){
		Mage::log('******'.$startMessage.'******', null, 'logicbroker_debug.log');
	}
	if(!empty($message)){
	Mage::log($message, null, 'logicbroker_debug.log');
	}
	if($state == 2){
		Mage::log('******'.$endMessage.'******', null, 'logicbroker_debug.log');
	}
	if($state == 0 && !empty($endMessage) ){
		Mage::log('******'.$endMessage.'******', null, 'logicbroker_debug.log');
	}
    }

	 protected function getLogCollection($params){
		$conn = Mage::getModel('logicbroker/uploadvendor')->getDatabaseConnection();
		$tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/vendor_import_log' );
		$select = $conn->select()->from($tableVendorImportLog)
								->where("created_at=?", $params['vdate']);
		$result = $conn->query($select);
		$rows = $result->fetch();
		return $rows;
	}

    /**
     * Returns indexes of the fetched array as headers for CSV
     *
     * @return array
     */
    protected function _getCsvHeaders()
    {
	$headers = array(0 => 'Date', 1 => 'Message') ;
	return $headers;
    }

    /**
     * Generates CSV file with error's list according to the collection in the $this->_list
     * @return array
     */
    public function generateErrorList($params)
    {
	    $this->_list = $this->getLogCollection($params);
	$ftpError = explode('<li>', $this->_list['ftp_error_desc']);
	if (!is_null($this->_list)) {
	    $items = $this->_list;
	    if (count($items) > 0) {
		$io = new Varien_Io_File();
		$path = Mage::getBaseDir('var') . DS . 'export' . DS;
		$name = md5(microtime());
		$file = $path . DS . $name . '.csv';
		$io->setAllowCreateFolders(true);
		$io->open(array('path' => $path));
		$io->streamOpen($file, 'w+');
		$io->streamLock(true);
		$io->streamWriteCsv($this->_getCsvHeaders());

				$error = array();
				foreach( $ftpError as $ferror){
					if(strip_tags($ferror) != "") {
						$date = Mage::helper('core')->formatDate($this->_list['created_at'], 'medium', true);
						$error[] = array(0=>$date, 1=>strip_tags($ferror));
					}
				}
		foreach ($error as $e) {
						$io->streamWriteCsv($e);
		}
		return array(
		    'type'  => 'filename',
		    'value' => $file,
		    'rm'    => true
		);
	    }
	}
    }

	/**
	 * LB Item status for orders
     *
	 *	@return array
	 */
	public function getLbOrderItemStatus()
	{
		return array('Reprocess', 'Backorder', 'Transmitting', 'Sent to Supplier', 'No Dropship');

	}

	/*
	 * Get serialised data for order item history
	 * @param object $orderItemInstance, string $itemStatus, string $orderStatus
     * @return string
	 */
	public function getSerialisedData($orderItemInstance, $itemStatus, $orderStatus )
	{
		if($orderItemInstance->getItemStatusHistory()){
			$existingValues = unserialize($orderItemInstance->getItemStatusHistory());
			$data = array('date'=>now(), 'item_status'=>$itemStatus, 'order_status'=>$orderStatus);
			array_push($existingValues,$data);
			$serializeData = serialize($existingValues);
		}else{
			$data = array(0=>array('date'=>now(), 'item_status'=>$itemStatus, 'order_status'=>$orderStatus));
			$serializeData = serialize($data);
		}
		return $serializeData;
	}
}
