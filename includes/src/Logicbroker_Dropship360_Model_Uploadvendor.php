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
	protected $_haderError = array();
	protected $_FtpErrors = array();
	protected $_UploadCsvErrors = array();
	protected $_inventoryModel; 
    protected $_vendorSkuFlag = array();
	protected $conn;
	protected $_errors = array();
	const XML_PATH_UPLOAD_ENABLED          = 'logicbroker_sourcing/cron_settings_upload/enabled';
	const XML_PATH_UPLOAD_FTP_SITE   = 'logicbroker_sourcing/cron_settings_upload/ftp_site';
	const XML_PATH_UPLOAD_FTP_USERNAME   = 'logicbroker_sourcing/cron_settings_upload/ftp_username';
	const XML_PATH_UPLOAD_FTP_PASSWORD   = 'logicbroker_sourcing/cron_settings_upload/ftp_password';
	const XML_PATH_UPLOAD_FTP_TYPE   = 'logicbroker_sourcing/cron_settings_upload/ftp_type';
	const XML_PATH_UPLOAD_FTP_ACCNUMBER  = 'logicbroker_sourcing/cron_settings_upload/ftp_accnumber';
	
	
	protected function _construct()
	{
		$this->_inventoryModel = Mage::getModel('logicbroker/inventory');
		$this->conn = $this->getDatabaseConnection ();
		$this->_init("logicbroker/uploadvendor");
		
	}
	
	public function getDatabaseConnection() 
	{
		return Mage::getSingleton ( 'core/resource' )->getConnection ( 'core_write' );
	}
	
	protected function _getSession()
	{
		return Mage::getSingleton('adminhtml/session');
	}
	
	protected function _getCsvData($fileName)
	{
		$csvObject  = new Varien_File_Csv();
		$csvData = $csvObject->getData($fileName);		 
		return $csvData;
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
			$this->_getSession()->addError(Mage::helper('logicbroker')->__($e->getMessage()));
		}	
	}
	
 	public function uploadSource()
    {
    	$error = false;   	
    	$this->_isProductSetupMode = $this->getProductsetupmode();   	
    	$entity = 'vendor_product_'.date('ymdHis');
    	$uploader  = Mage::getModel('core/file_uploader', self::FIELD_NAME_SOURCE_FILE);
        $uploader->skipDbProcessing(true);
        $result    = $uploader->save(self::getWorkingDir());
        $extension = pathinfo($result['file'], PATHINFO_EXTENSION);
        $uploadedFile = $result['path'] . $result['file'];
        if (!$extension) {
            $this->fileObj()->rm($uploadedFile);
            throw new Exception(Mage::helper('importexport')->__('Uploaded file has no extension'));
            return $error = true;
            
        }        
        if (strtolower($extension) != 'csv') {
        	$this->fileObj()->rm($uploadedFile);
        	throw new Exception(Mage::helper('importexport')->__('Incorrect file type uploaded. Please upload your Inventory feed in .csv format.'));
        	return $error = true;
        }
        
        $error = $this->validateCsv($uploadedFile);
        if($error){
        	$this->fileObj()->rm($uploadedFile);
        	$this->_getSession()->addNotice(Mage::helper('logicbroker')->__('Please fix errors and re-upload file'));
        	return $error;
        }
        
        $sourceFile = self::getWorkingDir() . $entity;

        $sourceFile .= '.' . strtolower($extension);

        if(strtolower($uploadedFile) != strtolower($sourceFile)) {
            if (file_exists($sourceFile)) {
				$this->fileObj()->rm($sourceFile);
            }

            if (!@rename($uploadedFile, $sourceFile)) {
                Mage::throwException(Mage::helper('importexport')->__('Source file moving failed'));
            }
        }
        Mage::register('file_name',$entity);
        if(!$error)
        $this->insertCronEntry($entity);
        
        return $error;
    }
    
   
    public function validateCsv($fileName)
    {
    	//$fileName  
    	$isError = false;
    	$csvData = $this->_getCsvData($fileName);
    	
    	/** checks columns */
    	
    	if($this->validateCsvHeader($csvData)){
    		$dataValidation = false;
    	if($dataValidation){
			$isError = true;
		}   		
    	}else{    		
    		$this->_getSession()->addError(Mage::helper('logicbroker')->__('CSV header %s is invalid ',implode(',',$this->_haderError)));
    		$isError = true;
    	}
    	return $isError; 
    }
    
    protected function validateCsvHeader($csvData,$isFtp = false)
    {    	
    	if	($this->_isProductSetupMode && !$isFtp ){
    	$csvFields  = array(
    				0   => Mage::helper('logicbroker')->__('magento_sku'),
    				1   => Mage::helper('logicbroker')->__('vendor_sku'),
    				2   => Mage::helper('logicbroker')->__(''),
    				3   => Mage::helper('logicbroker')->__('')
    		);
    	}else{
    		$csvFields  = (!$isFtp) ? array(
    				0   => Mage::helper('logicbroker')->__('vendor_sku'),
    				1   => Mage::helper('logicbroker')->__('qty'),
    				2   => Mage::helper('logicbroker')->__('cost'),
    		) : array(
    				0   => Mage::helper('logicbroker')->__('vendor_code'),
    			1   => Mage::helper('logicbroker')->__('vendor_sku'),
    			2   => Mage::helper('logicbroker')->__('qty'),
    			3   => Mage::helper('logicbroker')->__('cost')
    	);
    	}

    	$cvsDataNum   = count($csvData[0]);
    	$result = true;
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
    		
    	}else if($this->_isProductSetupMode ){
    		if($cvsDataNum != 2){
    			foreach ($csvData[0] as $val){
    				if(!in_array($val,array('vendor_sku','magento_sku')))
    				{
    					$this->_haderError[] = $val;
    				}
    			}
				return false;
    		}
    		
    	}else{
			if($cvsDataNum == 3 || $cvsDataNum == 4){
    			foreach ($csvData[0] as $val){
    				if(!in_array($val,($cvsDataNum == 3) ? array('vendor_sku','qty', 'cost') : array('vendor_code','vendor_sku','qty', 'cost'))){
    					$this->_haderError[] = $val;
    				}
    			}
				if(!empty($this->_haderError))
				return false;
				else
				return true;
    		}else
			{
				$this->_haderError[] = 'csv not supported';
				return false;
			}
			
		}
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
    
	protected function checkDataIntigrity($csvData,$isFtp = false){
    	$emptyRecords = array();
    	//patch for FTP backward compatibility header
    	(count($csvData[0]) <= 3) ? array_unshift($csvData[0], "") : $csvData[0];
    	foreach($csvData as $row => $csvRowData)
    	{
    		$error = true;	
    		if($row == 0)
    			continue;
			if(!$this->_isProductSetupMode && !$isFtp){
				array_unshift($csvRowData, "");
			}
			//patch for FTP backward compatibility data
			if($isFtp)
			(count($csvRowData) <= 3) ? array_unshift($csvRowData, "") : $csvRowData;
						
				
    		foreach($csvRowData as $key => $data){
				$data = trim($data);
				switch($key){
					case 0:
						if($this->_isProductSetupMode){
							if(empty($data)){
								$emptyRecords['magento_sku'][] = $row;
								$result[] = true;
							} else {
							$error = false; $result[] = false;
							}
						}else{
							continue;
						}	
						break;
					case 1:
						if(empty($data)){
							$emptyRecords['vendor_sku'][] = $row;
							$result[] = true;
						} 
						else 
							$error = false; $result[] = false;
						break;
					case 2:
						if(!is_numeric($data)  || $data < 0 ) 
						{
							if($data!=""){
								$emptyRecords['qty'][] = $row;
								$result[] = true;
							}
						}
						else 
							$error = false; $result[] = false;
						break;
					case 3:
						if(!is_numeric($data) || $data < 0 ){
							if($data!=""){
								 $emptyRecords['cost'][] = $row;
								 $result[] = true;
							}
						}
						else
							 $error = false; $result[] = false;
						break;
				}	
    		}
    	
    		if (!$isFtp) {
				$error = in_array(true,$result) ? true : false;				
				if($error){
					foreach($emptyRecords as $key=>$value){			
						if($this->_isProductSetupMode){
							if($key == 'magento_sku'){
								$string = implode(',',$value);
								$this->_UploadCsvErrors['magento_sku'] = 'Missing Data at Row(s) ' .$string.' are missing data in magento_sku';
							}
						}
						if($key == 'vendor_sku'){
							$string = implode(',',$value);
							$this->_UploadCsvErrors['vendor_sku'] = 'Missing Data at Row(s) ' .$string.' are missing data in vendor_sku';
						}
						if($key == 'qty'){	
							$string = implode(',',$value);
							$this->_UploadCsvErrors['qty'] =  'Bad Data at Row(s) '.$string.' contain bad data in qty';
						}
						if($key == 'cost'){
							$string = implode(',',$value);
							$this->_UploadCsvErrors['cost'] = 'Bad Data at Row(s) '.$string.' contain bad data in cost';
						}	
					}	
				}
			} else {
				$error = in_array(true,$result) ? true : false;
				if ($error){
					foreach($emptyRecords as $key=>$value){
						if($key == 'vendor_sku')
						{	$string = implode(',',$value);
						$this->_FtpErrors['vendor_sku'] = 'Missing Data at Row(s) ' .$string.' are missing data in vendor_sku';
						}
						if($key == 'qty')
						{	$string = implode(',',$value);
						$this->_FtpErrors['qty'] =  'Bad Data at Row(s) '.$string.' contain bad data in qty';
						}
						if($key == 'cost')
						{	$string = implode(',',$value);
						$this->_FtpErrors['cost'] = 'Bad Data at Row(s) '.$string.' contain bad data in cost';
						}
					}
				}
			}	
    	}
		return in_array(true,$result) ? true : false;
    }
    
    protected function getConfigValue($path)
	{
    	return Mage::getStoreConfig($path);
    }
    protected function getMagentoSku($vendorCode,$vendorSku){
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
		$foramterroroutput = array();
    	$tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/vendor_import_log' ); 	
    	$csvData = $this->_getCsvData(self::getWorkingDir().$fileName.'.csv');
      
        if(count($csvData) <= 1 )
    	{
            $failure[$fileName.'.csv'] = 'File is empty'; 
            $this->_UploadCsvErrors['empty_file'] =  'File is empty';            
    	} 
    	
		foreach($csvData as $row => $csvRowData)
    	{
    		if($row == 0)
    			continue;
    		
    		if(!$this->_isProductSetupMode)
    		{
    		(count($csvRowData) <= 3) ? array_unshift($csvRowData, "") : $csvRowData;
    		if(is_numeric($csvRowData[2])){
            /* LBN - 935 change */
    		$magento_sku = $this->getMagentoSku($lb_vendor_code, trim($csvRowData[1]));
    		$csvqty = (!empty($magento_sku)) ? Mage::helper('logicbroker')->getIsQtyDecimal($magento_sku,$csvRowData[2]) : $csvRowData[2];;
    		}                 
    		else
    		{
    			$csvqty = $csvRowData[2];
    		}
    		}
    		if(!$this->_isProductSetupMode)
    		$records[$row] = array('vendor_sku'=>trim($csvRowData[1]),'qty'=>$csvqty ,'cost'=>$csvRowData[3],'lb_vendor_code'=>$lb_vendor_code); 
    		else
    			$records[$row] = array('magento_sku'=>trim($csvRowData[0]),'vendor_sku'=>trim($csvRowData[1]),'qty'=>0 ,'cost'=>0,'lb_vendor_code'=>$lb_vendor_code);
    	}
    	
    	$this->conn->beginTransaction ();
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
    	$updateFileStatus = $this->load($fileName,'file_name');
    	$updateFileStatus->setImportStatus('done');
	    
	    try{
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
		if(isset($this->_UploadCsvErrors['general_error'])){
	    $this->_UploadCsvErrors['other'] = implode(' , ', $this->_UploadCsvErrors['general_error']);
	    unset($this->_UploadCsvErrors['general_error']);
		}
		foreach($this->_UploadCsvErrors as $k=>$output){
			if($k =='combination_error' && is_array($output)){
				foreach($output as $oput){
					$foramterroroutput[] = '<li>'.$oput.'</li>';
				}
			}else{			
				$foramterroroutput[] = '<li>'.$output.'</li>';
			}	
		}		
		array_unshift($foramterroroutput,'<ul>');
		array_push($foramterroroutput,'</ul>');
		$errorDiscription = implode('',$foramterroroutput);
				unset($foramterroroutput);
		$ftp_err = (count($failure) > 0)  ? 'Missing/Bad Data' : '';
	    $insert = 'INSERT INTO '.$tableVendorImportLog.'(lb_vendor_code,updated_by,success,failure,ftp_error,ftp_error_desc,created_at) VALUES ("'.$lb_vendor_code.'","'.Mage::getSingleton('admin/session')->getUser()->getUsername().'",'.count($success).','.count($failure).',"'.$ftp_err.'","'.$errorDiscription.'","'.now().'")';
	  
	    $this->conn->query($insert);
	    
	    try {
	    	$this->conn->commit ();
	    	$file = self::getWorkingDir() . $fileName.'.csv';
	    	$this->fileObj()->rm($file);
	    } catch ( Exception $e ) {
	    	$this->conn->rollBack ();
	    	$this->_errors[] = $e->getMessage();
            $this->_errors[] = $e->getTrace();
            Mage::log($e->getMessage(), Zend_Log::ERR);
            Mage::logException($e);
            echo $e->getMessage();
	    
	    }
        unset($this->_vendorSkuFlag);
	    return $this;
    }

	
    protected function chekDuplicateCombination($data)
    {
        $result = true;
		$collection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$data['lb_vendor_code'])->addFieldTofilter('lb_vendor_sku',trim($data['vendor_sku']));
         if($collection->count() > 0){ 
			 $existing_product_sku = $collection->getFirstItem()->getProductSku();
			if(!empty($existing_product_sku)){                
				if(trim($data['magento_sku']) != trim($existing_product_sku)){
				   $result = false;  
				}
			}
		}
		$inventoryCollection = $this->_inventoryModel->getCollection()->addFieldTofilter('lb_vendor_code',$data['lb_vendor_code'])->addFieldTofilter('product_sku',trim($data['magento_sku']));
		if($inventoryCollection->getSize() > 0){
		   $inventoryCollection = $inventoryCollection->getData(); 
		   $inventoryCollection = $inventoryCollection[0];
		   $existing_vendor_sku =  $inventoryCollection['lb_vendor_sku'];
		   if(trim($existing_vendor_sku) != trim($data['vendor_sku']))
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
    	$tableVendorInventory = Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/inventory' );
    	$inventoryCollectionResult = $this->getInventoryCollection($data);
    	$qtyArray = $this->calculateProductQty($data);
    	
    	switch($inventoryCollectionResult['operationType'])
    	{
    		case 'update':
    			$productId = Mage::getModel('catalog/product')->getIdBySku(trim($data['magento_sku']));
    			if($productId){
    				if(!is_numeric($data['cost']) || $data['cost'] < 0 || trim($data['cost']) =="")
    				{
    					$costUpdate = '';
    				}else
    				{
    					$costUpdate = 'cost ='. $data['cost'] . ',';
    				}
    				
    				if($qtyArray['upload_qty'] == .999999999 || trim($data['qty']) =="" )
    				{
    					$qtyUpdate = '';
    				}else
    				{
    					$qtyUpdate = ' stock = '.$qtyArray['upload_qty']. ',';
    				}    										
					if($costUpdate=='' && $qtyUpdate =='' && !$this->_isProductSetupMode){
						$timeUpdate = "";
					}else{
						$timeUpdate = ' updated_at = "'.now(). '",';
					}
					$vSkuUpdate = ' lb_vendor_sku = "'.$data['vendor_sku']. '"';
				    $update = 'update '.$tableVendorInventory.' set '.$costUpdate.$qtyUpdate.$timeUpdate.$vSkuUpdate.' where id = '.$inventoryCollectionResult['vendor_id'];
    				
    			try {
    					if(!$this->updateProductInventory(trim($data['magento_sku']),$qtyArray['final_qty']))
    					{
    						$this->_UploadCsvErrors['general_error'][] = 'Update error: Error in updating magento product inventory';
    						return false;
    					}
						if($update)
    					$this->conn->query($update);
    					return true;
    				} catch ( Exception $e ) {
    					$this->_errors[] = $e->getMessage();
            			$this->_errors[] = $e->getTrace();
            			Mage::log($e->getMessage(), Zend_Log::ERR);
           				Mage::logException($e);
           				echo $e->getMessage();
    				}
					   
    			}else
    			{
    				$this->_UploadCsvErrors['general_error'][] = 'Update error: magento product sku ' .$data['magento_sku'].' not exist';
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
    							$this->_UploadCsvErrors['general_error'][] = 'Add error: Error in updating magento product inventory';
    							return false;
    						}
    						$this->conn->query($insert);
    						return true;
    					} catch ( Exception $e ) {
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
    
    protected function getInventoryLogQuery($data,$type,$qty,$updateBy=null,$ignoreData)
    {
		if(count($ignoreData)>0){
			if($type=='update'){
				if(in_array('qty', $ignoreData))
				$type = 'Cost Updated, Qty Ignored';
				if(in_array('cost', $ignoreData))
				$type = 'Qty Updated, Cost Ignored';
			}else{
				if(in_array('qty', $ignoreData))
				$type = 'Cost Added, Qty Ignored';
				if(in_array('cost', $ignoreData))
				$type = 'Qty Added, Cost Ignored';
			}
			if(count($ignoreData)==2){
				$type = 'ignore';
			}			
		}
		if($qty==0.999999999)
		$qty = 0;
    	$vendorRankModel = Mage::getModel('logicbroker/ranking')->load($data['lb_vendor_code'],'lb_vendor_code');
    	$vendorName = $vendorRankModel->getLbVendorName();
    	 
    	$tableName = Mage::getSingleton("core/resource")->getTableName('logicbroker/inventorylog');
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
    	$file = $path . DS . $name . '.csv';
    
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
    	return Mage::getModel('logicbroker/ranking')->load($vendorCode,'lb_vendor_code')->getLbVendorName();
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
    		$loggedIn = ftp_login($ftpcon,  $ftpUserName['value'],  $ftpPassword['value']);
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
    	if(Mage::helper('logicbroker')->isProcessRunning('bulk_assign')){
    		$message = 'Bulk product setup is currently running hence cannot run ftp import';
    		Mage::log($message, null, 'logicbroker_log_report.log');
    		return;
    	}
    	$ftpfileName = array();
    	$ftpRequestPram = array('ftp_site'=>array('value'=> $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_SITE)),'ftp_username'=>array('value'=> $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_USERNAME)),'ftp_password'=>array('value'=> Mage::helper('core')->decrypt($this->getConfigValue(self::XML_PATH_UPLOAD_FTP_PASSWORD))),'ftp_type'=>array('value'=> $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_TYPE)));
		
    	if (! Mage::getStoreConfigFlag ( self::XML_PATH_UPLOAD_ENABLED )) {
    		return $this;
    	}
    	 
    	$connectionResult = $this->testFtpConnection($ftpRequestPram,true);  	 
    	if($connectionResult['error']){
    		$this->genrateLogEntry(array('ftp_error'=>'Connection error','ftp_error_desc'=>$connectionResult['message'],'error'=> 1));
    		Mage::log($connectionResult['message'], null, 'logicbroker_ftp_vendor_inventory_import.log');
    		ftp_close($connectionResult['object']);
    		return $this;
    	}
    	 
    	$rankCollection = Mage::getModel('logicbroker/ranking')->getCollection()->addFieldToFilter('is_dropship','yes');
    	/* file path format <ftp site>/<Logicbroker Account Number>_MagVendID<number>/Inventory/ */
    	 
    	if($rankCollection->getSize() > 0){
    		foreach($rankCollection as $ranks){
    			$path = $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_ACCNUMBER).'_'.$ranks->getLbVendorCode().'/'.'Inventory';
    
    			$ftpFiles = ftp_nlist($connectionResult['object'],$path);
    
    			if(is_array($ftpFiles)){
    				foreach($ftpFiles as $file){
    					if(!preg_match("/\bArchive\b/i", $file, $match)){
    					if($this->downloadFtpFile($connectionResult['object'],$file,$path, $ranks->getLbVendorCode()))
    						$ftpfileName[$ranks->getLbVendorCode()][] = self::getWorkingDir().str_replace("\\","/",$path).DS.$this->downloadFtpFile($connectionResult['object'],$file,$path);
    				}
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
		$nondropshipCollection = Mage::getModel('logicbroker/ranking')->getCollection()->addFieldToFilter('is_dropship','no');
		if($nondropshipCollection->getSize() > 0){
    		foreach($nondropshipCollection as $nondropship){
    			$path = $this->getConfigValue(self::XML_PATH_UPLOAD_FTP_ACCNUMBER).'_'.$nondropship->getLbVendorCode().'/'.'Inventory';
    			$ftpFiles = ftp_nlist($connectionResult['object'],$path);
    			if(is_array($ftpFiles)){
    				foreach($ftpFiles as $file){
    					if(!preg_match("/\bArchive\b/i", $file, $match)){
							$this->archiveFtpFile(array('object'=>$connectionResult['object'],'path'=>self::getWorkingDir().str_replace("\\","/",$path).DS.$this->downloadFtpFile($connectionResult['object'],$file,$path)));
							$this->genrateLogEntry(array('lb_vendor_code'=>$nondropship->getLbVendorCode(),'ftp_error'=>'Import Error','ftp_error_desc'=>$nondropship->getLbVendorCode().' is not a dropship supplier','error'=> 1)); 
						}
    				}
    			}
    			ftp_chdir($connectionResult['object'],'/');
    		}    		
    	}
    	if(!empty($ftpfileName)){
    		$this->initialize();
    		foreach($ftpfileName as $vendorCode=>$fileinfo)
    		{
    			foreach($fileinfo as $path){
    				if($this->validateCsvHeader($this->_getCsvData($path),true)){
						$this->ftpUpdateVendorProduct($this->_getCsvData($path),$path,$vendorCode);	
    					
    				}else{
    					$logPath = explode('logicbrokervendorproduct',str_replace("\\","/",$path));
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
    	return $this;
    
    }
    
    protected function initialize(){
    	Mage::helper('logicbroker')->startProcess('manual_upload');
    	Mage::log('Ftp upload started', null, 'logicbroker_ftp_vendor_inventory_import.log');
    }
    
    protected function finalize(){
    	Mage::helper('logicbroker')->finishProcess('manual_upload');
    	Mage::log('Ftp upload finished', null, 'logicbroker_ftp_vendor_inventory_import.log');
    }
    
    protected function archiveFtpFile($object)
	{
    	$path = str_replace("\\","/",$object['path']);
    	$patharr = explode('logicbrokervendorproduct',$path);
    	$dirname = pathinfo($patharr[1],PATHINFO_DIRNAME);
    	$basename = pathinfo($patharr[1],PATHINFO_BASENAME );
		$newname = Mage::getModel('core/date')->date('Y-m-d h:i:s').'_'.$basename;
    	ftp_chdir($object['object'],$dirname);
    	ftp_mkdir($object['object'], 'Archive');
		ftp_chdir($object['object'],'Archive');
    	ftp_put($object['object'], $basename, $path, FTP_ASCII);
    	ftp_rename ($object['object'],$basename,$newname);
		ftp_chdir($object['object'],'/');
		$this->fileObj()->rm($object['path']);
		ftp_delete($object['object'], $dirname.'/'.$basename);
    	return;
    	
    }
    protected function validateFtpFile($file, $vendorCode = null)
	{
    	$file = str_replace("/","\\",$file);
    	$extension = pathinfo($file, PATHINFO_EXTENSION);    
    	if (strtolower($extension) != 'csv') {
    		$this->fileObj()->rm($file);
    		$logPath = explode('logicbrokervendorproduct',str_replace("\\","/",$file));
    		$this->genrateLogEntry(array('lb_vendor_code'=>$vendorCode, 'ftp_error'=>'Bad File', 'ftp_error_desc'=>'Disallowed file type '.$logPath[1], 'error'=> 1));
    		Mage::log('Disallowed file type.', null, 'logicbroker_ftp_vendor_inventory_import.log');
    
    		return false;
    	}
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
    
    	if(!$this->validateFtpFile(self::getWorkingDir().$path.DS.$fileName[2], $vendorCode)){
    		return false;
    	}
    	// download server file
    	if (ftp_get($ftpRequest, $local_file, $server_file, FTP_ASCII)){
    		 
    		return $fileName[2];
    	}else{	 
    		return false;
    	}
    }
    
    protected function ftpUpdateVendorProduct($csvData,$path,$vendorCode = null)
	{
    	$records = array();
    	$success = array();
    	$failure = array();
		$itemerroroutput = array();
    	//$vendorCode = '';
    	$tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/vendor_import_log' );
		 if(count($csvData) <= 1 )
    	{
            $failure[] = 'File is empty'; 
            $this->_FtpErrors['empty_file'] =  'File is empty';            
    	} 
    	foreach($csvData as $row => $csvRowData)
    	{
    		if($row == 0)
    			continue;
    		$data = trim($data);
    		//patch for backwards compatible for ftp change lbn-1070
			(count($csvRowData) <= 3) ? array_unshift($csvRowData, "") : $csvRowData;
			if(is_numeric($csvRowData[2]) || $csvRowData[2] > 0){
				//$qty = floor($csvRowData[2]);
                /* LBN - 935 change */
               $magento_sku = $this->getMagentoSku($vendorCode, trim($csvRowData[1]));
                            
                if(!empty($magento_sku))
                {                    
    		       $qty = Mage::helper('logicbroker')->getIsQtyDecimal($magento_sku,$csvRowData[2]);  
                }
                else
                {
                   $qty = $csvRowData[2];  
                }
                /* End of LBN - 935 change */
			}else{
				$qty = $csvRowData[2];
			}
    		$records[$row] = array('lb_vendor_code'=>$vendorCode,'vendor_sku'=>trim($csvRowData[1]),'qty'=>$qty ,'cost'=>$csvRowData[3]);
    		//$vendorCode = $csvRowData[0];
    	}
    
		$this->conn->beginTransaction ();
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
		if(isset($this->_FtpErrors['general_error'])){
    	$this->_FtpErrors['other'] = implode(' , ', $this->_FtpErrors['general_error']);
    	unset($this->_FtpErrors['general_error']);
    	}
		foreach($this->_FtpErrors as $k=>$output){
			if($k =='combination_error' && is_array($output)){
				foreach($output as $oput){
					$itemerroroutput[] = '<li>'.$oput.'</li>';
				}
			}else{			
				$itemerroroutput[] = '<li>'.$output.'</li>';
			}	
		}
    	array_unshift($itemerroroutput,'<ul>');
    	array_push($itemerroroutput,'</ul>');
    	
    	$ftp_err = (count($failure) > 0)  ? 'Missing/Bad Data' : '';
    	$insert = 'INSERT INTO '.$tableVendorImportLog.'(lb_vendor_code,updated_by,success,failure,ftp_error,ftp_error_desc,created_at) VALUES ("'.$vendorCode.'","FTP",'.count($success).','.count($failure).',"'.$ftp_err.'","'.implode('',$itemerroroutput).'","'.now().'")';
    	 unset($itemerroroutput);
    	$this->conn->query($insert);
    	try {
    		$this->conn->commit ();
    	} catch ( Exception $e ) {
    		$this->conn->rollBack ();
    		Mage::log($e->getMessage(), null, 'logicbroker_ftp_vendor_inventory_import.log');
    		echo $e->getMessage();
    		 
    	}
    }
    
    protected function ftpVendorProductUpdate($data)
	{	 
    	$tableVendorInventory = Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/inventory' );
    	$inventoryCollectionResult = $this->getInventoryCollection($data,true);
    	$qtyArray = $this->calculateProductQty(array('magento_sku'=>$inventoryCollectionResult['magento_sku'],'qty'=>$data['qty'],'lb_vendor_code'=>$data['lb_vendor_code'])); 	
    	switch($inventoryCollectionResult['operationType'])
    	{
    		case 'update':
    			$productId = Mage::getModel('catalog/product')->getIdBySku($inventoryCollectionResult['magento_sku']);
    			if($productId){
    				if(!is_numeric($data['cost']) || $data['cost'] < 0 || trim($data['cost']) =="")
    				{
    					$costUpdate = '';
    				}else
    				{
    					$costUpdate = ' cost ='. $data['cost'] . ',';
    				}
    				
    				if($qtyArray['upload_qty'] == .999999999 || trim($data['qty']) =="" )
    				{
    					$qtyUpdate = '';
    				}else
    				{
    					$qtyUpdate = ' stock = '.$qtyArray['upload_qty']. ',';
    				}
					if($costUpdate=='' && $qtyUpdate ==''){
						$timeUpdate = "";
					}else{
						$timeUpdate = ' updated_at = "'.now(). '"';
					}
    				$update = 'update '.$tableVendorInventory.' set '.$costUpdate. $qtyUpdate.$timeUpdate.' where id = '.$inventoryCollectionResult['vendor_id'];
    					
    				try {
    					if(!$this->updateProductInventory($inventoryCollectionResult['magento_sku'],$qtyArray['final_qty']))
    					{
    						$this->_FtpErrors['general_error'][] = 'Update error: Error in updating magento product inventory';
    						return false;
    					}
						if($update)
    					$this->conn->query($update);
    					return true;
    				} catch ( Exception $e ) {
    					Mage::log($e->getMessage(), null, 'logicbroker_ftp_vendor_inventory_import.log');
    					echo $e->getMessage();
    				}
    				 
    			}else{
    				$this->_FtpErrors['general_error'][] = 'Update error: '.$data['lb_vendor_code'].' and '.$data['vendor_sku'] .' combination does not exist';
    				return false;
    			}
    			break;
    		default :
		/*fix for ticket lbn-710 vendor_sku not visible*/
    			$this->_FtpErrors['general_error'][] = $data['lb_vendor_code'].' and '.$data['vendor_sku'] .' combination does not exist';
    			return false;
    	}	 
    	return true;
    }
    
    protected function genrateLogEntry($message)
    {
    	$vendorCode = (!empty($message['lb_vendor_code'])) ? $message['lb_vendor_code'] : '';
    	$ftp_error = (!empty($message['ftp_error'])) ? $message['ftp_error'] : '';;
    	$ftp_error_desc = (!empty($message['ftp_error_desc'])) ? $message['ftp_error_desc'] : '';;
    	$tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/vendor_import_log' );
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
					$this->_UploadCsvErrors['combination_error'][] =  'Supplier code'. $data['lb_vendor_code'] .' and vendor sku '.$data['vendor_sku'].' combination not matching' ;
				}else{
					$this->_FtpErrors['combination_error'][] =  'Supplier code'. $data['lb_vendor_code'] .' and vendor sku '.$data['vendor_sku'].' combination not matching' ;
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
					 $this->_UploadCsvErrors['combination_error'][] =  'Vendor sku '.$data['vendor_sku'].' is already been assigned for this vendor'; 
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
				$this->_UploadCsvErrors['general_error'][] = 'Vendor Sku '. $data['vendor_sku'] .' & Supplier code '.$data['lb_vendor_code'].' combination does not exist';
			}else{
				if(trim($data['vendor_sku']))	
				$this->_FtpErrors['general_error'][]  = 'Vendor Sku '. $data['vendor_sku'] .' & Supplier code '.$data['lb_vendor_code'].' combination does not exist';
			}
		}
		if($data['magento_sku']){
			if($isFtp){
				$insertInventoryLog = $this->getInventoryLogQuery($data,'update',$data['qty'],'FTP', $ignoreData);
			}else{
				$insertInventoryLog = $this->getInventoryLogQuery($data, $inventoryCollectionResult['operationType'], $data['qty'], null,$ignoreData);
			}
			if($insertInventoryLog)
			$this->conn->query($insertInventoryLog);
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
						$this->_UploadCsvErrors['combination_error'][] = 'Vendor sku '.$data['vendor_sku'].' is duplicate in Magento Sku '. $data['magento_sku'] .' for this supplier'; 	
	  
					}else{
						$invalidData = true;
						$this->_UploadCsvErrors['combination_error'][] =  'Vendor sku '.$data['vendor_sku'].' & Magento Sku '. $data['magento_sku'] .' combination already present'; 	
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
					if($insertInventoryLog)
					$this->conn->query($insertInventoryLog);
				}				
			}
		}else{
			$failure+=1;
			$this->_UploadCsvErrors['general_error'][] = 'Magento Sku '.$data['magento_sku'].' does not exist';	
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
}
	 