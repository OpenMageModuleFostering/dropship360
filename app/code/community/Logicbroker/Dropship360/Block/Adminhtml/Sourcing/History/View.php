<?php

/**
 * Adminhtml quotation view
 *
 * @category   Logicbroker
 * @package    Logicbroker_Dropship360
 *
 */
class Logicbroker_Dropship360_Block_Adminhtml_Sourcing_History_View extends Mage_Adminhtml_Block_Widget_Form_Container
{

    public function __construct()
    {
        $this->_objectId    = 'lb_item_id';
		$this->_blockGroup  = 'dropship360';
        $this->_controller  = 'adminhtml_sourcing_history';
        $this->_mode        = 'view';
        parent::__construct();
		$data = array(
			'label' =>  'Back',
			'onclick'   => 'setLocation(\'' . $this->getUrl('*/*/sourcinggrid') . '\')',
			'class'     =>  'back'
		);
		$this->addButton ('custom_back', $data, 0, 100,  'header'); 
		$this->_removeButton('back');
        $this->_removeButton('reset');
        $this->_removeButton('save');
		$this->_removeButton('delete');
        $this->setId('adminhtml_sourcing_history_view');
    }

	/**
     * Get Header title
	 *
     * @return string
     */	
    public function getHeaderText()
    {
		$itemId = $this->getRequest()->getParam('lb_item_id');
		$orderItems = Mage::getModel('dropship360/orderitems')->load($itemId, 'item_id');
		$orderId    = Mage::getModel('sales/order')->load($orderItems->getItemOrderId())->getIncrementId();
		$createdDate =  Mage::helper('core')->formatDate($orderItems->getCreatedAt(), 'medium', true);
        return Mage::helper('sales')->__('Item Sku %s | Order # %s | %s', $orderItems->getSku(), $orderId, $createdDate);
    }
	
}
