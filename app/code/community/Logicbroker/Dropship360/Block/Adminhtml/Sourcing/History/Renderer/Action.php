<?php
/**
 * Adminhtml sourcing grid block action item renderer
 *
 * @category    Logicbroker
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Sourcing_History_Renderer_Action
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{
    /**
     * Render grid row
     *
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $actions[] = array(
            'caption' => Mage::helper('sales')->__('View Order'),
			'url'     => $this->getUrl('adminhtml/sales_order/view', array('order_id' => $row->getItemOrderId())));
		 $actions[] = array(
            'caption' => Mage::helper('sales')->__('View History'),
			'url'     => $this->getUrl('*/*/viewOrderItemHistory', array('lb_item_id' => $row->getItemId())))
        ;

        $this->getColumn()->setActions($actions);
        return parent::render($row);
    }
}	