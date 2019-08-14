<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Block_Adminhtml_Sourcing_Edit_Tab_Form extends Mage_Adminhtml_Block_Widget_Form
{
    
  protected function _prepareForm()
  {
      $form = new Varien_Data_Form();
      $sku = Mage::registry('sourcing_data')->getData('sku');
      $this->setForm($form);
      $fieldset = $form->addFieldset('logicbroker_form', array('legend'=>Mage::helper('logicbroker')->__('Item Sourcing Information')));
        //$data = $form->setValues(Mage::registry('logicbroker_data')->getData());
      
    if(in_array(Mage::registry('sourcing_data')->getData('lb_item_status'),array('Sourcing','Backorder','Transmitting','No Dropship'))){	     
      $fieldset->addField('lb_vendor_code', 'select', array(
      		'label'     => Mage::helper('logicbroker')->__('Vendor'),
      		'class'     => 'required-entry validate-select',
      		'required'  => true,
      		'name'      => 'lb_vendor_code',
      		'values'    => Mage::getModel('logicbroker/system_config_source_vendorlist')->vendorListSourcing(true,$sku),
      		'default' => '',
      		'note' => 'Select your drop ship vendor to source this item and bypass the dropship360 sourcing rule.'
      ));
	}
     $fieldset->addField('lb_item_status', 'text', array(
     		'label'     => Mage::helper('logicbroker')->__('Logicbroker Item Status'),
     		'name'      => 'lb_item_status',
     		'note'=>'Read only filed',
     		'readonly'=> true
     ));
       
      
     $fieldset->addField('sku', 'text', array(
          'label'     => Mage::helper('logicbroker')->__('Sku'),
          'name'      => 'sku',
     		'note'=>'Read only filed',
     		'readonly'=> true
      )); 
     $fieldset->addField('item_order_id', 'hidden', array(
     		//'label'     => Mage::helper('logicbroker')->__('Sku'),
     		'name'      => 'item_order_id',
     		//'note'=>'Read only filed',
     		//'readonly'=> true
     ));
     
     $fieldset->addField('item_id', 'hidden', array(
     		//'label'     => Mage::helper('logicbroker')->__('Sku'),
     		'name'      => 'item_id',
     		//'note'=>'Read only filed',
     		//'readonly'=> true
     ));
	
      if ( Mage::getSingleton('adminhtml/session')->getSourcingData() )
      {
          $form->setValues(Mage::getSingleton('adminhtml/session')->getSourcingData());
          Mage::getSingleton('adminhtml/session')->setSourcingData(null);
      } elseif ( Mage::registry('sourcing_data') ) {
          $form->setValues(Mage::registry('sourcing_data')->getData());
      }
      return parent::_prepareForm();
  }
}