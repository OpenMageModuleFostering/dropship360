<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Ordersourcing extends Mage_Core_Model_Abstract
{
    protected function _construct(){

       $this->_init("logicbroker/ordersourcing");
    }
	
    public function prepareOrderCollection($crontype)
    {
    	$processingOrders = Mage::getModel('sales/order')->getCollection()
    	//->addFieldToFilter('state', Mage_Sales_Model_Order::STATE_PROCESSING)
    	->addFieldToFilter('status',$crontype);
    	//->addAttributeToSelect('created_at');
    	$processingOrders->getSelect()->limit('20');
    	//echo $processingOrders->getSelect();
    	//$total = $processingOrders->count();
    	//echo '<br>'.$total;
    	
    	/* foreach ($processingOrders as $orders){
    	
    		foreach($orders->getAllItems() as $item ){
    			if(!in_array($item->getProductType(),array('virtual','downloadable'))){
    	
    				$orderId[] = array('order_id'=>$orders->getEntityId ());
    	
    			}
    		}
    	} */
    	//$collection = $this->getCollection();
    	//$collection->getSelect()->join( array('salesOrder'=>Mage::getSingleton('core/resource')->getTableName('sales/order')),
    		//	'main_table.order_id = salesOrder.entity_id', array('entity_id'))->where('main_table.sourcing in (?) and salesOrder.status = "'.$orderStatus.'"', array($crontype));
    	return $processingOrders; 	 
    }
	
}
	 