<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Uploadvendor extends Mage_Core_Model_Abstract
{
	
	const FIELD_NAME_SOURCE_FILE = 'import_file';
	protected $_isProductSetupMode = false;
	protected $sendBadFileAlert = false;
	protected $_haderError = array();
	protected $_FtpErrors = array();
	protected $_UploadCsvErrors = array();
	protected $_inventoryModel; 
    protected $_vendorSkuFlag = array();
	protected $conn;
	protected $ftpRequestPram = array();
	protected $_errors = array();
	const XML_PATH_UPLOAD_ENABLED          = 'logicbroker_sourcing/cron_settings_upload/enabled';
	const XML_PATH_UPLOAD_FTP_SITE   = 'logicbroker_sourcing/cron_settings_upload/ftp_site';
	const XML_PATH_UPLOAD_FTP_USERNAME   = 'logicbroker_sourcing/cron_settings_upload/ftp_username';
	const XML_PATH_UPLOAD_FTP_PASSWORD   = 'logicbroker_sourcing/cron_settings_upload/ftp_password';
	const XML_PATH_UPLOAD_FTP_TYPE   = 'logicbroker_sourcing/cron_settings_upload/ftp_type';
	const XML_PATH_UPLOAD_FTP_ACCNUMBER  = 'logicbroker_sourcing/cron_settings_upload/ftp_accnumber';
	const XML_PATH_INVENTORY_NOTIFICATION_EMAIL  = 'logicbroker_sourcing/inventory_notification/email';
	const XML_PATH_INVENTORY_NOTIFICATION_EMAIL_ENABLED  = 'logicbroker_sourcing/inventory_notification/enabled';
	protected $ftpCSVFormat =  array('vendor_code','vendor_sku','qty','cost');
	protected $manualCSVFormat = array('vendor_sku','qty','cost');
	protected $productSetupCSVFormat = array('magento_sku','vendor_sku','','');
	protected $_csvDataCache;
	protected $_vendorCode;
	protected $_csvParserObj;
	protected $emptyRecords = array(); //checkDataIntigrity fnction store empty records from CSV
	protected $result = array(); //checkDataIntigrity fnction store final result for error
	protected $supplierName = '';
	
	protected function _construct()
	{
		$this->_inventoryModel = Mage::getModel('dropship360/inventory');
		$this->conn = $this->getDatabaseConnection ();
		$this->_init("dropship360/uploadvendor");
		$this->_csvParserObj = Mage::getModel('dropship360/csvparser');
		
	}
	
	public function getDatabaseConnection() 
	{
		return Mage::getSingleton ( 'core/resource' )->getConnection ( 'core_write' );
	}
	
	protected function _getSession()
	{
		return Mage::getSingleton('adminhtml/session');
	}
	
	protected function _getCsvData($fileName,$header = false)
	{
		$csvObject  = new Varien_File_Csv();
		
		if(!$this->_csvDataCache){
			$this->_csvDataCache = $this->_csvParserObj->getChangedValue($csvObject->getData($fileName),$this->_vendorCode);
		}else
		{
			if($header){
				return array($this->_csvDataCache[0]); 
			}else
			{
				 $this->_csvDataCache;
			}
		}
		return $this->_csvDataCache;
	}
	
	
	
	/**
	 * Import working directory 
	 *
	 * @return string
	 */
	
	public static function getWorkingDir()
	{
		return Mage::getBaseDir('var') . DS . 'logicbrokervendorproduct' . DS;
	}
 	
	public function insertCronEntry($filename,$data = null){
		
		$this->setFileName($filename);
		$this->setUpdatedBy('manually');
		$this->setUpdatedAt(now());
		$this->setLbVendorCode(Mage::app()->getRequest()->getPost('vendor'));
		try{
			$this->save();
		}catch(Exception $e){
			$this->_getSession()->addError(Mage::helper('dropship360')->__($e->getMessage()));
		}	
	}
	
 	public function uploadSource()
    {
    	$error = false;   	
    	$this->_vendorCode = $this->getVendor();  	
    	$this->_isProductSetupMode = $this->getProductsetupmode();   	
    	$entity = 'vendor_product_'.date('ymdHis');
    	$uploader  = Mage::getModel('core/file_uploader', self::FIELD_NAME_SOURCE_FILE);
        $uploader->skipDbProcessing(true);
        $result    = $uploader->save(self::getWorkingDir());
        $extension = pathinfo($result['file'], PATHINFO_EXTENSION);
        $uploadedFile = $result['path'] . $result['file'];
        $error = $this->validateCsv($uploadedFile);
        if($error){
        	$this->fileObj()->rm($uploadedFile);
        	$this->_getSession()->addNotice(Mage::helper('dropship360')->__('Please fix errors and re-upload file'));
        	return $error;
        }
        
        $sourceFile = self::getWorkingDir() . $entity;

        $sourceFile .= '.' . strtolower($extension);
        $fileName = $entity.'.'.strtolower($extension);

        if(strtolower($uploadedFile) != strtolower($sourceFile)) {
            if (file_exists($sourceFile)) {
				$this->fileObj()->rm($sourceFile);
            }

            if (!@rename($uploadedFile, $sourceFile)) {
                Mage::throwException(Mage::helper('importexport')->__('Source file moving failed'));
            }
        }
        Mage::register('file_name',$fileName);
        if(!$error)
        $this->insertCronEntry($fileName);
        
        return $error;
    }
    
   
    public function validateCsv($fileName)
    {
    	//$fileName  
    	$isError = false;
    	$csvData = $this->_getCsvData($fileName,true);
    	
    	/** checks columns */
    	
    	if($this->validateCsvHeader($csvData)){
    		$isError = false;
    	}else{    		
    		$this->_getSession()->addError(Mage::helper('dropship360')->__('CSV header %s is invalid ',implode(',',$this->_haderError)));
    		$isError = true;
    		$this->_csvDataCache = array();
    	}
    	return $isError; 
    }
    
    protected function validateCsvHeader($csvData,$isFtp = false)
    {    	
    	$result = true;
    	if(empty($csvData))
    	{
    		return false;
    	}
    	if	($this->_isProductSetupMode && !$isFtp ){
    	$csvFields  = $this->productSetupCSVFormat;
    	}else{
    		$csvFields  = (!$isFtp) ?  $this->manualCSVFormat : $this->ftpCSVFormat;
    	}
    	$cvsDataNum   = count($csvData[0]);
    		
    	if(!$this->validateManualCsvHeader($cvsDataNum,$csvData,$isFtp))
				return false;
    	if(!$this->validateFtpCsvHeader($cvsDataNum,$csvData))
				return false;
    	if(!$this->validateProductSetupCsvHeader($cvsDataNum,$csvData))
				return false;
    	for ($i = 0; $i < $cvsDataNum; $i++) { 		 
    		if( $isFtp && ($csvData[0][0] == 'vendor_code' || $csvData[0][0] == 'vendor_sku')){
    			continue;
    		}
    		if($csvData[0][$i] == $csvFields[$i]){
    			continue;
    		}else{
    			(!$isFtp) ? $this->_haderError[] = $csvData[0][$i] : '';
    			$result = false;
    		}
    	}
    	return $result;
    }
    
    protected function validateManualCsvHeader($cvsDataNum,$csvData,$isFtp)
    {
    	if(!$isFtp && !$this->_isProductSetupMode ){
    		if($cvsDataNum != 3){
    			foreach ($csvData[0] as $val){
    				if(!in_array($val,array('vendor_sku','cost','qty')))
    				{
    					$this->_haderError[] = $val;
    				}
    			}
    			return false;
    		}
    	}
    	return true;
    }
    protected function validateFtpCsvHeader($cvsDataNum,$csvData)
    {
    	$validation = true;
    	if($cvsDataNum == 3 || $cvsDataNum == 4){
    		foreach ($csvData[0] as $val){
    			if(!in_array($val,($cvsDataNum == 3) ? array('vendor_sku','qty', 'cost') : array('vendor_code','vendor_sku','qty', 'cost'))){
    				$this->_haderError[] = $val;
    				$validation = false;
    			}
    		}
    		return $validation;
    	}
    	return true;
    }
    protected function validateProductSetupCsvHeader($cvsDataNum,$csvData)
    {
    	if($this->_isProductSetupMode ){
    		if($cvsDataNum != 2){
    			foreach ($csvData[0] as $val){
    				if(!in_array($val,array('vendor_sku','magento_sku')))
    				{
    					$this->_haderError[] = $val;
    				}
    			}
    			return false;
    		}
    	}
    	return true;
    }
	protected function checkDataIntigrity($csvData,$isFtp = false){
		
    	//patch for FTP backward compatibility header
    	(count($csvData[0]) <= 3) ? array_unshift($csvData[0], "") : $csvData[0];
    	foreach($csvData as $row => $csvRowData)
    	{
    		if($row == 0)
    			continue;
			if(!$this->_isProductSetupMode && !$isFtp){
				array_unshift($csvRowData, "");
			}
			//patch for FTP backward compatibility data
			if($isFtp)
			(count($csvRowData) <= 3) ? array_unshift($csvRowData, "") : $csvRowData;
			$this->getErrorRowNumber($csvRowData,$row);
    	}
    	$this->generateMsg($isFtp,$this->result,$this->emptyRecords);
    	return in_array(true,$this->result) ? true : false;
    }
    protected function getErrorRowNumber($csvRowData,$row)
    {
    	
    		foreach($csvRowData as $key => $data){
				$data = trim($data);
				switch($key){
					case is_numeric($key) ? 0 : 'magento_sku' :
						if($this->_isProductSetupMode){
							if(empty($data)){
								$this->emptyRecords['magento_sku'][] = $row;
								$this->result[] = true;
							} else {
    						$this->result[] = false;
							}
						}else{
							continue;
						}	
						break;
					case is_numeric($key) ? 1 : 'vendor_sku':
						if(empty($data)){
							$this->emptyRecords['vendor_sku'][] = $row;
							$this->result[] = true;
						} 
						else 
    					$this->result[] = false;
						break;
					case is_numeric($key) ? 2 : 'qty':
						if(!is_numeric($data)  || $data < 0 ) 
						{
							if($data!=""){
								$this->emptyRecords['qty'][] = $row;
								$this->result[] = true;
							}
						}
						else 
    					$this->result[] = false;
						break;
					case is_numeric($key) ? 3 : 'cost':
						if(!is_numeric($data) || $data < 0 ){
							if($data!=""){
								 $this->emptyRecords['cost'][] = $row;
								 $this->result[] = true;
							}
						}
						else
    					$this->result[] = false;
						break;
    		}
				}	
    	return;
    		}
    protected function generateMsg($isFtp,$result,$emptyRecords){
    	
				$error = in_array(true,$result) ? true : false;				
				if($error){
					foreach($emptyRecords as $key=>$value){			
						if($this->_isProductSetupMode){
							if($key == 'magento_sku'){
								$string = implode(';',$value);
								$this->_UploadCsvErrors[] = array('error_type'=>'row_magento_sku','value'=>$string);
							}
						}
						if($key == 'vendor_sku'){
							$string = implode(';',$value);
    					if($isFtp){ 
    							$this->_FtpErrors[] = array('error_type'=>'row_vendor_sku','value'=>$string);
    							$this->sendBadFileAlert = true;
    						}else{
    							$this->_UploadCsvErrors[] = array('error_type'=>'row_vendor_sku','value'=>$string);
						}
					}
						if($key == 'qty'){	
							$string = implode(';',$value);
    					if($isFtp){
    							$this->_FtpErrors[] =  array('error_type'=>'row_qty','value'=>$string);
    							$this->sendBadFileAlert = true;
    					}else{
    							$this->_UploadCsvErrors[] =  array('error_type'=>'row_qty','value'=>$string);
						}
					}
						if($key == 'cost'){
							$string = implode(';',$value);
    					if($isFtp){
    							$this->_FtpErrors[] = array('error_type'=>'row_cost','value'=>$string);
    							$this->sendBadFileAlert = true;
    					}else{
    							$this->_UploadCsvErrors[] = array('error_type'=>'row_cost','value'=>$string);
    					}
						}
						}
						}
    	return ;
    }
    
    protected function getConfigValue($path)
	{
    	return Mage::getStoreConfig($path);
    }
    public function getMagentoSku($vendorCode,$vendorSku){
    	$sku = '';
    	$vendorCollection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$vendorCode)->addFieldTofilter('lb_vendor_sku',$vendorSku);
    	if($vendorCollection->count() > 0)
    	{
    		$sku = $vendorCollection->getFirstItem()->getProductSku();
    	}
    	return $sku;
    }
 
    /* parse uploaded csv file  */
    public function parseCsv($fileName = null,$lb_vendor_code = null)
	{
    	$records = array();
    	$success = array();
    	$failure = array();   	
    	$counter = 0;
		$foramterroroutput = array();
		$this->_csvDataCache = array();
		$this->_vendorCode = $lb_vendor_code;
    	$tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/vendor_import_log' ); 	
    	$csvData = $this->_getCsvData(self::getWorkingDir().$fileName);
      
        if(count($csvData) <= 1 && Mage::getModel('dropship360/csvparser')->isCsvFileEmpty())
    	{
            $failure[$fileName] = 'Sorry,we cant find the record to update inventory'; 
            $this->_UploadCsvErrors[] =  array('error_type'=>'empty_file','value'=>'Sorry,we cant find the record to update inventory');
    	} 
    	
		$records = Mage::getModel('dropship360/csvparser')->generateManualCsvRow($csvData,$this->_isProductSetupMode,$lb_vendor_code);
    	
    	Mage::helper('dropship360')->turnOnReadUncommittedMode(); // dirty read patch
    	//$this->conn->beginTransaction ();
		if(is_array($records) && !empty($records)){
    	$requestData = array_chunk($records, 1, true);
    	
    	foreach($requestData as $dataArr){
    		foreach($dataArr as $data){	
				if($this->_isProductSetupMode){
					$result[] = $this->validateProductSetupMode($data);
				}else{
					$result[] = $this->validateCsvData($data);
				}	
			}
		}
		foreach($result as $successOrfail){
			if($successOrfail['success']!="")
			$success[] =  $successOrfail['success'];
			if($successOrfail['failure']!="")
			$failure[] = $successOrfail['failure'];
		}
    	 try{
	    	$updateFileStatus = Mage::getModel('dropship360/uploadvendor')->load($fileName,'file_name');
	    	$updateFileStatus->setImportStatus('done');
	    	$updateFileStatus->save();
	    }catch(Exception $e){
	    	echo $e->getMessage();
	    	$this->_errors[] = $e->getMessage();
            $this->_errors[] = $e->getTrace();
            Mage::log($e->getMessage(), Zend_Log::ERR);
            Mage::logException($e);
	    }	
	    $this->checkDataIntigrity($csvData);
		}
		$finalResultCounter = (!$this->_isProductSetupMode) ?  $this->logForUnprocessedRows($lb_vendor_code) : 0;
		
		if(is_array($finalResultCounter))
		{
			$failed = count($failure)+$finalResultCounter['failure'];
			$success = count($success)+$finalResultCounter['success'];
		}else
		{
			$failed = count($failure)+$finalResultCounter;
			$success = count($success)+$finalResultCounter;
		}
		
		$ftp_err = ($failed > 0)  ? 'Missing/Bad Data' : '';
		$insert = 'INSERT INTO '.$tableVendorImportLog.'(lb_vendor_code,updated_by,success,failure,ftp_error,created_at) VALUES ("'.$lb_vendor_code.'","'.Mage::getSingleton('admin/session')->getUser()->getUsername().'",'.$success.','.$failed.',"'.$ftp_err.'","'.now().'")';
	    $this->conn->beginTransaction ();
	    $this->conn->query($insert);
	    $entityId = $this->conn->lastInsertId($tableVendorImportLog);
	    try {
	    	$this->conn->commit ();
	    	$this->prepareInsertAndExeQuery($this->_UploadCsvErrors,$entityId);
	    	$file = self::getWorkingDir() . $fileName;
	    	$this->fileObj()->rm($file);
	    } catch ( Exception $e ) {
	    	$this->conn->rollBack ();
	    	$this->_errors[] = $e->getMessage();
            $this->_errors[] = $e->getTrace();
            Mage::log($e->getMessage(), Zend_Log::ERR);
            Mage::logException($e);
            echo $e->getMessage();
	    
	    }
	    $this->_csvParserObj->emptyTable();
	    $this->_csvDataCache = array();
        $this->_vendorSkuFlag = array();
        Mage::helper('dropship360')->turnOnReadCommittedMode(); //restore to orignal trasectional level
	    return $this;
    }

	
    protected function chekDuplicateCombination($data)
    {
        $result = true;
		$collection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$data['lb_vendor_code'])->addFieldTofilter('lb_vendor_sku',$data['vendor_sku']);
         if($collection->count() > 0){ 
			 $existing_product_sku = $collection->getFirstItem()->getProductSku();
			if(!empty($existing_product_sku)){                
				if($data['magento_sku'] != $existing_product_sku){
				   $result = false;  
				}
			}
		}
		$inventoryCollection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$data['lb_vendor_code'])->addFieldTofilter('product_sku',$data['magento_sku']);
		if($inventoryCollection->getSize() > 0){
		   $inventoryCollection = $inventoryCollection->getData(); 
		   $inventoryCollection = $inventoryCollection[0];
		   $existing_vendor_sku =  $inventoryCollection['lb_vendor_sku'];
		   if($existing_vendor_sku != trim($data['vendor_sku']))
		   {
			   $result = false; 
		   }              
		}            
		return $result;
    }
    
    protected function _prepareCollection()
    {
    	$collection = $this->getCollection()->addFieldToFilter('import_status','pending');
    	$collection->getSelect()->limit(1);
    	return $collection;
    }
    
    protected function calculateProductQty($data){
    	$qty = 0;
    	$configBuffer = 0;
    	if(!is_numeric($data['qty']) || $data['qty'] < 0){
    		return array('final_qty'=> .999999999,'upload_qty'=> .999999999); // apply patch for accept empty qty row from CSV
    	}
    	$buffer = Mage::getStoreConfig('logicbroker_sourcing/inventory/buffer');
    	$collection = $this->_inventoryModel->getCollection()->addFieldToFilter('product_sku',$data['magento_sku']);
    	if($collection->count() > 0){
    		foreach($collection as $qtyData){
    			if($data['lb_vendor_code'] != $qtyData->getLbVendorCode() )
    			$qty += $qtyData->getStock();
    		}
    	}
    	
    	if($data['qty'] <= 0){
    		$rQty = 0;
    	}else{
    		$rQty = $data['qty'];
    	}
	
    	if(!empty($buffer) && $buffer >= 0){
    		$configBuffer = $buffer;
    	}else{
    		$configBuffer = 0;
    	}
    	
    	$uploadQty = $rQty - $configBuffer;
    	$finalUploadQty = ($uploadQty >= 0) ? $uploadQty : 0;
    	$finalQty = $qty+$finalUploadQty;
    	return  array('final_qty'=> ($finalQty >= 0) ? $finalQty : 0,'upload_qty'=> $finalUploadQty);
    }
    protected function vendorProductInsert($data)
	{  	   	
    	$tableVendorInventory = Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/inventory' );
    	$inventoryCollectionResult = $this->getInventoryCollection($data);
    	$qtyArray = $this->calculateProductQty($data);
    	
    	switch($inventoryCollectionResult['operationType'])
    	{
    		case 'update':
    			$productId = Mage::getModel('catalog/product')->getIdBySku(trim($data['magento_sku']));
    			if($productId){
    				
    				$update = $this->_prepareUpdateQuery($data,$qtyArray,$inventoryCollectionResult,$tableVendorInventory,false);
    				
    			try {
    					if(!$this->updateProductInventory(trim($data['magento_sku']),$qtyArray['final_qty']))
    					{
    						$this->_UploadCsvErrors[] = array('error_type'=>'inventory_update_error','value'=>array('magento_sku'=>$data['magento_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
    						return false;
    					}
						if($update){
							$this->conn->beginTransaction ();
							$this->conn->query($update);
							$this->conn->commit ();
						}
    					return true;
    				} catch ( Exception $e ) {
    					$this->conn->rollBack ();
    					$this->_errors[] = $e->getMessage();
            			$this->_errors[] = $e->getTrace();
            			Mage::log($e->getMessage(), Zend_Log::ERR);
           				Mage::logException($e);
           				echo $e->getMessage();
    				}
					   
    			}else
    			{
    				$this->_UploadCsvErrors[] = array('error_type'=>'magento_sku_exists','value'=>array('magento_sku'=>$data['magento_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
    				return false;
    			}
    			break;
    			case 'productsetup' :
    				$productId = Mage::getModel('catalog/product')->getIdBySku($data['magento_sku']);
    				if($productId){
    					$qtyInsert = $qtyArray['upload_qty'];
    					$costInsert = $data['cost'];
    					$insert = 'INSERT INTO '.$tableVendorInventory.' (lb_vendor_code,lb_vendor_name,product_sku,lb_vendor_sku,stock,cost,created_at,updated_at) VALUES ("'.$data['lb_vendor_code'].'","'.$this->getVendorName($data['lb_vendor_code']).'","'.$data['magento_sku'].'","'.$data['vendor_sku'].'",'.$qtyInsert.','.$costInsert.',"'.now().'","'.now().'")';
    					try {
    						if(!$this->updateProductInventory($data['magento_sku'],$qtyArray['final_qty']))
    						{
    							$this->_UploadCsvErrors[] = array('error_type'=>'inventory_add_error','value'=>array('magento_sku'=>$data['magento_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
    							return false;
    						}
    						$this->conn->beginTransaction ();
    						$this->conn->query($insert);
    						$this->conn->commit ();
    						return true;
    					} catch ( Exception $e ) {
    						$this->conn->rollBack ();
    						$this->_errors[] = $e->getMessage();
    						$this->_errors[] = $e->getTrace();
    						Mage::log($e->getMessage(), Zend_Log::ERR);
    						Mage::logException($e);
    						echo $e->getMessage();
    					}
    				}
    				break; 
    		case 'addnotallowed':
    			
    			//$this->_UploadCsvErrors['general_error'][] = 'Add error: Cannot create new entry for vendor sku <b>' .$data['vendor_sku'].'</b>';
    				return false;
    			break;	
    	}
    	return true;
    	
    }
    
    protected function _prepareUpdateQuery($data,$qtyArray,$inventoryCollectionResult,$tableVendorInventory,$isFtp)
    {
    	$update;
    	(!is_numeric($data['cost']) || $data['cost'] < 0 || trim($data['cost']) =="") ? $costUpdate = '' : $costUpdate = 'cost ='. $data['cost'] . ',';
   		($qtyArray['upload_qty'] == .999999999 || trim($data['qty']) =="" ) ? $qtyUpdate = '' : $qtyUpdate = ' stock = '.$qtyArray['upload_qty']. ',';
	if(!$isFtp){
		($costUpdate=='' && $qtyUpdate =='' && !$this->_isProductSetupMode) ? $timeUpdate = ""  : $timeUpdate = ' updated_at = "'.now(). '",';
    	$vSkuUpdate = ' lb_vendor_sku = "'.$data['vendor_sku']. '"';
    	$update = 'update '.$tableVendorInventory.' set '.$costUpdate.$qtyUpdate.$timeUpdate.$vSkuUpdate.' where id = '.$inventoryCollectionResult['vendor_id'];
    }else
    {
    	if(trim($data['qty'])!='' || trim($data['cost']) !='')
    		$update = 'update '.$tableVendorInventory.' set '.$costUpdate. $qtyUpdate.' updated_at = "'.now().'" where id = '.$inventoryCollectionResult['vendor_id'];
    }
    return $update;
    }
    protected function getInventoryLogQuery($data,$type,$qty,$updateBy=null,$ignoreData)
    {
		if(count($ignoreData)>0){
			if($type=='update'){
				(in_array('qty', $ignoreData)) ? $type = 'Cost Updated, Qty Ignored' : '';
				(in_array('cost', $ignoreData)) ? $type = 'Qty Updated, Cost Ignored' : '';
			}else{
				(in_array('qty', $ignoreData)) ? $type = 'Cost Added, Qty Ignored' : '';
				(in_array('cost', $ignoreData)) ? $type = 'Qty Added, Cost Ignored' : '';
			}
			if(count($ignoreData)==2){
				$type = 'ignore';
			}			
		}
		if($qty==0.999999999)
		$qty = 0;
    	$vendorRankModel = Mage::getModel('dropship360/ranking')->load($data['lb_vendor_code'],'lb_vendor_code');
    	$vendorName = $vendorRankModel->getLbVendorName();
    	 
    	$tableName = Mage::getSingleton("core/resource")->getTableName('dropship360/inventorylog');
    	if(!$updateBy){
			$updateBy = Mage::getSingleton('admin/session')->getUser()->getUsername();
			}
		if(isset($data["pucost"]) && isset($data["puqty"])){
			$data['cost']= $data["pucost"];
			$qty = $data["puqty"];
		}
        if(isset($data["pucost"]) && isset($data["puqty"])){
			$data['cost']= $data["pucost"];
			$qty = $data["puqty"];
		}			
    	return 'INSERT INTO '.$tableName.' (lb_vendor_code,lb_vendor_name,product_sku,cost,stock,updated_by,activity,updated_at,created_at) VALUES ("'.$data['lb_vendor_code'].'","'.$vendorName.'","'.$data['magento_sku'].'","'.$data['cost'].'","'.$qty.'","'.$updateBy.'","'.$type.'","'.now().'","'.now().'")';
    }
    
    protected function getInventoryCollection($data,$isFtp = false,$log = false)
	{
		$spacesTrimmedSku = $this->getTrimmedSku();
		$searchedSku = array_search(trim($data['vendor_sku']), $spacesTrimmedSku);	
		$vendorCollection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$data['lb_vendor_code'])->addFieldTofilter('lb_vendor_sku',trim($data['vendor_sku']));
		if(isset($data['operationType'])){
			if($this->_isProductSetupMode && $data['operationType'] == 'update'){
				if($data['operationType'] == 'update')
				$vendorCollection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$data['lb_vendor_code'])->addFieldTofilter('product_sku',trim($data['magento_sku']));
			}
		}
		if($searchedSku){
			$vendorCollection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$data['lb_vendor_code'])->addFieldTofilter('lb_vendor_sku',$searchedSku);
		}
    	if($vendorCollection->getSize() > 0 || (isset($data['operationType']) && $data['operationType']=="update")){
    			$result = array('operationType'=> 'update','vendor_id'=>$vendorCollection->getFirstItem ()->getId(),'magento_sku'=>$vendorCollection->getFirstItem ()->getProductSku());
			$data['magento_sku'] = $vendorCollection->getFirstItem ()->getProductSku();
		}else{        
			if($log){
				$isDuplicate = false;
				if(in_array($data['vendor_sku'],$this->_vendorSkuFlag)){			   
					$isDuplicate = true;
				}
				else{           
				  $this->_vendorSkuFlag[] = $data['vendor_sku'];
				}				
				if($isDuplicate){
					$result = array('operationType'=> 'ignore','vendor_id'=>'');
				}else{
					$result = ($this->_isProductSetupMode) ? array('operationType'=> 'productsetup','vendor_id'=>'','magento_sku'=>$data['magento_sku']) :
				array('operationType'=> 'addnotallowed','vendor_id'=>'','magento_sku'=>null);	
				}
			}else{
				$result = ($this->_isProductSetupMode) ? array('operationType'=> 'productsetup','vendor_id'=>'','magento_sku'=>$data['magento_sku']) :
				array('operationType'=> 'addnotallowed','vendor_id'=>'','magento_sku'=>null);
			}
        }   	
    	return $result;
    }
    	 
    protected function updateProductInventory($sku,$qty)
	{
    	if($qty == .999999999){
    		return true;
    	}
    	$productId = Mage::getModel('catalog/product')->getIdBySku($sku);
    	if($productId){
    		$stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
    		if (!$stockItem->getId()) {
    			$stockItem->setData('product_id', $productId);
    			$stockItem->setData('stock_id', 1);
    		}
    	
    		if ($stockItem->getQty() != $qty) {
    			$stockItem->setData('qty', $qty);
    			$stockItem->setData('is_in_stock', $qty ? 1 : 0);
    			try {
    				$stockItem->save();
    				return true;
    			} catch (Exception $e) {
    				echo $e->getMessage();
    			}
    			 
    		}
    	}else{
			return false;
    	}
		return true;
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
    
    protected function getVendorName($vendorCode)
	{
    	return Mage::getModel('dropship360/ranking')->load($vendorCode,'lb_vendor_code')->getLbVendorName();
    }

    
	/* 
	 * 
	 * logic to Import CSV file from logicbroker FTP for vendor inventory cost
	 * 
	 * 
	 */
	public function testFtpConnection($request,$isFtp = false)
	{
    	$ftpServer =  $request['ftp_site'];
    	$ftpUserName = $request['ftp_username'];
    	$ftpPassword = $request['ftp_password'];
    	$ftpType = $request['ftp_type'];
    	try {		
    		if($ftpType['value'] == 'ftp'){
    			$ftpcon = ftp_connect($ftpServer['value']);
    		}else{
    			if(function_exists('ftp_ssl_connect'))
    				$ftpcon = ftp_ssl_connect($ftpServer['value']);
    			else
    				return  array('error'=>true,'message' => 'System does not support secure ftp');
    		}
    		if (false === $ftpcon) {
    
    			return  array('error'=>true,'message' => 'Unable to connect');
    		}
    		$loggedIn = @ftp_login($ftpcon,  $ftpUserName['value'],  $ftpPassword['value']);
    		ftp_pasv($ftpcon, true);	
    		if (false === $loggedIn) {
    			return array('error'=>true,'message' => 'Unable to log in');
    		}
			if(!$isFtp)
			ftp_close($ftpcon);
    	} catch (Exception $e) {
    		return  array('error'=>true,'message' => $e->getMessage());
    			
    	}
		if($isFtp)
    	return array('error'=>false,'message' => null,'object'=>$ftpcon);
		else
		return array('error'=>false,'message' => null);
    }
   
	 /* 
	  * 
	  * Ftp function call by observer from where program execution started
	  *
	*/         
    public function ftpParseCsv()
	{
    	if(Mage::helper('dropship360')->isProcessRunning('bulk_assign')){
    		$message = 'Bulk product setup is currently running hence cannot run ftp import';
    		Mage::log($message, null, 'logicbroker_log_report.log');
    		return;
    	}
    	$ftpfileName = array();
    	$this->ftpRequestPram = array('ftp_site'=>array('value'=> $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_SITE)),'ftp_username'=>array('value'=> $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_USERNAME)),'ftp_password'=>array('value'=> Mage::helper('core')->decrypt($this->getConfigValue(self::XML_PATH_UPLOAD_FTP_PASSWORD))),'ftp_type'=>array('value'=> $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_TYPE)));
		
    	if (! Mage::getStoreConfigFlag ( self::XML_PATH_UPLOAD_ENABLED )) {
    		return $this;
    	}
    	$connectionResult = $this->testFtpConnection($this->ftpRequestPram,true);  	 
    	if($connectionResult['error']){
    		$this->sendMail(array('subject'=>'Your magento site has failed to connect FTP site','message' => 'Connection Failure','bcc'=>trim(Mage::helper('dropship360')->getConfigObject('apiconfig/email/bcc'))));
    		$this->genrateLogEntry(array('ftp_error'=>'Connection error','ftp_error_desc'=>$connectionResult['message'],'error'=> 1));
    		Mage::log($connectionResult['message'], null, 'logicbroker_ftp_vendor_inventory_import.log');
    		ftp_close($connectionResult['object']);
    		return $this;
    	}
    	 
    	$rankCollection = Mage::getModel('dropship360/ranking')->getCollection()->addFieldToFilter('is_dropship','yes');
    	/* file path format <ftp site>/<Logicbroker Account Number>_MagVendID<number>/Inventory/ */
    	 
    	if($rankCollection->getSize() > 0){
    		foreach($rankCollection as $ranks){
    			$path = $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_ACCNUMBER).'_'.$ranks->getLbVendorCode().'/'.'Inventory';
    			$ftpFiles = array();
    			$ftpFilesList = ftp_nlist($connectionResult['object'],$path);
    			//patch for sort ftp files by time
    			if($ftpFilesList){
    			foreach ($ftpFilesList as $value) {
    				if(preg_match("/.csv$/i", $value, $match)){
    				$fileTime = ftp_mdtm($connectionResult['object'], $value);
    				if(array_key_exists($fileTime,$ftpFiles))
    					$ftpFiles[$fileTime+20] = $value; // if timestamp same for files
    				else
    					$ftpFiles[$fileTime] = $value;
    				}
    			}
    			ksort($ftpFiles); // sort associative arrays in acending order, according to the key(time)
    			}
    			if($ftpFiles){
    				foreach($ftpFiles as $file){
    					if($this->downloadFtpFile($connectionResult['object'],$file,$path, $ranks->getLbVendorCode()))
    						$ftpfileName[$ranks->getLbVendorCode()][] = self::getWorkingDir().str_replace("\\","/",$path).DS.$this->downloadFtpFile($connectionResult['object'],$file,$path);
    				}
    			}
    			ftp_chdir($connectionResult['object'],'/');
    		} 		 
    	}else{
    		$this->genrateLogEntry(array('ftp_error'=>'Import Error','ftp_error_desc'=>'No dropship supplier found','error'=> 1));
    		Mage::log('No dropship supplier found', null, 'logicbroker_ftp_vendor_inventory_import.log');
    		ftp_close($connectionResult['object']);
    		return $this;
    	}
    	if(!empty($ftpfileName)){
    		$this->initialize();
    		$this->_csvParserObj->emptyTable();
    		$this->_csvDataCache = array();
    		Mage::helper('dropship360')->turnOnReadUncommittedMode(); //dirty read patch
    		foreach($ftpfileName as $vendorCode=>$fileinfo)
    		{
    			$this->_vendorCode = $vendorCode;
    			foreach($fileinfo as $path){
    				if($this->validateCsvHeader($this->_getCsvData($path,true),true)){
    					Mage::helper('dropship360')->turnOnReadUncommittedMode(); // dirty read patch
						$this->ftpUpdateVendorProduct($this->_getCsvData($path),$path,$vendorCode);	
						Mage::helper('dropship360')->turnOnReadCommittedMode(); //restore to orignal trasectional level
						$this->_csvDataCache = array();//for more than one csv file on FTP server
    					
    				}else{
    					$this->_csvDataCache = array();
    					$logPath = explode('logicbrokervendorproduct',str_replace("\\","/",$path));
    					$this->sendMail(array('isfailed'=>true,'vendor_code'=>$vendorCode,'subject'=>'dropship360 failed to update inventory','message' => 'Bad File header,Check header format at following FTP path '.$logPath[1]));
    					$this->genrateLogEntry(array('lb_vendor_code'=>$vendorCode,'ftp_error'=>'Bad File header','ftp_error_desc'=>'Check header format at following FTP path '.$logPath[1],'error'=> 1));
    					Mage::log('Please check header format', null, 'logicbroker_ftp_vendor_inventory_import.log');
    				}
					//fix move file to archive folder in all cases
					$this->archiveFtpFile(array('object'=>$connectionResult['object'],'path'=>$path));
    			}
    		}
    		$this->finalize();
    	}else{
    		Mage::log('No files found on ftp server', null, 'logicbroker_ftp_vendor_inventory_import.log');
    		ftp_close($connectionResult['object']);
    		return $this;
    	}
    	ftp_close($connectionResult['object']);
    	$this->_csvParserObj->emptyTable();
    	$this->_csvDataCache = array();
    	Mage::helper('dropship360')->turnOnReadCommittedMode(); //restore to orignal transection level
    	return $this;
    }
    
    protected function initialize(){
    	Mage::helper('dropship360')->startProcess('manual_upload');
    	Mage::log('Ftp upload started', null, 'logicbroker_ftp_vendor_inventory_import.log');
    }
    
    protected function finalize(){
    	Mage::helper('dropship360')->finishProcess('manual_upload');
    	Mage::log('Ftp upload finished', null, 'logicbroker_ftp_vendor_inventory_import.log');
    }
    
    protected function archiveFtpFile($object)
	{
    	$path = str_replace("\\","/",$object['path']);
    	$patharr = explode('logicbrokervendorproduct',$path);
    	$dirname = pathinfo($patharr[1],PATHINFO_DIRNAME);
    	$basename = pathinfo($patharr[1],PATHINFO_BASENAME );
	$newname = Mage::getModel('core/date')->date('Ymd-his').'_'.$basename;
	$connection = $this->testFtpConnection($this->ftpRequestPram,true);
	if($connection['error'])  
		{  
		$this->sendMail(array('subject'=>'Your magento site has failed to connect FTP site','message' => "Connection Failure--Can not archive file -".$basename,'bcc'=>trim(Mage::helper('dropship360')->getConfigObject('apiconfig/email/bcc'))));
			Mage::log($connection['message'] ."--Can not archive file -".$basename, null, 'logicbroker_ftp_vendor_inventory_import.log'); 
		}		 
		else 
		{
			$object['object'] = $connection['object']; 
		} 
    	ftp_chdir($object['object'],$dirname);
    	ftp_mkdir($object['object'], 'Archive');
		ftp_chdir($object['object'],'Archive');
    	ftp_put($object['object'], $basename, $path, FTP_ASCII);
    	ftp_rename ($object['object'],$basename,$newname);
		ftp_chdir($object['object'],'/');
		$this->fileObj()->rm($object['path']);
		ftp_delete($object['object'], $dirname.'/'.$basename);
		ftp_close($connection['object']);
    	return;
    	
    }
    protected function validateFtpFile($file, $vendorCode = null)
	{
    	$file = str_replace("/","\\",$file);
    	$extension = pathinfo($file, PATHINFO_EXTENSION);    
    	return true;
    }
    
    protected function downloadFtpFile($ftpRequest,$file,$path, $vendorCode = null)
	{
    	if(!file_exists(self::getWorkingDir().$path)){
    		$patharr = explode('/',$path);
    		$this->fileObj()->mkdir(self::getWorkingDir().$patharr[0]);
			$this->fileObj()->chmod(self::getWorkingDir().$patharr[0],0777);
			$this->fileObj()->mkdir(self::getWorkingDir().$patharr[0].'/'.$patharr[1]);
			$this->fileObj()->chmod(self::getWorkingDir().$patharr[0].'/'.$patharr[1],0777);
    	}
    	$fileName = explode('/',$file);
    
    	ftp_chdir($ftpRequest,'/'.$fileName[0].'/'.$fileName[1]);
    	$server_file = $fileName[2];
    	$local_file = self::getWorkingDir().$path.DS.$fileName[2];
    	// download server file
    	if (ftp_get($ftpRequest, $local_file, $server_file, FTP_ASCII)){
    		 
    		return $fileName[2];
    	}else{	 
    		return false;
    	}
    }
    
    protected function uploadReport($file,$vendorCode){
    	$path = $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_ACCNUMBER).'_'.$vendorCode.'/'.'Inventory';
    	$reprotPath = 'report'.Mage::getModel('core/date')->date('Ymd-his').'.csv';
    	$connection = $this->testFtpConnection($this->ftpRequestPram,true);
    	if($connection['error'])
    	{
    		$this->sendMail(array('subject'=>'Your magento site has failed to connect FTP site','message' => "Connection Failure--Can not upload report file -".$basename,'bcc'=>trim(Mage::helper('dropship360')->getConfigObject('apiconfig/email/bcc'))));
    		Mage::log($connection['message'] ."--Can not archive file -".$basename, null, 'logicbroker_ftp_vendor_inventory_import.log');
    	}
    	else
    	{
    		$object = $connection['object'];
    	}
    	ftp_chdir($object,'/');
    	ftp_chdir($object,$path);
    	ftp_mkdir($object,'Reports');
    	ftp_chdir($object,'Reports');
    	ftp_put($object, $reprotPath,$file['value'], FTP_ASCII);
    	ftp_chdir($object,'/');
    	$this->fileObj()->rm( $file['value']);
    	ftp_close($connection['object']);
    	return;
    }
    protected function ftpUpdateVendorProduct($csvData,$path,$vendorCode = null)
	{
    	$records = array();
    	$success = array();
    	$failure = array();
		$itemerroroutput = array();
		$counter = 0;
    	//$vendorCode = '';
    	$tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/vendor_import_log' );
		 if(count($csvData) <= 1 && Mage::getModel('dropship360/csvparser')->isCsvFileEmpty())
    	{
            $failure[] = 'Sorry,we cant find the record to update inventory';
            $this->_FtpErrors[] =  array('error_type'=>'empty_file','value'=>'Sorry,we cant find the record to update inventory');           
    	} 
    	$records = Mage::getModel('dropship360/csvparser')->generateFtpCsvRow($csvData,$vendorCode);
		//$this->conn->beginTransaction ();
		if(is_array($records) && !empty($records)){
    	$requestData = array_chunk($records, 1, true);
    
    	foreach($requestData as $dataArr)
    	{
    		foreach($dataArr as $data){
				$result[] = $this->validateCsvData($data, true);
			}
		}					
		foreach($result as $successOrfail){
			if($successOrfail['success']!="")
			$success[] =  $successOrfail['success'];
			if($successOrfail['failure']!="")
			$failure[] = $successOrfail['failure'];
				
    	}
    	$this->checkDataIntigrity($this->_getCsvData($path),true);
    	}
    	$finalResultCounter = (!$this->_isProductSetupMode) ? $this->logForUnprocessedRows($vendorCode,true) : 0;
    	if(is_array($finalResultCounter))
    	{
    		$failed = count($failure)+$finalResultCounter['failure'];
    		$success = count($success)+$finalResultCounter['success'];
    	}else
    	{
    		$failed = count($failure)+$finalResultCounter;
    		$success = count($success)+$finalResultCounter;
    	}
    	$itemerroroutput = Mage::helper('core')->jsonEncode($this->_FtpErrors);
    	//$failed = count($failure)+$counter;
    	$ftp_err = ($failed > 0)  ? 'Missing/Bad Data' : '';
    	$insert = 'INSERT INTO '.$tableVendorImportLog.'(lb_vendor_code,updated_by,success,failure,ftp_error,created_at) VALUES ("'.$vendorCode.'","FTP",'.$success.','.$failed.',"'.$ftp_err.'","'.now().'")';
    	 $this->conn->beginTransaction ();
    	$this->conn->query($insert);
    	$entityId = $this->conn->lastInsertId($tableVendorImportLog);
    	try {
    		$this->conn->commit ();
    		if(count($this->_FtpErrors) > 0){
    		$csvFile = Mage::helper('dropship360')->generateErrorList(array('ftp_error_desc'=>$itemerroroutput,'lb_vendor_code'=>$vendorCode),true);
    		$this->uploadReport($csvFile,$vendorCode);
    		}
    		if($this->sendBadFileAlert){
    			$logPath = explode('logicbrokervendorproduct',str_replace("\\","/",$path));
    			$this->sendMail(array('isfailed'=>true,'vendor_code'=>$vendorCode,'subject'=>'dropship360 failed to update inventory','message' => 'Missing/Bad data, check CSV data at following FTP path <br>'.$logPath[1]));
    		}
    		$this->prepareInsertAndExeQuery($this->_FtpErrors,$entityId);
    		$itemerroroutput = array();
    		$this->_FtpErrors = array();
    	} catch ( Exception $e ) {
    		$this->conn->rollBack ();
    		Mage::log($e->getMessage(), null, 'logicbroker_ftp_vendor_inventory_import.log');
    		echo $e->getMessage();
    		 
    	}
    }
    
    protected function ftpVendorProductUpdate($data)
	{	 
    	$tableVendorInventory = Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/inventory' );
    	$inventoryCollectionResult = $this->getInventoryCollection($data,true);
    	$qtyArray = $this->calculateProductQty(array('magento_sku'=>$inventoryCollectionResult['magento_sku'],'qty'=>$data['qty'],'lb_vendor_code'=>$data['lb_vendor_code'])); 	
    	switch($inventoryCollectionResult['operationType'])
    	{
    		case 'update':
    			$productId = Mage::getModel('catalog/product')->getIdBySku($inventoryCollectionResult['magento_sku']);
    			if($productId){
    					$update = $this->_prepareUpdateQuery($data,$qtyArray,$inventoryCollectionResult,$tableVendorInventory,true);
    				try {
    					if(!$this->updateProductInventory($inventoryCollectionResult['magento_sku'],$qtyArray['final_qty']))
    					{
    						$this->_FtpErrors[] = array('error_type'=>'inventory_update_error','value'=>array('magento_sku'=>$inventoryCollectionResult['magento_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));;
    						$this->sendBadFileAlert = true;
    						return false;
    					}
						if($update){
							$this->conn->beginTransaction ();
    					$this->conn->query($update);
							$this->conn->commit ();
						}
    					return true;
    				} catch ( Exception $e ) {
    					$this->conn->rollBack ();
    					Mage::log($e->getMessage(), null, 'logicbroker_ftp_vendor_inventory_import.log');
    					echo $e->getMessage();
    				}
    				 
    			}else{
    				$this->_FtpErrors[] = array('error_type'=>'combination_notexist','value'=>array('magento_sku'=>'','qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
    				$this->sendBadFileAlert = true;
    				return false;
    			}
    			break;
    		default :
		/*fix for ticket lbn-710 vendor_sku not visible*/
    			$this->_FtpErrors[] = array('error_type'=>'combination_notexist','value'=>array('magento_sku'=>'','qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));;
    			$this->sendBadFileAlert = true;
    			return false;
    	}	 
    	return true;
    }
    
    protected function genrateLogEntry($message)
    {
    	$vendorCode = (!empty($message['lb_vendor_code'])) ? $message['lb_vendor_code'] : '';
    	$ftp_error = (!empty($message['ftp_error'])) ? $message['ftp_error'] : '';
    	$ftp_error_desc = (!empty($message['ftp_error_desc'])) ? $message['ftp_error_desc'] : '';
    	$tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/vendor_import_log' );
    	$this->conn->beginTransaction ();
		$now = now();
    	$insert = 'INSERT INTO '.$tableVendorImportLog.'(lb_vendor_code,updated_by,success,failure,created_at,ftp_error,ftp_error_desc) VALUES ("'.$vendorCode.'","FTP",'.count($success).','.$message['error'].',"'.$now.'","'.$ftp_error.'","'.$ftp_error_desc.'")';
    	$this->conn->query($insert);
    	try {
    		$this->conn->commit ();
    	} catch ( Exception $e ) {
    		$this->conn->rollBack ();
    		Mage::log($e->getMessage(), null, 'logicbroker_ftp_vendor_inventory_import.log');
    		echo $e->getMessage();
    		 
    	}
    }
    
	/**
	  * Check csv data for qty and cost values
	  * @param array $data, bool $isFtp
	  * @return array
	  */
	protected function validateCsvData($data, $isFtp=false){
		$invalidData = false;	
		$success = 0;
		$failure = 0;
		$ignoreData = array();	
		
		$inventoryCollectionResult = $this->getInventoryCollection($data,true,($isFtp) ? false : true);
		$data['magento_sku'] = $inventoryCollectionResult['magento_sku'];
		
		if(Mage::getModel('catalog/product')->getIdBySku(trim($data['magento_sku']))){
			if(!is_numeric($data['qty']) || $data['qty'] < 0){
					$ignoreData[]= 'qty';
			}
			if(!is_numeric($data['cost']) || $data['cost'] < 0){			
					$ignoreData[]= 'cost';
			}
			if((!is_numeric($data['cost']) || $data['cost'] < 0) && (!is_numeric($data['qty']) || $data['qty'] < 0)){
				if($data['cost']!="" && $data['qty']!="")
				$invalidData = true;
			}	
			/* fix for lbn-954*/
			if(!$duplicateCombination = $this->chekDuplicateCombination($data)){        
				$invalidData = true;
				if(!$isFtp){
					$this->_UploadCsvErrors[] =  array('error_type'=>'combination_exist','value'=>array('magento_sku'=>$data['magento_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
				}else{
					$this->_FtpErrors[] =  array('error_type'=>'combination_exist','value'=>array('magento_sku'=>$data['magento_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
					$this->sendBadFileAlert = true;
				}					
			}
			$vendorCollection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$data['lb_vendor_code'])->addFieldTofilter('lb_vendor_sku',trim($data['vendor_sku']));
			if($vendorCollection->getSize() == 0){
				$spacesTrimmedSku = $this->getTrimmedSku();
				$searchedSku = array_search(trim($data['vendor_sku']), $spacesTrimmedSku);
				if($searchedSku){
					$vendorCollection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$data['lb_vendor_code'])->addFieldTofilter('lb_vendor_sku',$searchedSku);
				}
		    }	
			if($vendorCollection->getSize() > 0){
				if($data['magento_sku']!=$vendorCollection->getFirstItem ()->getProductSku()){
					$invalidData = true;
					 $this->_UploadCsvErrors[] =  array('error_type'=>'already_assigned','value'=>array('magento_sku'=>$data['magento_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
				}
			}					
			if($invalidData){
				$failure+=1;
			}else{
				if($isFtp){
					($this->ftpVendorProductUpdate($data)) ? $success += 1 : $failure+=1;
				}else{
					($this->vendorProductInsert($data)) ? $success += 1 : $failure+=1;
				}
								
			}
		}else{
			$failure+=1;
			if(!$isFtp){
				if(trim($data['vendor_sku']))	
				$this->_UploadCsvErrors[] = array('error_type'=>'combination_notexist','value'=>array('magento_sku'=>'','qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
			}else{
				if(trim($data['vendor_sku']))	
				$this->_FtpErrors[]  = array('error_type'=>'combination_notexist','value'=>array('magento_sku'=>'','qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
				$this->sendBadFileAlert = true;
			}
		}
		if($data['magento_sku']){
			if($isFtp){
				$insertInventoryLog = $this->getInventoryLogQuery($data,'update',$data['qty'],'FTP', $ignoreData);
			}else{
				$insertInventoryLog = $this->getInventoryLogQuery($data, $inventoryCollectionResult['operationType'], $data['qty'], null,$ignoreData);
			}
			if($insertInventoryLog)
			{
				$this->conn->beginTransaction ();
				$this->conn->query($insertInventoryLog);
				try{
					$this->conn->commit ();
				}catch(Exception $e){
					$this->conn->rollBack ();
				}
			}
		}
		return	array('success'=>$success, 'failure'=>$failure);	
	} 


	/**
	  * Validate product setup data for manual upload
	  * @param array $data, bool $isFtp
	  * @return array
	  */
	protected function validateProductSetupMode($data, $isFtp = false){
		$invalidData = false;	
		$success = 0;
		$failure = 0;
		$type = "Product Setup";
		if(trim($data['vendor_sku'])=="" || trim($data['magento_sku'])==""){
			return	array('success'=>$success =0, 'failure'=>$failure+=1);	
		}		
		if(Mage::getModel('catalog/product')->getIdBySku(trim($data['magento_sku']))){
			$collection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$data['lb_vendor_code'])->addFieldTofilter('lb_vendor_sku',$data['vendor_sku']);
			if($collection->count() > 0){
				$existing_product_sku = $collection->getFirstItem()->getProductSku();				
				if(!empty($existing_product_sku)){                
					if($data['magento_sku'] != $existing_product_sku){
						$invalidData = true;
						$this->_UploadCsvErrors[] = array('error_type'=>'duplicate_vendor_sku','value'=>array('magento_sku'=>$data['magento_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));	
	  
					}else{
						$invalidData = true;
						$this->_UploadCsvErrors[] =  array('error_type'=>'combination_exist','value'=>array('magento_sku'=>$data['magento_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost'])); 	
					}
				}
			}
			$inventoryCollection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$data['lb_vendor_code'])->addFieldTofilter('product_sku',$data['magento_sku']);
			if($inventoryCollection->getSize() > 0){
				$inventoryCollection = $inventoryCollection->getData(); 
				$inventoryCollection = $inventoryCollection[0];
				$existing_vendor_sku =  $inventoryCollection['lb_vendor_sku'];
				if(trim($data['vendor_sku'])!=""){	
					if($existing_vendor_sku != trim($data['vendor_sku'])){
						if($existing_vendor_sku != trim($data['vendor_sku'])){
							$data['operationType'] = "update";
							$data['cost'] = "";
							$data['qty']  = "";
							$data['pucost'] = $inventoryCollection['cost'];
							$data['puqty'] = $inventoryCollection['stock'];
							$type = "Product Update";
						} 
					} 
				}	
			}  					
			if($invalidData){
				$failure+=1;
			}else{
				($this->vendorProductInsert($data)) ? $success += 1 : $failure+=1;
				if($this->vendorProductInsert($data)){
					$insertInventoryLog = $this->getInventoryLogQuery($data, $type, 0, null, null);
					if($insertInventoryLog){
						$this->conn->beginTransaction ();
						$this->conn->query($insertInventoryLog);
						try{
							$this->conn->commit ();
						}catch(Exception $e){
							$this->conn->rollBack ();
						}
					}
				}				
			}
		}else{
			$failure+=1;
			$this->_UploadCsvErrors[] = array('error_type'=>'magento_sku_exists','value'=>array('magento_sku'=>$data['magento_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
		}
		return	array('success'=>$success, 'failure'=>$failure);	
	}  
	
    /* method for bulk assignment of vendor code to all product*/
    public function prepareBulkassignmentCollection($vendorCode)
    {
		$numberOfRecords = 200;
    	$Lbsku = array();
    	$magentoSku = array();
    	$magentoSkuCollection = Mage::getModel('catalog/product');
    	if(count($Lbsku) > 0)
   	 		$productCollection = $magentoSkuCollection->getCollection()->addAttributeToSelect('sku')->addAttributeToFilter('sku', array('nin' => $Lbsku));
    	else
    		$productCollection = $magentoSkuCollection->getCollection()->addAttributeToSelect('sku')->addAttributeToFilter('type_id','simple');
    	
    	if($productCollection->getSize() > 0){
    		$chunkSkus = array_chunk($productCollection->getData(), $numberOfRecords);
    		
    		foreach($chunkSkus as $skus)
    		{
    		foreach($skus as $mageSku){
    			$magentoSku[] = $mageSku['sku'];
    		}
    	}
    	}
    	return $magentoSku;
    }
	
	/**
	 * Get Varien I/O File class object
	 * @return object
	 */
	protected function fileObj()
	{
		$fileObj = new Varien_Io_File();
		return $fileObj;
	}
	
	/**
	 * Get vendor skus with satrting and trailing spaces 
	 * @return array
	 */
	protected function getTrimmedSku()
	{
		$read = Mage::getSingleton ('core/resource')->getConnection ('core_read');
	    $tableName = Mage::getSingleton ('core/resource')->getTableName('logicbroker_vendor_inventory');
		$trimSpacesQuery = 'SELECT lb_vendor_sku FROM '.$tableName.' WHERE lb_vendor_sku LIKE '. '"% %"';
		$result = $read->fetchAll($trimSpacesQuery);
		$trimmedSkus = array();
		if(count($result) > 0){
			foreach($result as $k=>$v){
				foreach($v as $sku)
				$trimmedSkus[$sku] = trim($sku);
			}
	    }	
		return $trimmedSkus;
	}
	
	protected function sendMail($mailData = array()){
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
	protected function logForUnprocessedRows($vendorCode,$isFtp = false){
		$msg = '';
		$error = 0;
		$success = 0;
		$proObj = Mage::getModel('dropship360/ranking')->load($vendorCode,'lb_vendor_code');
		$proLinkAttr = $proObj->getLinkingAttribute();
		$proLinkAttr = empty($proLinkAttr) ? 'none' : $proLinkAttr;
		$this->supplierName = $proObj->getLbVendorName();
		$helper = Mage::helper('dropship360');
		$csvData = Mage::getModel('dropship360/csvparser')->getUnprocessedCsvRows($vendorCode,$isFtp);
		if(count($csvData) > 0 ){
		foreach($csvData as $data){
		$collection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$vendorCode)->addFieldTofilter('lb_vendor_sku',$data['vendor_sku']);
		if($collection->getSize() > 0)
		{
			$msg = array('error_type'=>'data_notchnage','value'=>array('magento_sku'=> $collection->getFirstItem()->getProductSku(),'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
			(!$isFtp) ? $this->_UploadCsvErrors[] = $msg : 	$this->_FtpErrors[] = $msg;
			$error++;
		}else
		{
			switch ($proLinkAttr) {
				case  'none':
					$msg = array('error_type'=>'combination_notexist','value'=>array('magento_sku'=>'','qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
					(!$isFtp) ? $this->_UploadCsvErrors[] = $msg : 	$this->_FtpErrors[] = $msg;
					$error++;
					break;
				default:
			$proCol = Mage::getModel('catalog/product')->getCollection();
					if(!$this->checkAttributeAval($proLinkAttr,$proCol)){
						$msg = array('error_type'=>'attribute_notexist','value'=>array('magento_sku'=>'','qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
						(!$isFtp) ? $this->_UploadCsvErrors[] = $msg : 	$this->_FtpErrors[] = $msg;
						$error++;
					}else{
						$product = $proCol->addAttributeToFilter($proLinkAttr,$data['vendor_sku']);
						if($this->validateGenerateProduct($product,$data,$proLinkAttr,$isFtp)){
							$tempArray = $this->genNonExistPro($product,$data,$isFtp);
							!empty($tempArray['success']) ?  $success++ : '';
							!empty($tempArray['failure']) ?  $error++ : '';
						}else
							{
								$error++;
							}
					}
				break;
					}
				}
			}
			$csvData[0] = $this->_csvDataCache[0];
			$this->emptyRecords = array(); 
			$this->result = array();
			$this->checkDataIntigrity($csvData,$isFtp);
		}
		return array('success'=>$success,'failure'=>$error);
	}
	protected function prepareInsertAndExeQuery($csvData,$entityId){
		if(count($csvData) <= 0 || empty($entityId))
			return ;
		$tableName = Mage::getSingleton ( 'core/resource' )->getTableName ('dropship360/vendor_import_log_desc');
		
		foreach($csvData as $data)
		{
		try {
				$this->conn->insertArray($tableName,array('error_id','description'),array(array($entityId,Mage::helper('core')->jsonEncode($data))));
		} catch ( Exception $e ) {
            Mage::logException($e);
            	Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
			}
			
		}
		return ;
	}
	protected function validateGenerateProduct($product,$data,$attr,$isFtp){
		$isValid = true;
		$helper = Mage::helper('dropship360');
		if($product->getSize() == 0)
		{
			$errorType = ($attr == $helper::LOGICBROKER_PRODUCT_LINK_CODE_SKU) ? 'magento_sku_exists' : $attr.'_notexist'; 
			$msg = array('error_type'=>$errorType,'value'=>array('magento_sku'=>$data['vendor_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
			(!$isFtp) ? $this->_UploadCsvErrors[] = $msg : 	$this->_FtpErrors[] = $msg;
			$isValid = false;
		}elseif($product->getSize() > 1)
		{
			$msg = array('error_type'=>$attr.'_multiple','value'=>array('magento_sku'=>'','qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
			(!$isFtp) ? $this->_UploadCsvErrors[] = $msg : 	$this->_FtpErrors[] = $msg;
			$isValid = false;
		}else{
			$data['magento_sku'] = $product->getFirstItem()->getSku();
			if(!$this->chekDuplicateCombination($data))
			{
				$msg = array('error_type'=>'combination_exist','value'=>array('magento_sku'=>$data['magento_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
				(!$isFtp) ? $this->_UploadCsvErrors[] = $msg : 	$this->_FtpErrors[] = $msg;
				$isValid = false;
			}
		}
			
		return $isValid;
	}
	
	protected function genNonExistPro($proObj,$data,$isFtp){
		$invalidData = false;
		$failure = 0;
		$success = 0;
		$ignoreData = array();
		$data['magento_sku'] = $proObj->getFirstItem()->getSku();
		/* LBN - 935 change */
		 $data['qty'] = (is_numeric($data['qty'])) ? Mage::helper('dropship360')->getIsQtyDecimal($data['magento_sku'],$data['qty']) : $data['qty'];
		if(!is_numeric($data['qty']) || $data['qty'] < 0){
			$ignoreData[]= 'qty';
		}
		if(!is_numeric($data['cost']) || $data['cost'] < 0){
			$ignoreData[]= 'cost';
		}
		if((!is_numeric($data['cost']) || $data['cost'] < 0) && (!is_numeric($data['qty']) || $data['qty'] < 0)){
			if($data['cost']!="" && $data['qty']!="")
				$invalidData = true;
		}
		
		if($invalidData){
			$failure+=1;
		}else{
			($this->insertNonExistPro($proObj,$data,$isFtp)) ? 	$success += 1 : $failure += 1;
			}
		
		$this->insertInventoryLog($ignoreData,$data,$isFtp);

		return array('success'=>$success,'failure'=>$failure);
	}
	
	protected function insertNonExistPro($proObj,$data,$isFtp = false){
		
		$vendorCode = ($isFtp) ? $data['lb_vendor_code'] : $this->_vendorCode;
		$tableVendorInventory = Mage::getSingleton ('core/resource')->getTableName('dropship360/inventory');
		$qtyArray = $this->calculateProductQty($data);
		$costInsert = (!is_numeric($data['cost']) || $data['cost'] < 0 || trim($data['cost']) =="") ? 0 : $data['cost'] ;
		$qtyInsert = ($qtyArray['upload_qty'] == .999999999 || trim($data['qty']) =="" ) ? 0 : $qtyArray['upload_qty'];
		$dbFields = array('lb_vendor_code','lb_vendor_name','product_sku','lb_vendor_sku','stock','cost','created_at','updated_at');
		$dbFieldVal = array(
			array($vendorCode,$this->supplierName,$data['magento_sku'],$data['vendor_sku'],$qtyInsert,$costInsert,now(),now())
		);
		try {
			if(!$this->updateProductInventory(trim($data['magento_sku']),$qtyArray['final_qty']))
			{
				$this->_UploadCsvErrors[] = array('error_type'=>'inventory_update_error','value'=>array('magento_sku'=>$data['magento_sku'],'qty'=>$data['qty'],'vendor_sku'=>$data['vendor_sku'],'cost'=>$data['cost']));
				return false;
			}
			$this->conn->insertArray($tableVendorInventory,$dbFields,$dbFieldVal);
			return true;
		} catch ( Exception $e ) {
			Mage::logException($e);
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
		}
		
		
	}
	protected function insertInventoryLog($ignoreData,$data,$isFtp)
	{
		$type = 'add';
		$vendorCode = ($isFtp) ? $data['lb_vendor_code'] : $this->_vendorCode;
		if(count($ignoreData)>0){
			(in_array('qty', $ignoreData)) ? $type = 'Cost Added, Qty Ignored' : '';
			(in_array('cost', $ignoreData)) ? $type = 'Qty Added, Cost Ignored' : '';
			
			if(count($ignoreData)==2){
				$type = 'ignore';
			}
		}
		if($data['qty']==0.999999999)
			$data['qty'] = 0;
		$tableName = Mage::getSingleton("core/resource")->getTableName('dropship360/inventorylog');
		$updatedBy = (!$isFtp) ? Mage::getSingleton('admin/session')->getUser()->getUsername() : 'FTP';
		$dbFields = array('lb_vendor_code','lb_vendor_name','product_sku','cost','stock','updated_by','activity','updated_at','created_at');
		$dbFieldVal = array(
			array($vendorCode,$this->supplierName,$data['magento_sku'],$data['cost'],$data['qty'],$updatedBy,$type,now(),now())
		);
		try {
			$this->conn->insertArray($tableName,$dbFields,$dbFieldVal);
			return true;
		} catch ( Exception $e ) {
			Mage::logException($e);
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
		}
		
	}
	protected function checkAttributeAval($attr,$object){
		$isExist = false;
		$attrEav = Mage::getResourceModel('catalog/eav_attribute')->loadByCode('catalog_product',$attr);
		if ($attrEav->getId())
			$isExist = true;
		return $isExist;
	}
}