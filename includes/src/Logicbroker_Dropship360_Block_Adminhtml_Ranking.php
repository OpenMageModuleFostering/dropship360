<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Ranking extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_ranking';
    $this->_blockGroup = 'dropship360';
    $this->_headerText = Mage::helper('dropship360')->__('Supplier Management');
    $this->_addButtonLabel = Mage::helper('dropship360')->__('Add Supplier Ranking');
   
    
    $this->addButton('show_history',array(
    		'label'     => 'Show History',
    		'onclick'   => 'setLocation(\'' . $this->getUrl('*/*/showhistory') .'\')',
    		'class'     => 'save',
    ));
     $this->addButton('save_ranking_table',array(
    		'label'     => 'save ranking table',
    		'onclick'   => 'saveRankingTable()',
    		'class'     => 'save',
    )); 
    
    $this->addButton('save_ranking',array(
    		'label'     => 'Save Ranking',
    		'class'     => 'save',
    		'onclick'   => 'saveRankingTable()',
    		
    ));
     parent::__construct();
     $this->setTemplate('logicbroker/vendor_ranking.phtml');
   	$this->removeButton('add');
   	$this->removeButton('save_ranking_table');
    
  }
  public function getVendorCollection($type = 'no'){

  	$arrVendor = array();
  	$tempReslt = Mage::getModel('dropship360/ranking')->getVendorCollection($type);
  	$result['gridData'] = Mage::helper('core')->jsonEncode($tempReslt);
  	if(!empty($tempReslt)){
  	foreach($tempReslt as $value){
  		$arrVendor[] = array('name'=>$value['name'],'code'=>$value['code']);
  	}
  	}
  	$result['arrayData'] = Mage::helper('core')->jsonEncode($arrVendor);
  	return $result;
  }
  public function getAttributeCode()
  {
	$helper = Mage::helper('dropship360');
	$attributeCode = array(array('link'=>'','name'=>$helper::LOGICBROKER_PRODUCT_LINK_NONE),array('link'=>$helper::LOGICBROKER_PRODUCT_LINK_CODE_UPC,'name'=>$helper::LOGICBROKER_PRODUCT_LINK_UPC),array('link'=>$helper::LOGICBROKER_PRODUCT_LINK_CODE_MNP,'name'=>$helper::LOGICBROKER_PRODUCT_LINK_MNP),array('link'=>$helper::LOGICBROKER_PRODUCT_LINK_CODE_SKU,'name'=>$helper::LOGICBROKER_PRODUCT_LINK_SKU));
	return Mage::helper('core')->jsonEncode($attributeCode);
  }

}
