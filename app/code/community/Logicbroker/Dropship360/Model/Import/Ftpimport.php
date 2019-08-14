<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 * ftp vendor inventory import 
 * product attribute used upc,manufacturer_part_number,sku for insert new relation 
 */

class Logicbroker_Dropship360_Model_Import_Ftpimport extends Logicbroker_Dropship360_Model_Import_Base
{
	protected $_ftp;
	protected $ftpCSVFormatbacward = array('vendor_code','vendor_sku','qty','cost');
	protected $_colNamesFtp;
	protected $_rowDataFtp;
	const XML_PATH_UPLOAD_ENABLED          = 'logicbroker_sourcing/cron_settings_upload/enabled';
	const XML_PATH_UPLOAD_FTP_ACCNUMBER  = 'logicbroker_sourcing/cron_settings_upload/ftp_accnumber';
	const XML_PATH_INVENTORY_NOTIFICATION_EMAIL  = 'logicbroker_sourcing/inventory_notification/email';
	const XML_PATH_INVENTORY_NOTIFICATION_EMAIL_ENABLED  = 'logicbroker_sourcing/inventory_notification/enabled';
	
	public function __construct(){
		$this->_ftp = Mage::getModel('dropship360/import_ftp');
		$this->_dbConnection = Mage::helper('dropship360')->getDatabaseConnection()->getConnection ( 'core_write' );
	}
	
	/*
	 * initialize variable used during file execution
	 */
	public function initVar($vendorCode){
		$this->_bufferStock = Mage::getStoreConfig('logicbroker_sourcing/inventory/buffer');
		$this->_vendorCode = $vendorCode;
		$ranking = Mage::getModel('dropship360/ranking')->load($vendorCode,'lb_vendor_code');
		$this->_linkAttribute = $ranking->getLinkingAttribute();
		$this->_vendorName = $ranking->getLbVendorName();
		$this->_updatedBy = 'FTP';
		return $this;
	}
	
	/*
	 * validate FTP file header
	 */
	public function validateCsvHeader($colNamesFtp){
		$headerValidation = true;
		$result = array_diff($colNamesFtp,$this->ftpCSVFormatbacward);
		if(count($result) > 0 ){
			if(!in_array('vendor_code',$result))
				$headerValidation = false;
		}
		return $headerValidation;
	}
	
	/*
	 * connect to FTP site
	 */
	public function connect(){
		$this->_ftp->connectftp();
	}
	/*
	 * send email in following cases
	 * 1) FTP connection failure
	 * 2) bad file header
	 * 3) invalid row data
	 */
	public function sendMail($mailData = array()){
		if (!Mage::getStoreConfigFlag (self::XML_PATH_INVENTORY_NOTIFICATION_EMAIL_ENABLED)) {
			return $this;
		}
		$mailData['datetime'] = Mage::getModel('core/date')->date();
		$postObject = new Varien_Object();
		$postObject->setData($mailData);
		$email = trim(Mage::getStoreConfig(self::XML_PATH_INVENTORY_NOTIFICATION_EMAIL));
		$templateId = 'logicbroker_ftp_con_fail';
		$isMailSent = Mage::helper('dropship360')->sendMail($postObject,$email,$templateId);
		if(!$isMailSent)
			Mage::log('Notification email not sent :'.$email, null, 'logicbroker_debug.log');
	}
	/*
	 * check FTP inventory upload enable from DS360 configuration page
	 */
	public function isFtpUploadEnable() {
		return Mage::getStoreConfigFlag ( self::XML_PATH_UPLOAD_ENABLED ); 
	}
	
