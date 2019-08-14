<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Vendorproductuploadhistory extends Mage_Adminhtml_Block_Widget_Grid_Container
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
								'lb_upc_notexist'=> 'UPC attribute missing vendor_sku',
								'lb_upc_multiple'=> 'Multiple Match found for UPC vendor_sku',
								'lb_mnp_notexist'=> 'MNP attribute missing vendor_sku',
								'lb_mnp_multiple'=> 'Multiple Match found for MNP vendor_sku',
								'sku_multiple'=> 'Multiple Match found for SKU vendor_sku',
								'attribute_notexist'=> 'Attribute assigned for Supplier code vendor_code does not exist for Vendor sku vendor_sku'
							);
protected $_replace = array('magento_sku','vendor_sku','vendor_code');
  public function __construct()
  {
  	$backButtonUrl =  Mage::helper('core/http')->getHttpReferer() ? Mage::helper('core/http')->getHttpReferer()  : Mage::getUrl('*/*/');
  	$this->_controller = 'adminhtml_ranking';
    $this->_blockGroup = 'dropship360';
    $this->_headerText = Mage::helper('dropship360')->__('Supplier Product Upload Log');
    $this->__addBackButton = Mage::helper('dropship360')->__('Back');
    $this->addButton('back',array(
            'label'     => 'Back',
            'onclick'   => 'setLocation(\'' . $backButtonUrl .'\')',
            'class'     => 'back',
        )
    );
    
    parent::__construct();
    $this->removeButton('add');
    
  }
 
  public function getMessageArray(){
  	return $this->_message;
  }
  
  public function getReplaceValue(){
  	return $this->_replace;
  }
  public function getLogCollection(){
  	
  	$conn = Mage::getModel('dropship360/uploadvendor')->getDatabaseConnection();
  	$tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/vendor_import_log' );  	
  	$select = $conn->select()->from($tableVendorImportLog)->order('created_at DESC'); 	
  	$stmt = $conn->query($select);
  	$rows = $stmt->fetchAll();
  	return $rows;
  }
  
  public function getLogDescriptionCollection($error_id){
  	$conn = Mage::getModel('dropship360/uploadvendor')->getDatabaseConnection();
  	$tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/vendor_import_log_desc' );
  	$select = $conn->select()->from($tableVendorImportLog)->where('error_id ='.$error_id);
  	$stmt = $conn->query($select);
  	$rows = $stmt->fetchAll();
  	return $rows;
  }
    
  public function parseDescription($data,$vendorCode){
	
  	$decodedata = $this->prepareRowData($data);
  	if(!is_array($decodedata) || empty($decodedata))
  		return empty($decodedata) ? implode('',$decodedata) : $decodedata;
  	$htmlStart = '<ul>';
  	$htmlEnd = '</ul>';
  	foreach($decodedata as $data){
  		$msg = $this->_message[$data['error_type']];

  		if(is_array($data['value']) && !empty($data['value'])){
  			
  			$htmlStart .= $this->genrateHtml($data['value'],$msg,$vendorCode);
  		}else{
  			$htmlStart .= '<li>'.str_replace('row_num',$data['value'],$msg).'</li>';
  		}
  	}
  	return $htmlStart.$htmlEnd;
  }
  
  public function genrateHtml($value,$msg,$vendorCode){
  	
  	$string = $msg;
  	$value['vendor_code'] = $vendorCode;
  	foreach($this->_replace as $val){
  		
  		if(strstr($string,$val))
  			$string = str_replace($val,$value[$val],$string);
  	}
  	
  	return  '<li>'.$string.'</li>';
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
