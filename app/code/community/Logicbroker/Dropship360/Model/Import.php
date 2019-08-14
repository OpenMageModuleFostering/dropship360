<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 * Import vendor inventory kick off from this file and on the basis of upload type 
 * objects are created.
 * following oeprations are prefromed :
 * 1. file and header validation
 * 2. retrive and remove CSV file from local file system after completed import
 * 3. Support all three upload type  
 */
class Logicbroker_Dropship360_Model_Import extends Mage_Core_Model_Abstract {
	const FIELD_NAME_SOURCE_FILE = 'import_file';
	protected $_colNames;
	protected $_rowData;
	protected $_importType;
	protected $_importObject;
	protected $_fileObj;
	protected function _construct() {
		//$this->_init ( "dropship360/uploadvendor" );
		$this->_fileObj = $this->fileObj();
	}
	
	// initialize variables
	public function initCsvData($file) {
		$csvObject = new Varien_File_Csv ();
		$csvData = $csvObject->getData ( $file );
		if($csvData)
			$this->_colNames = $csvData [0];
		$this->_rowData = $csvData;
	}
	/* prepare downloadable sample CSV file for user */
	public function getCsvFile($isProductSetupMode = false)
	{
		$io = new Varien_Io_File();
		$path = Mage::getBaseDir('var') . DS . 'export' . DS;
		$name = md5(microtime());
		$file = $path . DS . $name;
		$io->setAllowCreateFolders(true);
		$io->open(array('path' => $path));
		$io->streamOpen($file, 'w+');
		$io->streamLock(true);
		($isProductSetupMode) ?  $io->streamWriteCsv(array('magento_sku','vendor_sku')) : $io->streamWriteCsv(array('vendor_sku','qty','cost'));
		$io->streamUnlock();
		$io->streamClose();
		return array(
				'type'  => 'filename',
				'value' => $file,
				'rm'    => true // can delete file after use
		);
	}
	public function getDatabaseConnection() {
		return Mage::getSingleton ( 'core/resource' )->getConnection ( 'core_write' );
	}
	protected function _getSession() {
		return Mage::getSingleton ( 'adminhtml/session' );
	}
	public function insertCronEntry($filename, $data = null) {
		$uploadFile = Mage::getModel('dropship360/uploadvendor');
		$uploadFile->setFileName ( $filename );
		$uploadFile->setUpdatedBy ( 'manually' );
		$uploadFile->setUpdatedAt ( now () );
		$uploadFile->setLbVendorCode ( Mage::app ()->getRequest ()->getPost ( 'vendor' ) );
		try {
			$uploadFile->save ();
		} catch ( Exception $e ) {
			$this->_getSession ()->addError ( Mage::helper ( 'dropship360' )->__ ( $e->getMessage () ) );
		}
	}
	/**
	 * Import working directory
	 *
	 * @return string
	 */
	public static function getWorkingDir() {
		return Mage::getBaseDir ( 'var' ) . DS . 'logicbrokervendorproduct' . DS;
	}
	protected function setImportType() {
		$type = ($this->getProductsetupmode ()) ? 'productsetup' : 'manualimport';
		$this->_importType = $type;
		return $this;
	}
	public function getImportType() {
		return $this->_importType;
	}
	public function uploadSource() {
		$error = false;
		$vendorCode = $this->getVendor ();
		$this->setImportType ();
		$entity = 'vendor_product_' . date ( 'ymdHis' );
		$uploader = Mage::getModel ( 'core/file_uploader', self::FIELD_NAME_SOURCE_FILE );
		$uploader->skipDbProcessing ( true );
		$result = $uploader->save ( self::getWorkingDir () );
		$extension = pathinfo ( $result ['file'], PATHINFO_EXTENSION );
		$uploadedFile = $result ['path'] . $result ['file'];
		$this->initCsvData ( $uploadedFile );
		$error = $this->validateCsv ();
		if ($error) {
			$this->_fileObj->rm ( $uploadedFile );
			$this->_getSession ()->addNotice ( Mage::helper ( 'dropship360' )->__ ( 'Please fix errors and re-upload file' ) );
			return $error;
		}
		
		$sourceFile = self::getWorkingDir () . $entity;
		
		$sourceFile .= '.' . strtolower ( $extension );
		$fileName = $entity . '.' . strtolower ( $extension );
		
		if (strtolower ( $uploadedFile ) != strtolower ( $sourceFile )) {
			if ($this->_fileObj->fileExists( $sourceFile )) {
				$this->_fileObj->rm ( $sourceFile );
			}
			
			if (! @rename($uploadedFile,$sourceFile )) {
				Mage::throwException ( Mage::helper ( 'importexport' )->__ ( 'Source file moving failed' ) );
			}
		}
		Mage::register ( 'file_name', $fileName );
		if (! $error)
			$this->insertCronEntry ( $fileName );
		
		return $error;
	}
	public function validateCsv() {
		$isFailed = false;
		// create object as per requested import type
		$this->_importObject = Mage::getModel ( 'dropship360/import_' . $this->getImportType () );
		
		//check for empty csv file
		if(count($this->_rowData) <= 1){
			$this->_getSession ()->addError ( Mage::helper ( 'dropship360' )->__ ( 'CSV file is empty ') );
			$isFailed = true;
		}
		// checks columns
		elseif (!$this->_importObject->validateCsvHeader ( $this->_colNames ) || empty ( $this->_colNames ) ) {
			$this->_getSession ()->addError ( Mage::helper ( 'dropship360' )->__ ( 'CSV header %s is invalid ', implode ( ',', $this->_colNames ) ) );
			$isFailed = true;
		}
		return $isFailed;
	}
	
