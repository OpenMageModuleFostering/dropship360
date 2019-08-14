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
	const XML_PATH_LOGICSOURCING_SOURCING_TYPE  = 'logicbroker_sourcing/rank/sourcing_type';
	protected $_orderStatus;
	protected $_itemData = array();
    protected function _construct(){
       $this->_init("dropship360/ordersourcing");
    }
    /**
     * Prepare order collection from logicbroker_sales_order_items 
     * @param $crontype (Reprocess,sourcing,backorder)
     * @param string $isCronSourcing
     * @return collection
     */
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
    /**
     * unsed
     * @param unknown $condition
     * @return multitype:NULL
     */
	protected function getOrderForProcess($condition){
    	$orderIds = array();
    	$processingItem = Mage::getModel('dropship360/orderitems')->getCollection();
    	$processingItem->getSelect()->join(array('salesOrder'=>Mage::getSingleton('core/resource')->getTableName('sales/order')),'salesOrder.entity_id = main_table.item_order_id',array('state'));
    	$processingItem->addFieldToFilter('state','processing');
    	$processingItem->addFieldToFilter('lb_item_status',array('in'=>$condition));
    	$processingItem->getSelect()->group('item_order_id');
    	$processingItem->getSelect()->order('id asc');
    	if($processingItem->getSize() > 0){
    	foreach ($processingItem as $item){
    			$orderIds[] = $item->getItemOrderId();
    		}
    	}
    	return $orderIds;
    }
    
    /**
     * check souring cron running status 
     * @param cron_type $type
     * @return boolean
     */
    public function checkRunningStatus($type)
    {
    	$path = ($type == Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER) ? $this->_pathBackorderStart : $this->_pathSourcingStart;
    	if(!Mage::getStoreConfigFlag($path)){
    		return false;//job not running
    	}
    	/*$now = time() - ($this->_waitTIme * 60);
    	$time = strtotime(Mage::getStoreConfig($path));
    	if ($time < $now) {
    		return false;//insert forcefully 
    	}*/
    	return true;
    }
    /**
     * set date time value in core_config_data when cron start
     * @param cron_type $type
     */
    public function sourcingStarted($type){
		$path = ($type == Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER) ? $this->_pathBackorderStart : $this->_pathSourcingStart;
    	$value = strftime('%Y-%m-%d %H:%M:00', time());
    	Mage::getResourceModel('dropship360/ordersourcing')->saveConfig($path, $value);
    }
    
    /**
     * update datetime value in core_config_data when sourcing done
     * @param unknown $type
     */
    public function sourcingCompleted($type){
    	//$path = ($type == 'backorder') ? $this->_pathBackorderComp : $this->_pathSourcingComp;
    	$path = ($type == Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER) ? $this->_pathBackorderStart : $this->_pathSourcingStart;
    	$value = '';//strftime('%Y-%m-%d %H:%M:00', time());
    	Mage::getResourceModel('dropship360/ordersourcing')->saveConfig($path, $value);
    }
    
    /**
     * save data in logicbroker_sales_order_item when new order placed using event
     * @param sales_order_item $item
     * @param event $object
     */
    public function getOrderSourcing($item, $object){
    	return $this->_getOrderSourcing($item, $object);
    }
    protected function _getOrderSourcing($item, $object){
    	$orderSourcingInstance = Mage::getModel ( 'dropship360/orderitems' );
    	Mage::getModel('dropship360/logicbroker')->prepareNotification($orderSourcingInstance,$object->getOrder()->getEntityId());
    	$orderStatus = $object->getOrder()->getStatus();
    	$itemStatusHistory = Mage::helper('dropship360')->getSerialisedData($orderSourcingInstance, 'Sourcing', $orderStatus);
    	$orderSourcingInstance->setSku ( $item->getSku() );
    	$orderSourcingInstance->setItemId ( $item->getItemId() );
    	$orderSourcingInstance->setItemOrderId ( $object->getOrder()->getEntityId() );
    	$orderSourcingInstance->setLbItemStatus ('Sourcing');
    	$orderSourcingInstance->setUpdatedBy ('Cron');
    	$orderSourcingInstance->setUpdatedAt(now());
    	$orderSourcingInstance->setItemStatusHistory($itemStatusHistory);
    	try {
    		$orderSourcingInstance->save ();
    	} catch ( Execption $e ) {
    		Mage::helper('dropship360')->genrateLog(0,null,null,'Section : order item inserted Error: '.$e->getMessage().' sku : '.$item->getSku());
    		echo $e->getMessage();
    	}
    
    	//As item get saved in logicbroker_sales_orders_items we run our sourcing logic
    	if(Mage::getStoreConfigFlag(self::XML_PATH_LOGICSOURCING_SOURCING_TYPE)){
    		$this->assignToVendor($item);
    		Mage::getResourceModel('dropship360/orderitems')->saveOrderItems($this->_itemData,$object->getOrder());
    		$this->_itemData = array();
    	}
    }
    
    /**
     * process item and save order item ranking in logicbroker_sales_order_items
     * @param cron_type $crontype
     * @param string $isCronSourcing
     */
    public function setLbVendorRanking($crontype,$isCronSourcing = false){
    	$this->_setLbVendorRanking($crontype,$isCronSourcing);
    }

    protected function _setLbVendorRanking($crontype,$isCronSourcing)
    {
    	$reprocess = Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS;
    	$lbOrderInstances = Mage::getModel('dropship360/ordersourcing');
    	$collection = $lbOrderInstances->prepareItemCollection($crontype,$isCronSourcing);
    	try{
    		if(count($collection) > 0 ){
    			foreach ( $collection as $orderID => $orderCollectionData ) {
    				$orderCollection = Mage::getModel('sales/order')->Load($orderID);
    				//Patch : skip sourcing process if order is deleted
    				if (! $orderCollection->getEntityId ()) {
    					Mage::helper ( 'dropship360' )->genrateLog ( 0, null, null, 'Order not exists for => order_id: ' . $orderID . ' hence cannot continue' );
    					continue;
    				}
    				$this->_orderStatus = $orderCollection->getStatus();
    				foreach ($orderCollectionData as $orderData ){
    					Mage::helper('dropship360')->genrateLog(0,null,null,'<---->Item Processing Started : Order id '.$orderID.'##'.$orderData->getSku());
    					if ($crontype == Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS) {
    						$assigned = $this->assignToVendor(Mage::getModel('sales/order_item')->Load($orderData->getItemId()));
    					}else
    					{
    						$orderItems = Mage::getModel( 'dropship360/orderitems' )->load($orderData->getItemId(), 'item_id');
    						$itemStatusHistory = Mage::helper('dropship360')->getSerialisedData($orderItems, $reprocess, $this->_orderStatus);
    						$this->_itemData[$orderData->getItemId()] = array('lb_item_status'=>$reprocess,'item_status_history'=>$itemStatusHistory);
    					}
    					Mage::helper('dropship360')->genrateLog(0,null,null,'####### Item Processing ended : Order id '.$orderID.'##'.$orderData->getSku());
    						
    				}
    				Mage::getResourceModel('dropship360/orderitems')->saveOrderItems($this->_itemData,$orderCollection,$crontype);
    				$this->_itemData = array();
    			}
    		}else {
    			Mage::helper('dropship360')->genrateLog(0,null,null,'Order collection is empty for => Cron_type: '.$crontype.' hence cannot continue');
    			return;
    		}
    	}catch (Exception $e){
    		Mage::helper('dropship360')->genrateLog(null,null,null,$e->getMessage());
    		Mage::helper('dropship360')->genrateLog(null,null,null,$e->getTraceAsString());
    		return;
    	}
    }
    
    /**
     * assign vendor for item on the basis of ranking and configuration
     * @param sa;es_order_item $item
     * @return string
     */
    protected function assignToVendor($item) {
    	$productSku = $item->getSku ();
    	$itemStatusComplete = Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_TRANSMITTING;
    	$itemStatusBackorder = Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER;
    	$itemStatusNoDropShip = Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_NO_DROPSHIP;
    	$qtyInvoiced = $item->getQtyOrdered ();
    	$itemId = $item->getItemId ();
    	$isDefaultVendor = false;
    	$vendorCode = '';
    	$inventoryStock = '';
    	$defaultVendor = (Mage::getStoreConfig('logicbroker_sourcing/rank/defaultbackorder') == 'none') ? '' : Mage::getStoreConfig('logicbroker_sourcing/rank/defaultbackorder');
    	$orderItemInstance = Mage::getModel ( 'dropship360/orderitems' );
    	$orderItemInstance->load ( $itemId, 'item_id' );
    	$collectionVendor = $orderItemInstance->prepareOrderItemData($item);
    	$arrDefaultVendorDetails = array();
    	$vendorCost = 0;
    
    	if ($collectionVendor->count () > 0) {
    		if($collectionVendor->count () >= 1){
    			foreach ($collectionVendor as $vendorData) {
    				//assign default vendor details
    				if(!empty($defaultVendor) && $vendorData->getLbVendorCode() == $defaultVendor )
    				{
    					$arrDefaultVendorDetails =  array('lb_vendor_code'=>$vendorData->getLbVendorCode(),'stock'=>$vendorData->getStock(),'cost'=>$vendorData->getCost(),'lb_vendor_sku'=>$vendorData->getLbVendorSku(),'product_sku'=>$vendorData->getProductSku());
    				}
    				//if item is in backordered
    				if($vendorData->getStock() < $qtyInvoiced ){
    					$arrVendorDetail[] = array('lb_vendor_code'=>$vendorData->getLbVendorCode(),'stock'=>$vendorData->getStock(),'cost'=>$vendorData->getCost(),'lb_vendor_sku'=>$vendorData->getLbVendorSku(),'product_sku'=>$vendorData->getProductSku());
    					$vendorCode = $arrVendorDetail[0]['lb_vendor_code'];
    					$inventoryStock = $arrVendorDetail[0]['stock'];
    					$vendorCost = $arrVendorDetail[0]['cost'];
    					$vendorSku = $arrVendorDetail[0]['lb_vendor_sku'];
    					$productSku = $arrVendorDetail[0]['product_sku'];
    					$arrVendorAvailable[] = $vendorData->getLbVendorCode();
    					$isDefaultVendor = true;
    				}else{
    					$vendorCode = $vendorData->getLbVendorCode();
    					$inventoryStock = $vendorData->getStock();
    					$vendorCost = $vendorData->getCost();
    					$vendorSku = $vendorData->getLbVendorSku();
    					$productSku = $vendorData->getProductSku();
    					$arrVendorAvailable[] = $vendorData->getLbVendorCode();
    					$isDefaultVendor = false;
    					break;
    				}
    			}
    			$arrVendorAvailable[] = $vendorData->getLbVendorCode();
    		}else {
    			$arrFirstVendor = $collectionVendor->getFirstItem ()->getData ();
    			$vendorCode = $arrFirstVendor ['lb_vendor_code'];
    			$inventoryStock = $arrFirstVendor ['stock'];
    			$vendorCost = $arrFirstVendor ['cost'];
    			$vendorSku = $arrFirstVendor ['lb_vendor_sku'];
    			$productSku = $arrFirstVendor ['product_sku'];
    			if($inventoryStock < $qtyInvoiced)
    				$isDefaultVendor = true;
    			else
    				$isDefaultVendor = false;
    
    			$arrVendorAvailable[] = $arrFirstVendor ['lb_vendor_code'];
    		}
    	}
    
    	if(!empty($vendorCode)){
    		if ($vendorCode && $inventoryStock >= $qtyInvoiced) {
    			$itemStatusHistory = Mage::helper('dropship360')->getSerialisedData($orderItemInstance, $itemStatusComplete, $this->_orderStatus);
    			Mage::helper('dropship360')->genrateLog(0,null,null,'@@@@@@@@ Sourcing Details==> stock('.$inventoryStock.') >= qtyinvoiced('.$qtyInvoiced.'),vendor_code ->'.$vendorCode.', item-status->'.$itemStatusComplete);
    			Mage::getModel('dropship360/logicbroker')->setupNotification();
    			$this->_itemData [$item->getItemId ()] = array (
    					'updateInventory' => true,
    					'qtyInvoiced' =>$qtyInvoiced,
    					'updated_at' => now (),
    					'sku' => $item->getSku (),
    					'updated_by' => 'Cron',
    					'lb_item_status' => $itemStatusComplete,
    					'lb_vendor_code' => $vendorCode,
    					'vendor_cost' => $vendorCost * $qtyInvoiced,
    					'lb_vendor_sku' => $vendorSku,
    					'item_status_history' => $itemStatusHistory
    			);
    			return $itemStatusComplete;
    		}
    		if ($isDefaultVendor && $inventoryStock <= $qtyInvoiced && !empty($defaultVendor) && in_array($defaultVendor,$arrVendorAvailable)) {
    			$itemStatusHistory = Mage::helper('dropship360')->getSerialisedData($orderItemInstance, $itemStatusComplete, $this->_orderStatus);
    			Mage::helper('dropship360')->genrateLog(0,null,null,'@@@@@@@@ Sourcing Details Default vendor set ==>stock('.$inventoryStock.') >= qtyinvoiced('.$qtyInvoiced.'),vendor_code ->'.$vendorCode.', item-status->Transmitting');
    			$this->_itemData [$item->getItemId ()] = array (
    					'updateInventory' => false,
    					'updated_at' => now (),
    					'sku' => $item->getSku (),
    					'updated_by' => 'Cron',
    					'lb_item_status' => $itemStatusComplete,
    					'lb_vendor_code' => $defaultVendor,
    					'vendor_cost' => $arrDefaultVendorDetails ['cost'] * $qtyInvoiced,
    					'lb_vendor_sku' => $arrDefaultVendorDetails ['lb_vendor_sku'],
    					'item_status_history' => $itemStatusHistory
    			);
    			return $itemStatusComplete;
    		}
    		if ($vendorCode && $inventoryStock <= $qtyInvoiced) {
    			$itemStatusHistory =Mage::helper('dropship360')->getSerialisedData($orderItemInstance, $itemStatusBackorder, $this->_orderStatus);
    			Mage::helper('dropship360')->genrateLog(0,null,null,'@@@@@@@@ Sourcing Details==>stock('.$inventoryStock.') <= qtyinvoiced('.$qtyInvoiced.'),vendor_code ->'.$vendorCode.', item-status->'.$itemStatusBackorder);
    			$this->_itemData [$item->getItemId ()] = array (
    					'updateInventory' => false,
    					'updated_at' => now (),
    					'sku' => $item->getSku (),
    					'updated_by' => 'Cron',
    					'lb_item_status' => $itemStatusBackorder,
    					'lb_vendor_code' => $vendorCode,
    					'vendor_cost' => $vendorCost * $qtyInvoiced,
    					'lb_vendor_sku' => $vendorSku,
    					'item_status_history' => $itemStatusHistory
    			);
    			return $itemStatusBackorder;
    		}
    	}else{
    		$itemStatusHistory = Mage::helper('dropship360')->getSerialisedData($orderItemInstance,$itemStatusNoDropShip, $this->_orderStatus);
    		Mage::helper('dropship360')->genrateLog(0,null,null,'@@@@@@@@ Sourcing Details==> No vendor Set ,vendor_code ->'.$vendorCode.', item-status->No Dropship');
    		$this->_itemData [$item->getItemId ()] = array (
    				'updateInventory' => false,
    				'updated_at' => now (),
    				'sku' => $item->getSku (),
    				'updated_by' => 'Cron',
    				'lb_item_status' => $itemStatusNoDropShip,
    				'lb_vendor_code' => $vendorCode,
    				'vendor_cost' => $vendorCost * $qtyInvoiced,
    				'lb_vendor_sku' => '',
    				'item_status_history' => $itemStatusHistory
    		);
    		return $itemStatusNoDropShip;
    	}
    }
    
}	 