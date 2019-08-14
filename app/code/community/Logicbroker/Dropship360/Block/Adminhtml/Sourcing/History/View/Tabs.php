<?php
/**
 * Sourcing History view tabs
 *
 * @category   Logicbroker
 * @package    Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Sourcing_History_View_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{

  public function __construct()
  {
      parent::__construct();
      $this->setId('adminhtml_sourcing_history_view');
      $this->setDestElementId('history_view');
      $this->setTitle(Mage::helper('logicbroker')->__('Order Item History'));
   }

  protected function _beforeToHtml()
  {
      $this->addTab('form_section', array(
          'label'     => Mage::helper('logicbroker')->__('Order Item History'),
          'title'     => Mage::helper('logicbroker')->__('Order Item History'),
      ));
     
      return parent::_beforeToHtml();
  }
   

}