	/*
	 * Inventory file process start
	 */
	public function startImport($file,$vendorCode){
		$this->_csvParserObj = Mage::getModel('dropship360/csvparser');
		//$changedCsvData = array();
		unset($this->_rowDataFtp[0]);
		//$this->checkFtpHeader($this->_colNamesFtp);
		$csvData = $this->_rowDataFtp;
		$sliceCsv = array_chunk($csvData, $this->_chunkSize,true);
		foreach($sliceCsv as $slicedData){
			/* 	@var $changedCsvData
			 * 	filter CSV row as we need to process only those records
			*	which are present in our inventory table
			*/
			$changedCsvData = $this->_csvParserObj->getChangedValue($slicedData,$vendorCode,$this->_colNamesFtp);
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
		}
		$logHistory = $this->_historyLog;
		$errorId = $this->saveLogHistory();
		/*
		 * send email if their is invalid row data 
		 */
		if($this->_errorsCount > 0){
			$reportFile =  Mage::helper ( 'dropship360' )->generateErrorList ( array (
					'failure' => count($this->_errorsCount),
					'lb_vendor_code' => $vendorCode,
					'error_id' => $errorId,
					'ftp_error_desc'=>null
			), true ); 
			if(!$reportFile['error'])
				$this->_ftp->uploadReport($reportFile,$vendorCode);
			$serverPath = explode('logicbrokervendorproduct',$file);
			$this->sendMail ( array (
					'isfailed' => true,
					'vendor_code' => $vendorCode,
					'subject' => 'dropship360 failed to update inventory',
					'message' => 'Missing/Bad data, check CSV data at following FTP path <br>' . $serverPath [1] 
			) );
		}
		return;
	}
	/*
	 * download all FTP files for each vendor
	 * @return files array 
	 * array[vendor_code] = array(file_path) 
	 */
	public function downloadFiles($localpath){
		$files = array();
		$vendorRanks = $this->getVendorRankCollection();
		if(!empty($vendorRanks)){
			$files = $this->_ftp->downloadFiles($localpath,$vendorRanks);
		}
		return $files;
	}
	/*
	 * @return active vendors and inventory folder path 
	 */
	protected function getVendorRankCollection() {
		$rankCollection = Mage::getModel('dropship360/ranking')->getCollection()
						->addFieldToFilter('is_dropship','yes');
		$vendorRank = array();
		$accountNumber =  $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_ACCNUMBER);
		if($rankCollection->getSize() > 0){
			foreach($rankCollection as $vendor){
				$path = $accountNumber.'_'.$vendor->getLbVendorCode().DS.'Inventory';
				$vendorRank[$vendor->getLbVendorCode()] = $path;
			}
		}
		return $vendorRank;
	}
	/*
	 * @var $this->_colNamesFtp : CSV header
	 * @var $this->_rowDataFtp : CSV rows  
	 */
	public function initFtpCsvData($file){
		$csvObject = new Varien_File_Csv ();
		$csvData = $csvObject->getData ( $file );
		if($csvData)
			$this->_colNamesFtp = $csvData [0];
		$this->_rowDataFtp = $csvData;
		return $this;
	}
	/*
	 * validate csv file before start import process
	 */
	public function validateFtpCsv($file) {
		$isFailed = false;
		
		//check for empty csv file
		if(count($this->_rowDataFtp) <= 1){
			$this->saveFtpHistoryError('','empty_file','Bad File');
			$isFailed = true;
		}
		// checks columns
		elseif (!$this->validateCsvHeader ( $this->_colNamesFtp ) || empty ( $this->_colNamesFtp ) ) {
			$serverPath = explode('logicbrokervendorproduct',$file);
			$valueArray = array (
					'file_name' => str_replace(DS,'#',$serverPath[1]),
					'magento_sku' => '',
					'vendor_sku' => '',
					'cost' => '',
					'qty' => '',
			);
			$this->saveFtpHistoryError($valueArray,'ftp_bad_header','Bad File header');
			$this->sendMail(array('isfailed'=>true,'vendor_code'=>$this->_vendorCode,'subject'=>'dropship360 failed to update inventory','message' => 'Bad File header,Check header format at following FTP path '.$serverPath[1]));
			Mage::log('Please check header format for file :'.$serverPath[1], null, 'logicbroker_ftp_vendor_inventory_import.log');
			$isFailed = true;
		}
		
		return $isFailed;
	}
	/*
	 * before process next FTP file reset global variable  
	 */
	public function resetVar() {
		$this->_errorsCount = 0;
		$this->_successCount = 0;
		$this->_colNamesFtp = array();
		$this->_rowDataFtp = array();
		$this->_historyErrorType = 'Missing/Bad Data';
		
	}
	/*
	 * set and save History log forcefully and 
	 */
	public function saveFtpHistoryError($message,$msgtype,$historyType){
		$this->_updatedBy = 'FTP';
		$this->setLogError('history',$message,$msgtype);
		$this->setHistoryErrorType($historyType);
		$this->saveLogHistory();
		$this->resetVar();
		return;
	}
	public function initialize(){
		Mage::helper('dropship360')->startProcess('manual_upload');
		Mage::log('Ftp upload started', null, 'logicbroker_ftp_vendor_inventory_import.log');
	}
	
	public function finalize(){
		Mage::helper('dropship360')->finishProcess('manual_upload');
		Mage::log('Ftp upload finished', null, 'logicbroker_ftp_vendor_inventory_import.log');
	}
	
}