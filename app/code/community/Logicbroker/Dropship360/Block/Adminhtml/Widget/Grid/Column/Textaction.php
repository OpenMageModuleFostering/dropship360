<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Textaction extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{
  
	/**
     * Renders column
     *
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
    	
		$actions[] = array(
            'caption' => Mage::helper('sales')->__('Edit'),
			'url'     => $this->getUrl('*/*/edit', array('lb_item_id' => $row->getLbItemId(), 'qty'=> (int)$row->getQtyOrdered())))
        ;
        if ( empty($actions) || !is_array($actions) || !in_array($row->getLbItemStatus(),array('Backorder'))) {
            return '&nbsp;';
        }
		$out="";
        if(!$this->getColumn()->getNoLink()) {
            foreach ($actions as $action) {
                if ( is_array($action) ) {
                  $out .= "&nbsp;&nbsp;&nbsp;".$this->_toLinkHtml($action, $row);
                }
            }
        }
		return $out;
        
    }
}