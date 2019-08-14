<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Ordersourcing extends Mage_Core_Model_Abstract
{
	protected $_pathSourcingStart = 'logicbroker/sourcing_cron/start_time';
	protected $_pathSourcingComp = 'logicbroker/sourcing_cron/comp_time';
	protected $_pathBackorderStart = 'logicbroker/backorder_cron/start_time';
	protected $_pathBackorderComp = 'logicbroker/backorder_cron/comp_time';
	protected $_waitTIme = 30; //min
    protected function _construct(){
       $this->_init("dropship360/ordersourcing");
    }
	public function prepareItemCollection($crontype,$isCronSourcing = false){
    	$orderItemColletion = array();
    	$rowObj = new Varien_Object();
    	$condition = ($isCronSourcing) ? array($crontype,Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_SOURCING) : array($crontype);
    	//$orders = $this->getOrderForProcess($condition);
    	$processingItem = Mage::getModel('dropship360/orderitems')->getCollection();
    	$processingItem->getSelect()->join(array('salesOrder'=>Mage::getSingleton('core/resource')->getTableName('sales/order')),'salesOrder.entity_id = main_table.item_order_id',array('state'));
    	$processingItem->addFieldToFilter('state','processing');
    	$processingItem->addFieldToFilter('lb_item_status',array('in'=>$condition));
   		 if($processingItem->getSize() > 0){
    	foreach ($processingItem as $item){
    		$var = array (
						'id' => $item->getId (),
						'item_id' => $item->getItemId (),
						'item_order_id' => $item->getItemOrderId (),
						'sku' => $item->getSku (),
						'lb_vendor_sku' => $item->getLbVendorSku (),
						'vendor_cost' => $item->getVendorCost (),
						'lb_item_status' => $item->getLbItemStatus (),
						'lb_vendor_code' => $item->getLbVendorCode (),
						'updated_by' => $item->getUpdatedBy (),
						'item_status_history' => $item->getItemStatusHistory (),
						'updated_at' => $item->getUpdatedAt () 
				);
    		$rowObj = new Varien_Object();
    		$orderItemColletion[$item->getItemOrderId()][] = $rowObj->setData($var);
    		}
    	}
    	return $orderItemColletion;
    }
	protected function getOrderForProcess($condition){
    	$orderIds = array();
    	$processingItem = Mage::getModel('dropship360/orderitems')->getCollection();
    	$processingItem->getSelect()->join(array('salesOrder'=>Mage::getSingleton('core/resource')->getTableName('sales/order')),'salesOrder.entity_id = main_table.item_order_id',array('state'));
    	$processingItem->addFieldToFilter('state','processing');
    	$processingItem->addFieldToFilter('lb_item_status',array('in'=>$condition));
    	$processingItem->getSelect()->group('item_order_id');
    	//$processingItem->getSelect()->limit('200');
    	$processingItem->getSelect()->order('id asc');
    	if($processingItem->getSize() > 0){
    	foreach ($processingItem as $item){
    			$orderIds[] = $item->getItemOrderId();
    		}
    	}
    	return $orderIds;
    }
    public function checkRunningStatus($type)
    {
    	$path = ($type == Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER) ? $this->_pathBackorderStart : $this->_pathSourcingStart;
    	if(!Mage::getStoreConfigFlag($path)){
    		return false;//job not running
    	}
    	$now = time() - ($this->_waitTIme * 60);
    	$time = strtotime(Mage::getStoreConfig($path));
    	/*if ($time < $now) {
    		return false;//insert forcefully 
    	}*/
    	return true;
    }
    public function sourcingStarted($type){
		$path = ($type == Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER) ? $this->_pathBackorderStart : $this->_pathSourcingStart;
    	$value = strftime('%Y-%m-%d %H:%M:00', time());
    	Mage::getResourceModel('dropship360/ordersourcing')->saveConfig($path, $value);
    }
    public function sourcingCompleted($type){
    	//$path = ($type == 'backorder') ? $this->_pathBackorderComp : $this->_pathSourcingComp;
    	$path = ($type == Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER) ? $this->_pathBackorderStart : $this->_pathSourcingStart;
    	$value = '';//strftime('%Y-%m-%d %H:%M:00', time());
    	Mage::getResourceModel('dropship360/ordersourcing')->saveConfig($path, $value);
    }
}	 