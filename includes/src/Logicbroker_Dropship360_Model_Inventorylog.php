<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Inventorylog extends Mage_Core_Model_Abstract
{
	protected $_message = array(
			'row_magento_sku'=>'Missing Data at Row(s) for Magento Sku row_num',
			'row_vendor_sku'=>'Missing Data at Row(s) for Vendor Sku row_num',
			'row_qty'=>'Bad Data for Qty at Row(s) row_num',
			'row_cost'=>'Bad Data for Cost at Row(s) row_num',
			'empty_file'=>'Sorry,we cant find the record to update inventory',
			'inventory_update_error'=>'Error in updating magento product inventory, magento_sku',
			'magento_sku_exists'=>'magento product sku not exist magento_sku',
			'inventory_add_error'=>'Error in adding magento product inventory, magento_sku',
			'combination_notexist'=>'Vendor Sku vendor_sku & Supplier code vendor_code combination does not exist',
			'already_assigned'=>'Vendor sku vendor_sku is already been assigned for this vendor',
			'duplicate_vendor_sku'=>'Vendor sku vendor_sku is duplicate in Magento Sku magento_sku for this supplier',
			'combination_exist'=> 'Vendor sku vendor_sku or Supplier code vendor_code combination already present for Magento Sku magento_sku',
			'data_notchnage'=> 'Cost & Qty for Vendor Sku vendor_sku & Supplier code vendor_code not changed',
			'upc_notexist'=> 'UPC attribute missing vendor_sku',
			'upc_multiple'=> 'Multiple Match found for UPC vendor_sku',
			'manufacturer_part_number_notexist'=> 'MNP attribute missing vendor_sku',
			'manufacturer_part_number_multiple'=> 'Multiple Match found for MNP vendor_sku',
			'sku_multiple'=> 'Multiple Match found for SKU vendor_sku',
								'attribute_notexist'=> 'Attribute assigned for Supplier does not exist for Row(s) row_num',
								'ftp_bad_header'=>'Bad csv header for file file_name',
								'connection_error'=>'con_error'
	);
protected $_replace = array('magento_sku','vendor_sku','vendor_code','file_name','con_error');
    
    protected function _construct(){

       $this->_init("dropship360/inventorylog");
    }
    
    public function getMessageArray(){
    	return $this->_message;
    }
    public function getReplaceValue(){
    	return $this->_replace;
    }
    public function getLogDescriptionCollection($error_id){
    	$conn = Mage::getModel('dropship360/uploadvendor')->getDatabaseConnection();
    	$tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/vendor_import_log_desc' );
    	$select = $conn->select()->from($tableVendorImportLog)->where('error_id ='.$error_id);
    	$rows = $conn->fetchAll($select);
    	return $rows;
    }
    public function prepareRowData($data)
    {
    	$decodedata = array();
    	$dataTemp = array();
    	if(is_numeric($data))
    	{
    		$rowData = $this->getLogDescriptionCollection($data);
    		if(count($rowData) > 0)
    		{
    			foreach($rowData as $eachRow)
    			{
    				$decodedata[] = array_merge($dataTemp, $this->getDecodedJson($eachRow['description']));
    			}
    		}
    	}else
    	{
    		$decodedata = $this->getDecodedJson($data);
    	}
    	return $decodedata;
    }
    
    protected function getDecodedJson($data)
    {
    	if(empty($data) || !Mage::helper('dropship360')->isJson($data)){
    		return $data;
    	}
    	$data = trim($data,'"');
    	$data = trim($data,'\'');
    	$decodedata = Mage::helper('core')->jsonDecode($data);
    	return $decodedata;
    }
}
	 