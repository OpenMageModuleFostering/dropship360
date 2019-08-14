<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Resource_Vendorimportlog 
{
	protected $_tableVendorImportLog ;
	protected $conn;
	
	public function getDatabaseConnection() 
	{
		return Mage::getSingleton ( 'core/resource' )->getConnection ( 'core_write' );
	}
    
	public function insertLog($lb_vendor_code = null,$updated_by = null,$success = 0,$failure = 0,$ftp_error =null,$ftp_error_desc = null)
	{
	$this->_tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/vendor_import_log' );
	 $this->conn = $this->getDatabaseConnection();	
     $this->conn->beginTransaction ();
	 $created_at = now();
     $insert = 'INSERT INTO '.$this->_tableVendorImportLog.'(lb_vendor_code,updated_by,success,failure,ftp_error,ftp_error_desc,created_at) VALUES ("'.$lb_vendor_code.'","'.$updated_by.'",'.$success.','.$failure.',"'.$ftp_error.'","'.$ftp_error_desc.'","'.$created_at.'")';
     $this->conn->query($insert);
		try {
				$this->conn->commit ();
			} catch ( Exception $e ) {
				$this->conn->rollBack ();
				Mage::log($e->getMessage(), Zend_Log::ERR);
				Mage::logException($e);
				echo $e->getMessage();
			
			}
   }
}