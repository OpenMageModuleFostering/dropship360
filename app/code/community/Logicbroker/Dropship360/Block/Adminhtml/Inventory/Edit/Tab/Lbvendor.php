<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Block_Adminhtml_Inventory_Edit_Tab_Lbvendor extends Mage_Adminhtml_Block_Widget
{
    public function __construct()
    {
        parent::__construct();
       // $this->setTemplate('catalog/product/edit/options.phtml');
    }


    protected function _prepareLayout()
    {
        $this->setChild('vendor_add_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(array(
                    'label' => Mage::helper('logicbroker')->__('Add New vendor'),
                    'class' => 'add',
                    'id'    => 'add_new_defined_option_vendor'
                ))
        );
        
        $this->setChild('update_delete_button_vendor',
        		$this->getLayout()->createBlock('adminhtml/widget_button')
        		->setData(array(
        				'label' => Mage::helper('logicbroker')->__('Delete Option'),
        				'class' => 'delete delete-product-option-vendor '
        		))
        );

        $this->setChild('vendor_options_box',
            $this->getLayout()->createBlock('logicbroker/adminhtml_inventory_edit_tab_addvendorfield')
        );

        return parent::_prepareLayout();
    }

    public function getAddButtonHtml()
    {
    	return $this->getChildHtml('vendor_add_button');
    }

    public function getOptionsBoxHtml()
    {
        return $this->getChildHtml('vendor_options_box');
    }
    
    public function getAssignedData(){
    	
    	$rankingTableName = Mage::getSingleton('core/resource')->getTableName('logicbroker/ranking');
        $collection = Mage::getModel('logicbroker/inventory')->getCollection()
    	->addFieldToFilter('product_sku',Mage::getModel('catalog/product')->load($this->getProductId())->getSku());
    	$collection->getSelect()->joinLeft(array('ranking' => $rankingTableName), 'main_table.lb_vendor_code=ranking.lb_vendor_code', array('vendor_name' => 'lb_vendor_name'));
        return $collection;
    }
    
    public function getFieldName()
    {
    	return 'product[vendor_update]';
    }
    
    /**
     * Retrieve options field id prefix
     *
     * @return string
     */
    public function getFieldId()
    {
    	return 'product_vendor';
    }
    
    public function getUpdateDeleteButtonHtml(){
    	
    	return $this->getChildHtml('update_delete_button_vendor');
    }
    
}