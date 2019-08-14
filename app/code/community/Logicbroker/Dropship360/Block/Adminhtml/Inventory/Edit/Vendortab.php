<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
 
class Logicbroker_Dropship360_Block_Adminhtml_Inventory_Edit_Vendortab extends Mage_Adminhtml_Block_Widget
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    public function canShowTab()
    {
        return true;
    }
    public function getTabLabel()
    {
        return $this->__('Vendor');
    }
    public function getTabTitle()
    {
        return $this->__('lbvendor');
    }
    public function isHidden()
    {
        return false;
    }
    public function getTabUrl()
    {
        return $this->getUrl('logicbroker/adminhtml_inventory/vendors', array('_current' => true));
    }
    public function getTabClass()
    {
        return 'ajax';
    }
} 