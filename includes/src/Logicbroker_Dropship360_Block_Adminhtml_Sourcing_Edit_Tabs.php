<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
 
class Logicbroker_Dropship360_Block_Adminhtml_Sourcing_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{


  public function __construct()
  {
      parent::__construct();
      $this->setId('logicbroker_tabs');
      $this->setDestElementId('edit_form');
     $this->setTitle(Mage::helper('dropship360')->__('Item Sourcing Information'));
  }

  protected function _beforeToHtml()
  {
      $this->addTab('form_section', array(
          'label'     => Mage::helper('dropship360')->__('Item Sourcing Information'),
          'title'     => Mage::helper('dropship360')->__('Item Sourcing Information'),
          'content'   => $this->getLayout()->createBlock('dropship360/adminhtml_sourcing_edit_tab_form')->toHtml(),
      ));
     
      return parent::_beforeToHtml();
  }
}