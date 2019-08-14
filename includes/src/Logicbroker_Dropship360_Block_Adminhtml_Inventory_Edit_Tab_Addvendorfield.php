<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Block_Adminhtml_Inventory_Edit_Tab_Addvendorfield extends Mage_Adminhtml_Block_Widget
{
    protected $_product;

    protected $_productInstance;

    protected $_values;

    protected $_itemCount = 1;

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('logicbroker/addvendorfields.phtml');
    }

    public function getItemCount()
    {
        return $this->_itemCount;
    }

    public function setItemCount($itemCount)
    {
        $this->_itemCount = max($this->_itemCount, $itemCount);
        return $this;
    }

        /**
     * Retrieve options field name prefix
     *
     * @return string
     */
    public function getFieldName()
    {
        return 'product[vendor_new]';
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

    public function getVendorSelectHtml()
    {
    	$productsku = Mage::getModel('catalog/product')->load(Mage::app()->getRequest()->getParam('id'))->getSku();
    	
    	$select = $this->getLayout()->createBlock('adminhtml/html_select')
    	->setData(array(
    			'id' => $this->getFieldId().'_{{id}}_lb_vendor_code',
    			'class' => 'select select-product-option-type required-option-select'
    	))
    	->setName($this->getFieldName().'[{{id}}][lb_vendor_code]')
    	->setOptions(Mage::getSingleton('dropship360/system_config_source_vendorlist')->vendorList('',$productsku));
    	
    	return $select->getHtml();
    }
    protected function _prepareLayout()
    {
        $this->setChild('delete_button_vendor',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(array(
                    'label' => Mage::helper('dropship360')->__('Delete Option'),
                    'class' => 'delete delete-product-option-vendor '
                ))
        );
		return parent::_prepareLayout();
    }

    public function getAddButtonId()
    {
        $buttonId = $this->getLayout()
                ->getBlock('vendors_product_tab')
                ->getChild('vendor_add_button')->getId();
        return $buttonId;
    }

    public function getDeleteButtonHtml()
    {
        return $this->getChildHtml('delete_button_vendor');
    }

}