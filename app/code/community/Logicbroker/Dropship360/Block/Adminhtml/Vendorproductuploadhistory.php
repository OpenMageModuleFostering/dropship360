<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Vendorproductuploadhistory extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
  	$this->_controller = 'adminhtml_ranking';
    $this->_blockGroup = 'logicbroker';
    $this->_headerText = Mage::helper('logicbroker')->__('Vendor Product Upload Log');
    $this->__addBackButton = Mage::helper('logicbroker')->__('Back');
    $this->addButton('back',array(
            'label'     => 'Back',
            'onclick'   => 'setLocation(\'' . $this->getUrl('*/*/') .'\')',
            'class'     => 'back',
        )
    );
    parent::__construct();
    $this->removeButton('add');
    
  }
 
  public function getLogCollection(){
  	
  	$conn = Mage::getModel('logicbroker/uploadvendor')->getDatabaseConnection();
  	$tableVendorImportLog = Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/vendor_import_log' );
  	
  	$select = $conn->select()->from($tableVendorImportLog)->order('created_at DESC');
  	
  	$stmt = $conn->query($select);
  	$rows = $stmt->fetchAll();
  	
  	
  return $rows;
  }
  
}
