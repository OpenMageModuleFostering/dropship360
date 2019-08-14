<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Vendorproductuploadhistory extends Mage_Core_Block_Template
{
	protected $_message;
	protected $_replace;
protected $_backButtonUrl;
protected $_headerText;
  public function __construct()
  {
  	$this->setBackButtonUrl();
  	$this->setMsgVariables();
    $this->_headerText = Mage::helper('dropship360')->__('Supplier Product Upload Log');
    parent::__construct();
    $this->setCollection($this->_initCollection());
  }
  protected function setMsgVariables(){
  	$this->_message = $this->getMessageArray(); 
  	$this->_replace = $this->getReplaceValue();
  }
 protected function setBackButtonUrl()
  {
  	  	$params = Mage::app()->getRequest()->getParams();
		if(array_key_exists("p",$params) ||array_key_exists("limit",$params)){
  	  		$this->_backButtonUrl = Mage::getSingleton('adminhtml/session')->getBackButtonUrl();
  	  	}else
  	  	{
  	  		$this->_backButtonUrl =  Mage::helper('core/http')->getHttpReferer() ? Mage::helper('core/http')->getHttpReferer()  : Mage::getUrl('*/*/');
	  		Mage::getSingleton('adminhtml/session')->setBackButtonUrl( $this->_backButtonUrl);		
  	  	}
 }
  
  public function getButtonsHtml()
  {
  	$childId = 'back_button';
  	
  	$data = array(
            'label'     => 'Back',
            'onclick'   => 'setLocation(\'' . $this->_backButtonUrl .'\')',
            'class'     => 'back',
        );
  	$out = '';
  	$block = $this->getLayout()->createBlock('adminhtml/widget_button');
    $this->setChild($childId, $block);
    $child = $this->getChild($childId);
    $child->setData($data);
     $out = $this->getChildHtml($childId);
     return $out;
  }


public function getHeaderHtml()
    {
        return '<h3 class="header-adminhtml-ranking">' . $this->_headerText . '</h3>';
    }
 
  public function getMessageArray(){
  	return Mage::getModel('dropship360/inventorylog')->getMessageArray();
  }
  
  public function getReplaceValue(){
  	return Mage::getModel('dropship360/inventorylog')->getReplaceValue();
  }
  public function _initCollection(){
  	
  	$conn = Mage::getModel('dropship360/uploadvendor')->getDatabaseConnection();
  	$tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/vendor_import_log' );  	
	$collection = new Varien_Data_Collection_Db($conn);
 	$collection->setConnection($conn);
 	$collection->getSelect()->from($tableVendorImportLog)->order('created_at DESC');
 	return $collection;
  }
  
  protected function pager(){
  		$pager = $this->getLayout()->createBlock('page/html_pager', 'custom.pager');
  		$pager->setTemplate('logicbroker/html/pager.phtml');
        $pager->setAvailableLimit(array(20=>20,50=>50,100=>100,200=>200));
        $pager->setCollection($this->getCollection());
       	//$pager->setData('area','frontend'); 
        $this->setChild('pager', $pager);
        $this->getCollection()->load();
        return $this;
  	
  }
  public function getLogDescriptionCollection($error_id){
  	
  	return Mage::getModel('dropship360/inventorylog')->getLogDescriptionCollection($error_id);
  }
  public function prepareRowData($data)
  {
  	return Mage::getModel('dropship360/inventorylog')->prepareRowData($data);
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
  
  protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->pager();
    }
  
public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }
}
