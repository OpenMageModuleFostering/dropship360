<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 * used by Ftp inventory upload for FTP opreations i.e.
 * 1. archive file
 * 2. upload file
 * 3. connect ftp
 * 4. downloadfiles
 */

class Logicbroker_Dropship360_Model_Import_Ftp extends Mage_System_Ftp
{
	protected $_ftp;
	protected $_fileObj;
	protected $_urlString;
	const XML_PATH_UPLOAD_ENABLED          = 'logicbroker_sourcing/cron_settings_upload/enabled';
	const XML_PATH_UPLOAD_FTP_SITE   = 'logicbroker_sourcing/cron_settings_upload/ftp_site';
	const XML_PATH_UPLOAD_FTP_USERNAME   = 'logicbroker_sourcing/cron_settings_upload/ftp_username';
	const XML_PATH_UPLOAD_FTP_PASSWORD   = 'logicbroker_sourcing/cron_settings_upload/ftp_password';
	const XML_PATH_UPLOAD_FTP_TYPE   = 'logicbroker_sourcing/cron_settings_upload/ftp_type';
	const XML_PATH_UPLOAD_FTP_ACCNUMBER  = 'logicbroker_sourcing/cron_settings_upload/ftp_accnumber';
	
	
	public function __construct(){
		$this->_fileObj = new Varien_Io_File();
		$this->_fileObj->setAllowCreateFolders(true);
	}
	protected function getConfigValue($path)
	{
		return Mage::getStoreConfig($path);
	}
	
	public function connectftp(){
		$urlString = $this->getUrlString();
		$this->connect($urlString);
	}
	
	/*
	 * Connection string: ftp://user:pass@server:port/path
     * user,pass,port,path are optional parts
	 */
	protected function getUrlString() {
		if(!$this->_urlString){
			$ftp_site = $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_SITE);
			$ftp_username = $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_USERNAME);
			$ftp_password = Mage::helper('core')->decrypt($this->getConfigValue(self::XML_PATH_UPLOAD_FTP_PASSWORD));
			$ftp_type = $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_TYPE);
			$this->_urlString = 'ftp://'.$ftp_username.':'.$ftp_password.'@'.$ftp_site;
		}
		return $this->_urlString;
	}
	
	public function downloadFiles($localpath,$vendorRanks){
		
		$fileinfo = array();
		foreach($vendorRanks as $vendorCode=>$serverpath){
			$localPath = $this->getFiles($vendorCode,$serverpath,$localpath);
			if(!empty($localPath)){
				$fileinfo[$vendorCode] = $localPath;
			//$this->archiveFiles($vendor);
			}
		}
		return $fileinfo;
	}
	protected function getFiles($vendorCode,$serverpath,$localpath) {
		 $this->createArchiveAndReportFolder($serverpath);
		 $files = $this->ls($serverpath);
		 $localfilepath = array();
		 $filterFiles = array_filter($files,array($this,'filterFiles'));
		 if($filterFiles){
		 $this->_fileObj->createDestinationDir($localpath.$serverpath);
		 foreach($filterFiles as $fileDetail){
		 	if($this->download($serverpath.DS.$fileDetail['name'], $localpath.$serverpath.DS.$fileDetail['name'])){
		 		$this->archiveFile($serverpath, $localpath, $fileDetail['name']);
		 		$dateString = $fileDetail['rawdata'][5].$fileDetail['rawdata'][6].$fileDetail['rawdata'][7];
		 		$dateString = strtotime($dateString);
		 		if(array_key_exists($dateString,$localfilepath))
		 			$dateString = rand(100,200) + $dateString;
		 		$localfilepath[$dateString] = $localpath.$serverpath.DS.$fileDetail['name'];
		 	}else{
		 		Mage::log('Downloading failed for file : '.$serverpath.DS.$fileDetail['name'], null, 'logicbroker_ftp_vendor_inventory_import.log');
		 	}
		}
		 ksort($localfilepath);
		 }
		return  array_values($localfilepath);
	}
	protected function filterFiles($files) {
		return !$files['dir'];
	}
	protected function createArchiveAndReportFolder($serverpath){
		$this->chdir($this->correctFilePath($serverpath));
		$this->mdkir('Archive');
		$this->mdkir('Reports');
		$this->chdir('/');
	}
	protected function archiveFile($serverpath,$localpath,$filename) {
		$path = $this->correctFilePath($serverpath.DS.'Archive'.DS.date("Y-m-d_H_i_s_").$filename);
		if($this->upload($path,$localpath.$serverpath.DS.$filename)){
			if($this->delete($serverpath.DS.$filename))
				Mage::log('File deleted successfully  : '.$serverpath.DS.$filename, null, 'logicbroker_ftp_vendor_inventory_import.log');
		}else{
			Mage::log('Failed to move file in archive folder  : '.$path, null, 'logicbroker_ftp_vendor_inventory_import.log');
		}
		
	}
	
	public function uploadReport($file,$vendorCode) {
		$this->connectftp();
		$localPath = $file['value'];
		$accountNumber = $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_ACCNUMBER);
		$serverPath = $accountNumber.'_'.$vendorCode.DS.'Inventory'.DS.'Reports';
		$filename = 'report'.Mage::getModel('core/date')->date('Ymd-his').'.csv';
		$correctServerPath = $this->correctFilePath($serverPath.DS.$filename);
		if($this->upload($correctServerPath,$localPath)){
			if($this->_fileObj->rm($file['value']))
				Mage::log('Report file deleted successfully  : '.$localPath, null, 'logicbroker_ftp_vendor_inventory_import.log');
		}else{
			Mage::log('Failed to move file in reports folder  : '.$correctServerPath, null, 'logicbroker_ftp_vendor_inventory_import.log');
		}
		
	}
}