	/**
	 * Get Varien I/O File class object
	 * 
	 * @return object
	 */
	protected function fileObj() {
		$fileObj = new Varien_Io_File ();
		return $fileObj;
	}
	
	public function parseCsv($fileName = null,$lb_vendor_code = null)
	{
		$uploadFile = Mage::getModel('dropship360/uploadvendor');
		if(empty($fileName) || empty($lb_vendor_code)){
			$this->_getSession()->addError($this->__('Required parameters are missing CSV file or Vendor-Code'));
			return;
		}
		unset($this->_rowData[0]);
		Mage::helper('dropship360')->turnOnReadUncommittedMode(); // dirty read patch
		$this->_importObject->startImport($this->_rowData,$lb_vendor_code);
		Mage::helper('dropship360')->turnOnReadCommittedMode(); //restore to orignal transection level
		try{
			$updateFileStatus = $uploadFile->unsetData()->load($fileName,'file_name');
			$updateFileStatus->setImportStatus('done');
			$updateFileStatus->save();
		}catch(Exception $e){
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
			Mage::log($e->getMessage(), Zend_Log::ERR);
		}
		$file = self::getWorkingDir() . $fileName;
		$this->fileObj()->rm($file);
		return $this;
	}
	
	/*
	 *
	* Ftp function call by observer from where program execution started
	*
	*/
	public function ftpParseCsv(){
		if(Mage::helper('dropship360')->isProcessRunning('bulk_assign')){
			$message = 'Bulk product setup is currently running hence cannot run ftp import';
			Mage::log($message, null, 'logicbroker_log_report.log');
			return;
		}
		$ftpfileName = array();
		$vendorFiles = array();
		$ftpObj = Mage::getModel('dropship360/import_ftpimport');
		if (!$ftpObj->isFtpUploadEnable()) {
			return $this;
		}
		try{
			$ftpObj->connect(); // connect to FTP site
			$vendorFiles = $ftpObj->downloadFiles(self::getWorkingDir()); // download and archive all FTP files 
		}catch (Exception $e){
			$ftpObj->sendMail(array('subject'=>'Your magento site has failed to connect FTP site','message' =>$e->getMessage(),'bcc'=>trim(Mage::helper('dropship360')->getConfigObject('apiconfig/email/bcc'))));
			Mage::log($e->getMessage(), null, 'logicbroker_ftp_vendor_inventory_import.log');
			$valueArray = array (
					'con_error'=>$e->getMessage(),
					'magento_sku' => '',
					'vendor_sku' => '',
					'cost' => '',
					'qty' => '',
			);
			$ftpObj->saveFtpHistoryError($valueArray,'connection_error','connection-error');
		}
		/*
		 * vendors files on FTP server return empty 
		 * 1) vendor inventory folder empty
		 * 2) supplier vendor not set at supplier management screen 
		 */
		if(empty($vendorFiles)){
			Mage::log('Files not found on FTP server Or Supplier vendor not set', null, 'logicbroker_ftp_vendor_inventory_import.log');
			return $this;
		}
		
		/*
		 * start process files as per vendor.
		 */
		$ftpObj->initialize();
		Mage::helper('dropship360')->turnOnReadUncommittedMode(); // dirty read patch
		foreach($vendorFiles as $vendor=>$files){
			$ftpObj->initVar($vendor);
			foreach ($files as $filepath) {
				try{
			$ftpObj->initFtpCsvData($filepath);
			$isInvalid = $ftpObj->validateFtpCsv($filepath);
			if($isInvalid){
				continue; // skip file for invalid header and empty file
			}
			$ftpObj->startImport($filepath,$vendor);
			$ftpObj->resetVar();
			$this->_fileObj->rm($filepath);
			}catch (Exception $e){
				$ftpObj->sendMail ( array (
							'subject' => 'Exception occurred during FTP inventory import for supplier '.$vendor,
							'message' => $filepath.'<br>'.$e->getMessage (),
							'bcc' => trim ( Mage::helper ( 'dropship360' )->getConfigObject ( 'apiconfig/email/bcc' ) ) 
					) );
				Mage::log('FTP-'.$e->getMessage().'Description ##'.$vendor.' ## '.$filepath, null, 'logicbroker_ftp_vendor_inventory_import.log');
				$ftpObj->resetVar();
				continue;
			}
			}
		}
		Mage::helper('dropship360')->turnOnReadCommittedMode(); //restore to orignal transection level
		$ftpObj->finalize();
	}
}