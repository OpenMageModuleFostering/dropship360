<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
 
class Logicbroker_Dropship360_Block_Adminhtml_Sourcing_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        
                 
        $this->_objectId = 'lb_item_id';
        $this->_blockGroup = 'logicbroker';
        $this->_controller = 'adminhtml_sourcing';
        parent::__construct();
        
        $this->_updateButton('save', 'label', Mage::helper('logicbroker')->__('Save Sourcing'));
       	$this->_updateButton('delete', 'label', Mage::helper('logicbroker')->__('Delete Vendor'));
		
        $this->_addButton('saveandcontinue', array(
            'label'     => Mage::helper('adminhtml')->__('Save And Continue Edit'),
            'onclick'   => 'saveAndContinueEdit()',
            'class'     => 'save',
        ), -100);
        if(Mage::registry('sourcing_data')->getData('updated_by') != 'logicbroker'){
        
        	$this->_addButton('cancelitem', array(
        			'label'     => Mage::helper('adminhtml')->__('Cancel Item'),
        			'onclick'   => 'cancelItem()',
        			'class'     => 'delete',
        	), -100);
        }
        $this->_removeButton('delete');
        $this->_removeButton('reset');
        $this->_formScripts[] = "
                
            function saveAndContinueEdit(){
                editForm.submit($('edit_form').action+'back/edit/');
            }
            function cancelItem(){
        		$('lb_vendor_code').className = '';
                editForm.submit($('edit_form').action+'cancel/item/');
            }    
            ";
        
        
    }

    public function getBackUrl()
    {
    	return $this->getUrl('*/*/sourcinggrid');
    }
    
    public function getHeaderText()
    {
        if( Mage::registry('sourcing_data') && Mage::registry('sourcing_data')->getLbItemId() ) {
            return Mage::helper('logicbroker')->__("Edit Item Sourcing Vendor", $this->htmlEscape(Mage::registry('sourcing_data')->getTitle()));
        } else {
            return Mage::helper('logicbroker')->__('Select Sourcing Vendor');
        }
    }

}