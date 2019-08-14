<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Orderaction extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{
  
	/**
     * Renders column
     *
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
    	$incrementId = $row->getData($this->getColumn()->getIndex());
    	$order = Mage::getModel('sales/order')->load($incrementId, 'increment_id');
    	if ($order)
    		$out = '<a target="_blank" rel="external" href="'.Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/view', array('order_id' => $order->getId())).'">'.$incrementId.'</a>';
    	else
    		$out = $incrementId;
    	return $out;   
    }
}