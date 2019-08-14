<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Model_Observer {
	
	const CRON_STRING_PATH_SOURCING = 'crontab/jobs/logicbroker_dropship360/schedule/cron_expr';
	const CRON_STRING_PATH_BACKORDER = 'crontab/jobs/logicbroker_backorder/schedule/cron_expr';
	const XML_PATH_SCHEDULE_GENERATE_SOURCING_MIN  = 'logicbroker_cron/cron_settings/schedule_sourcing_min';
	const XML_PATH_SCHEDULE_GENERATE_BACKORDER_MIN  = 'logicbroker_cron/cron_settings/schedule_backorder_min';
	const XML_PATH_LOGICBROKER_CRON_SOURCING_HRS   = 'logicbroker_cron/cron_settings/schedule_sourcing_hour';
	const XML_PATH_LOGICBROKER_CRON_BACKORDER_HRS   = 'logicbroker_cron/cron_settings/schedule_backorder_hour';
	const XML_PATH_LOGICBROKER_CRON_SOURCING_UPDATED_TIME   = 'logicbroker_cron/cron_settings/sourcing_updated_time';
	const XML_PATH_LOGICBROKER_CRON_BACKORDER_UPDATED_TIME   = 'logicbroker_cron/cron_settings/backorder_updated_time';
	const XML_PATH_LOGICBROKER_CRON_SOURCING_STATUS   = 'logicbroker_cron/cron_settings/sourcing_status';
	const XML_PATH_LOGICBROKER_CRON_BACKORDER_STATUS   = 'logicbroker_cron/cron_settings/backorder_status';
	const XML_PATH_LOGICBROKER_ORDER_BEGIN_SOURCING_STATUS   = 'logicbroker_sourcing/order/begin_sourcing';
	const XML_PATH_LOGICBROKER_ORDER_AWAITING_TRANSMISSION   = 'logicbroker_sourcing/order/awaiting_transmission';
	const XML_PATH_LOGICBROKER_ORDER_BACKORDERED   = 'logicbroker_sourcing/order/backorder';
	const XML_PATH_LOGICBROKER_ORDER_SOURCING_COMPLETE   = 'logicbroker_sourcing/order/sourcing_complete';
	const STATUS_RUNNING = 'running';
	const STATUS_SUCCESS = 'success';
	protected $_orderStatus;
	
	public static function getWorkingDir()
	{
		return Mage::getBaseDir();
	}
	
public function tarPatch()
	{
		$prefix =  self::getWorkingDir();
		$filePath = $prefix.'/app/design/adminhtml/default/logicbroker/template/downloadable/sales/order/view/items/renderer/downloadable.phtml/';
		$sourceCopy = $prefix.'/app/design/adminhtml/default/logicbroker/template/downloadable/sales/order/view/downloadable.phtml';
		$destinationCopy = $prefix.'/app/design/adminhtml/default/logicbroker/template/downloadable/sales/order/view/items/renderer/downloadable.phtml';		
		try{
		if(is_dir($filePath)){
		$delete = @rmdir($filePath);
		$copy =  @rename($sourceCopy,$destinationCopy);
		}
		}catch(Exception $e)
		{
			return;
		}
		
		return;
		
	}
	
	
	public function insertProcessOrder($object) {
		
// 		$orderSourcingInstance = Mage::getModel ( 'logicbroker/ordersourcing' );
// 		$orderSourcingInstance->setOrderId ( $object->getOrder ()->getEntityId () );
		if($object->getOrder ()->getStatus() == Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BEGIN_SOURCING_STATUS))
		{
		foreach ($object->getOrder ()->getAllItems() as $item){
			if(in_array($item->getProductType(),array('simple','grouped')) ){
					$this->getOrderSourcing($item, $object);
			}
		}
		}
		
	}
	protected function getOrderSourcing($item, $object){
		$orderSourcingInstance = Mage::getModel ( 'logicbroker/orderitems' );
		$orderSourcingInstance->setSku ( $item->getSku() );
		$orderSourcingInstance->setItemId ( $item->getItemId() );
		$orderSourcingInstance->setItemOrderId ( $object->getOrder()->getEntityId() );
		$orderSourcingInstance->setLbItemStatus ('Sourcing');
		$orderSourcingInstance->setUpdatedBy ('Cron');
		$orderSourcingInstance->setUpdatedAt(now());		
		try {
			$orderSourcingInstance->save ();
		} catch ( Execption $e ) {
			echo $e->getMessage();
		}
	}

	public function logicbrokerSourcing() {
		if(!Mage::getStoreConfig(self::CRON_STRING_PATH_SOURCING)) {
			return;
		}
		$this->setLbVendorRanking (Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BEGIN_SOURCING_STATUS));
		$this->addCronStatus('logicbroker_cron/cron_settings/dispaly_sourcing_updated_time', Mage::helper('core')->formatDate(now(), 'medium', true));
		return;
		 
	}
	
	public function logicbrokerBackorder(){
	 
		if(!Mage::getStoreConfig(self::CRON_STRING_PATH_BACKORDER)) {
            return;
        }
		$this->setLbVendorRanking (Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BACKORDERED),true);
		$this->addCronStatus('logicbroker_cron/cron_settings/display_backorder_updated_time', Mage::helper('core')->formatDate(now(), 'medium', true));
		return;
		
	}
	

	/* Check allowed to run cron job
	* @param string $jobCode
	* @param time $cronLastRun
	* @param string $cronStatus
	* return bool
	*/
	protected function checkCronJobStatus($jobCode, $cronLastRun, $cronStatus) {
		$schedule = Mage::getModel('cron/schedule')->load($jobCode, 'job_code');
		if($jobCode=='Logicbroker_Dropship360'){
			$min = Mage::getStoreConfig(self::XML_PATH_SCHEDULE_GENERATE_SOURCING_MIN);
			$hrs = Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_CRON_SOURCING_HRS);
			$scheduleFor = $min + ($hrs*60);
		}elseif($jobCode=='logicbroker_backorder'){
			$min = Mage::getStoreConfig(self::XML_PATH_SCHEDULE_GENERATE_BACKORDER_MIN);
			$hrs = Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_CRON_BACKORDER_HRS);
			$scheduleFor = $min + ($hrs*60);
		}
			
		$now = time();
		$timePassed = ($now - $cronLastRun)/60;
		if( $timePassed >= $scheduleFor) {		
			if ($schedule->getId() != null) {
				if($cronStatus == self::STATUS_RUNNING){
					if($timePassed >=30 && $cronStatus == self::STATUS_RUNNING){
						return true;
					}
					return false;				
				}
			}
			return true;
		}else if($timePassed >=30 && $cronStatus == self::STATUS_RUNNING){
			return true;
		}
	}
	
	/* Save last cron executed time
	* @param string $time
	*/
	protected function addCronCreatedTime($time) {
		$config = new Mage_Core_Model_Config();
		$config->saveConfig($time, time(), 'default', 0);
		return;
	}
	
	/* Save last cron status
	* @param string $statusPath
	* @param string $status
	*/
	protected function addCronStatus($statusPath, $status) {
		$config = new Mage_Core_Model_Config();
		$config->saveConfig($statusPath, $status, 'default', 0);
		return;
	}
	
	protected function setLbVendorRanking($crontype,$isBackorderedCron = false) {
		$arrStatus = array();
		$lbOrderInstances = Mage::getModel('logicbroker/ordersourcing');
		
		//$lbOrderItemsInstances = Mage::getModel('logicbroker/orderitems')->isVendorCollectionAvailable();
		$collection = $lbOrderInstances->prepareOrderCollection($crontype);
		//$collection[] = array('order_id'=>197);
		
		if($collection->count() > 0 ){
		foreach ( $collection as $orderData ) {
			//Mage::log('Crontype-:'.$crontype .'->'.$orderData->getEntityId() , null, 'mylogfile.log');
		$orderCollection = Mage::getModel('sales/order')->Load($orderData->getEntityId());
		$itemCollection = $orderCollection->getAllItems();
		$itemComplete = false;
		$itemBackorder = false;
		$itemNodropship = false;
		$allItemStatus=array();
		$this->_orderStatus = $orderCollection->getStatus();
		foreach($itemCollection as $item)
		{
			if(in_array($item->getProductType(),array('simple','grouped'))){
	
			$assigned = $this->assignToVendor($item);
			
			switch ($assigned) {
				  
				case Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_AWAITING_TRANSMISSION) :
					$itemComplete = true;
					break;
				  
				  case Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BACKORDERED) :
				  	$itemBackorder = true;
				    break;
				  
				  case Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_SOURCING_COMPLETE) :
				  	$itemNodropship = true;
				  	break;
				  
				} 
			 	
			}
		}
		
		//echo $itemComplete. '&&' .$itemBackorder. '&&' .$itemNodropship;
		//echo '<br>';
		//echo $isBackorderedCron. '&&' .$this->_orderStatus. '=='. Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BACKORDERED). '&&' .$itemBackorder;
		//die;
		/*
		 * PATCH : In backorder cron process if any item-status -> "backorder" and order-status -> "backorder"
		 *		  than set new order-status -> Begin Sourcing,order-state -> processing and item-status -> "sourcing"
		 */ 	  
		if($isBackorderedCron && $this->_orderStatus == Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BACKORDERED)){
			
			$orderCollection->setStatus(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BEGIN_SOURCING_STATUS));       //done
			//$orderCollection->setState('processing');
			$orderCollection->addStatusToHistory(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BEGIN_SOURCING_STATUS), 'Order status changed to begin sourcing', false);
			
		}else{
		
			if($itemComplete && !$itemBackorder && !$itemNodropship){
				
		                 $orderCollection->setStatus(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_AWAITING_TRANSMISSION));       //done
		                 //$orderCollection->setState('processing');
		                 $orderCollection->addStatusToHistory(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_AWAITING_TRANSMISSION), 'Order status changed to awaiting transmission', false);
		        
		                  }elseif (!$itemComplete && !$itemBackorder && $itemNodropship){
		        
		                  		//$orderCollection->setState('processing');
		                        $orderCollection->setStatus(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_SOURCING_COMPLETE)); //re
		                        $orderCollection->addStatusToHistory(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_SOURCING_COMPLETE), 'Order status changed to sourcing complete', false);
		        
		                  }elseif (!$itemComplete && $itemBackorder && $itemNodropship){
		                  		//$orderCollection->setState('processing');
		                        $orderCollection->setStatus(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BACKORDERED)); //re
		                        $orderCollection->addStatusToHistory(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BACKORDERED), 'Order status changed to backorder', false);
		        
		                  }elseif (!$itemComplete && $itemBackorder && !$itemNodropship){
		                  		//$orderCollection->setState('processing');
		                        $orderCollection->setStatus(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BACKORDERED)); //re
		                        $orderCollection->addStatusToHistory(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BACKORDERED), 'Order status changed to backorder', false);
		        
		                  }else{
		                  	//$orderCollection->setState('processing');
		                  	$orderCollection->setStatus(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_AWAITING_TRANSMISSION)); //re
		                  	$orderCollection->addStatusToHistory(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_AWAITING_TRANSMISSION), 'Order status changed to awaiting transmission', false);
		                  }
		}
		 $orderCollection->save();
		 unset($orderCollection);                 
		}
		}else {
			return;
		}
	}
	protected function assignToVendor($item) {
		$productSku = $item->getSku ();
		$orderStatusComplete = Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_AWAITING_TRANSMISSION);
		$orderStatusBackorder = Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_BACKORDERED);
		$orderStatusNoDropShip = Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_ORDER_SOURCING_COMPLETE);
		$qtyInvoiced = $item->getQtyOrdered ();
		$itemId = $item->getItemId ();
		$isDefaultVendor = false;
		$vendorCode = '';
		$inventoryStock = '';
		$defaultVendor = (Mage::getStoreConfig('logicbroker_sourcing/rank/defaultbackorder') == 'none') ? '' : Mage::getStoreConfig('logicbroker_sourcing/rank/defaultbackorder');
		$orderItemInstance = Mage::getModel ( 'logicbroker/orderitems' );
		$orderItemInstance->load ( $itemId, 'item_id' );
		$collectionVendor = $orderItemInstance->prepareOrderItemData($item);
		$arrDefaultVendorDetails = array();
		
		if ($collectionVendor->count () > 0) {
			
			if($collectionVendor->count () > 1){
			
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
						$inventoryStock = $arrVendorDetail[0]['stock'];//$vendorData->getStock();
						$vendorCost = $arrVendorDetail[0]['cost'];//$vendorData->getCost();
						$vendorSku = $arrVendorDetail[0]['lb_vendor_sku'];//$vendorData->getLbVendorSku();
						$productSku = $arrVendorDetail[0]['product_sku'];//$vendorData->getProductSku();
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
			
			if($orderItemInstance->getLbVendorCode() == $vendorCode  && !in_array($orderItemInstance->getLbItemStatus(),array('Sourcing','Backorder')))
			{
				return $orderStatusComplete;
			}
			
			$itemStatus = ($this->_orderStatus ==  $orderStatusBackorder) ? 'Sourcing' : 'Transmitting';
			$orderItemInstance->setItemData($orderItemInstance,$itemStatus,$item,$vendorCode,$vendorCost*$qtyInvoiced,$vendorSku);
			if($this->_orderStatus !=  $orderStatusBackorder)
				$orderItemInstance->updateLbVendorInvenory ( $vendorCode, $productSku,$qtyInvoiced );
			return $orderStatusComplete;
		}
		if ($isDefaultVendor && $inventoryStock <= $qtyInvoiced && !empty($defaultVendor) && in_array($defaultVendor,$arrVendorAvailable)) {
			
			
			$orderItemInstance->setItemData($orderItemInstance,'Transmitting',$item,$defaultVendor,$arrDefaultVendorDetails['cost']*$qtyInvoiced,$arrDefaultVendorDetails['lb_vendor_sku']);
			//$orderItemInstance->updateLbVendorInvenory ( $defaultVendor, $qtyInvoiced );
			return $orderStatusComplete;
		
		}
		if ($vendorCode && $inventoryStock <= $qtyInvoiced) {
			
			$itemStatus = ($this->_orderStatus ==  $orderStatusBackorder) ? 'Sourcing' : 'Backorder';
			$orderItemInstance->setItemData($orderItemInstance,$itemStatus,$item,$vendorCode,$vendorCost*$qtyInvoiced,$vendorSku);
			return $orderStatusBackorder;
			
		}
		
		}else{
			
			$orderItemInstance->setItemData($orderItemInstance,'No Dropship',$item,$vendorCode,$vendorCost*$qtyInvoiced,$vendorSku);
			return $orderStatusNoDropShip;
			
		}
		
	}
	
	 /**
     * Flag to stop observer executing more than once
     *
     * @var static bool
     */
    //static protected $_singletonFlag = false;
 
    /**
     * This method will run when the product is saved from the Magento Admin
     * Use this function to update the product model, process the 
     * data or anything you like
     *
     * @param Varien_Event_Observer $observer
     */
	
	public function saveProductTabData(Varien_Event_Observer $observer)
   	{
       // if (!self::$_singletonFlag) {
          //  self::$_singletonFlag = true;
             
            $product = $observer->getEvent()->getProduct();
         if(!empty($product['vendor_update']) || !empty($product['vendor_new'])){
            try {
                /**
                 * Perform any actions you want here
                 *
                 */
                $customFieldValue =  $this->_getRequest()->getPost('product');
                $result = Mage::getModel('logicbroker/inventory')->saveTabVendorData($customFieldValue);
                
                /**
                 * Uncomment the line below to save the product
                 *
                 */
                //if(!$result)
                	//Mage::getSingleton('adminhtml/session')->addError(Mage::helper('logicbroker')->__('Vendor Data Cannot be saved'));
                
                //$product->save();
                
                
                $this->_inventoryUpdate($result,$customFieldValue['sku']);
            }
            catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
         }
         return;
       // }
   	}
   	
   	protected function _inventoryUpdate($result,$sku)
   	{
   		if(!$result['result'])
   		{
   			return;
   		}
   			
   		$finalStock = $result['inventory'];
        $finalStock = floor($finalStock); // LBN - 918 change
   		$conn = Mage::getSingleton ( 'core/resource' )->getConnection ( 'core_write' );
   		 
   		$tableNameStatus = Mage::getSingleton ( 'core/resource' )->getTableName ( 'cataloginventory/stock_status' );
   		$tableNameItem = Mage::getSingleton ( 'core/resource' )->getTableName ( 'cataloginventory/stock_item' );
   		$tableNameItemIdx = Mage::getSingleton ( 'core/resource' )->getTableName ( 'cataloginventory/stock_status_indexer_idx' );
   		 
   		$stockStatus = $finalStock ? 1 : 0;
   		$productId = Mage::getModel('catalog/product')->getIdBySku($sku);
   		if($productId){ 
   		$updateStatus = 'update '.$tableNameStatus.' SET qty = '.$finalStock.',stock_status = '.$stockStatus.' where product_id = '.$productId;
   		$updateItem = 'update '.$tableNameItem.' SET qty = '.$finalStock.',is_in_stock = '.$stockStatus.' where product_id = '.$productId;
   		$updateItemIdx =  'update '.$tableNameItemIdx.' SET qty = '.$finalStock.',stock_status = '.$stockStatus.' where product_id = '.$productId;
   		$conn->beginTransaction ();
   		$conn->query ($updateStatus);
   		$conn->query ($updateItem);
   		$conn->query ($updateItemIdx);
   		 
   		try {
   			$conn->commit ();
   		} catch ( Exception $e ) {
   			$conn->rollBack ();
   			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
   		}
   		 
   		}
   		 
   	}
      
    /**
     * Retrieve the product model
     *
     * @return Mage_Catalog_Model_Product $product
     */
    public function getProduct()
    {
        return Mage::registry('product');
    }
     
    /**
     * Shortcut to getRequest
     *
     */
    protected function _getRequest()
    {
        return Mage::app()->getRequest();
    }
    
    public function preDispatch(Varien_Event_Observer $observer)
    {
    
    return;
    }
    
    protected function _isValidForShipmentEmail($shipment)
    {
        $trackingNumbers = array();
        foreach ($shipment->getAllTracks() as $track) {
            $trackingNumbers[] = $track->getNumber();
        };
        // send shipment email only when carrier tracking info is added
        if (count($trackingNumbers) > 0) {
            $lastValueOfArray = end($trackingNumbers);
            $lastValueOfArray = trim($lastValueOfArray);    
                if(!empty($lastValueOfArray))
                    return true;
                else
                    return false;
        } else {
            return false;
        }
    }
     
    public function salesOrderShipmentSaveBefore(Varien_Event_Observer $observer)
    {
        
        if (Mage::registry('salesOrderShipmentSaveBeforeTriggered')) {
            return $this;
        }
 
        /* @var $shipment Mage_Sales_Model_Order_Shipment */
        $shipment = $observer->getEvent()->getShipment();
        if ($shipment) {
            if ($this->_isValidForShipmentEmail($shipment)) {
                $shipment->setEmailSent(true);
                Mage::register('salesOrderShipmentSaveBeforeTriggered', true);
            }
        }
        return $this;
    }
     
    public function salesOrderShipmentSaveAfter(Varien_Event_Observer $observer)
    {
        if (Mage::registry('salesOrderShipmentSaveAfterTriggered')) {
            return $this;
        }
        
        /* @var $shipment Mage_Sales_Model_Order_Shipment */
        $shipment = $observer->getEvent()->getShipment();
        if ($shipment) {
            if ($this->_isValidForShipmentEmail($shipment)) {
                $shipment->sendEmail();
                Mage::register('salesOrderShipmentSaveAfterTriggered', true);
            }
        }
        return $this;
    }
}
