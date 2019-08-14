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
     $this->setTitle(Mage::helper('logicbroker')->__('Item Sourcing Information'));
  }

  protected function _beforeToHtml()
  {
      $this->addTab('form_section', array(
          'label'     => Mage::helper('logicbroker')->__('Item Sourcing Information'),
          'title'     => Mage::helper('logicbroker')->__('Item Sourcing Information'),
          'content'   => $this->getLayout()->createBlock('logicbroker/adminhtml_sourcing_edit_tab_form')->toHtml(),
      ));
     
      return parent::_beforeToHtml();
  }
}