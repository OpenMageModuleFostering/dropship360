<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Inventory extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_inventory';
    $this->_blockGroup = 'dropship360';
    $this->_headerText = Mage::helper('dropship360')->__('');
    parent::__construct();
    $this->removeButton('add');
  }
}
