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
	const XML_PATH_LOGICBROKER_ORDER_BEGIN_SOURCING_STATUS   = 'Reprocess';
	const XML_PATH_LOGICBROKER_ORDER_BACKORDERED   = 'Backorder';
	const XML_PATH_LOGICBROKER_EMAIL_SHIPMENT   = 'logicbroker_sourcing/rank/email_shipment';
	const XML_PATH_INVENTORY_NOTIFICATION_EMAIL  = 'logicbroker_sourcing/inventory_notification/email';
	const XML_PATH_INVENTORY_NOTIFICATION_EMAIL_ENABLED  = 'logicbroker_sourcing/inventory_notification/enabled';
	const XML_PATH_INVENTORY_NOTIFICATION_DAYS  = 'logicbroker_sourcing/inventory_notification/days';
	const XML_PATH_LOGICSOURCING_SOURCING_TYPE  = 'logicbroker_sourcing/rank/sourcing_type';
	protected $_orderStatus;
	protected $_itemData = array();
	
	public static function getWorkingDir()
	{
		return Mage::getBaseDir();
	}
	
	
	public function insertProcessOrder($object)
	{
		
		$this->_orderStatus = $object->getOrder()->getStatus();
			foreach ($object->getOrder ()->getAllItems() as $item){
				if(in_array($item->getProductType(),array('simple','grouped')) ){
					$started = 0;
					$ended = 1;
					$logMsg = 'Item inserted @'.Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/orderitems' ). ' sku : '.$item->getSku().','.$object->getOrder()->getIncrementId();
					Mage::helper('dropship360')->genrateLog(++$started,'Order Item Inserted Started',null,$logMsg);
						$this->getOrderSourcing($item, $object);
					Mage::helper('dropship360')->genrateLog(++$ended,null,'Order Item Inserted Ended',null);
				}
			}
		}
	
	protected function getOrderSourcing($item, $object){
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

	public function logicbrokerSourcing() {
		$sourcingObj = Mage::getModel('dropship360/ordersourcing');
		if(!Mage::getStoreConfig(self::CRON_STRING_PATH_SOURCING)) {
			Mage::helper('dropship360')->genrateLog(0,'Sourcing started','Sourcing started','Sourcing can not be started as cron time not set');
			return;
		}
		if($sourcingObj->checkRunningStatus('sourcing')){
			
			Mage::helper('dropship360')->genrateLog(0,'Sourcing started','Sourcing started','Sourcing can not be started as process already running');
			return;
		}
		Mage::helper('dropship360')->genrateLog(1,'Sourcing Started for '.Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS.' Item status',null,null);
		$sourcingObj->sourcingStarted(Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_SOURCING);
		$this->setLbVendorRanking (Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS,true);
		$this->addCronStatus('logicbroker_sourcing/cron_settings/dispaly_sourcing_updated_time', Mage::helper('core')->formatDate(now(), 'medium', true));
		$sourcingObj->sourcingCompleted(Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_SOURCING);
		Mage::helper('dropship360')->genrateLog(2,null,'Sourcing Ended for ' .Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS.' Item status',null);
		return; 
	}
	
	public function logicbrokerBackorder()
	{
		$sourcingObj = Mage::getModel('dropship360/ordersourcing');
		if(!Mage::getStoreConfig(self::CRON_STRING_PATH_BACKORDER)) {
			Mage::helper('dropship360')->genrateLog(0,'Backorder sourcing started','Backorder sourcing ended','Backorder Sourcing can not be started as cron time not set');
			return;
        }
        if($sourcingObj->checkRunningStatus(Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER)){
        	Mage::helper('dropship360')->genrateLog(0,'Backorder sourcing started','Backorder sourcing ended','Backorder Sourcing can not be started process already running');
        	return;
        }
        Mage::helper('dropship360')->genrateLog(1,'Backorder Sourcing Started for '.Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER . ' item status',null,null);
        $sourcingObj->sourcingStarted(Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER);
		$this->setLbVendorRanking (Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER);
		$this->addCronStatus('logicbroker_sourcing/cron_settings/display_backorder_updated_time', Mage::helper('core')->formatDate(now(), 'medium', true));
		$sourcingObj->sourcingCompleted(Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER);
		Mage::helper('dropship360')->genrateLog(1,'Backorder Sourcing Ended for '.Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER .' item status',null,null);
		return;
	}
		
		
	/* 
	* Save last cron status
	* @param string $statusPath
	* @param string $status
	*/
	protected function addCronStatus($statusPath, $status) {
		$config = new Mage_Core_Model_Config();
		$config->saveConfig($statusPath, $status, 'default', 0);
		return;
	}
	
	protected function setLbVendorRanking($crontype,$isCronSourcing = false)
	{
		$reprocess = Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS;
		$lbOrderInstances = Mage::getModel('dropship360/ordersourcing');
		$collection = $lbOrderInstances->prepareItemCollection($crontype,$isCronSourcing);
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
			Mage::helper('dropship360')->genrateLog(0,null,null,'<---->Item Processing Started : '.$orderData->getSku());
			if ($crontype == Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS) {
				$assigned = $this->assignToVendor(Mage::getModel('sales/order_item')->Load($orderData->getItemId()));
			}else
			{	
				$orderItems = Mage::getModel( 'dropship360/orderitems' )->load($orderData->getItemId(), 'item_id');
				$itemStatusHistory = Mage::helper('dropship360')->getSerialisedData($orderItems, $reprocess, $this->_orderStatus);
				$this->_itemData[$orderData->getItemId()] = array('lb_item_status'=>$reprocess,'item_status_history'=>$itemStatusHistory);
			}
			Mage::helper('dropship360')->genrateLog(0,null,null,'####### Item Processing ended : '.$orderData->getSku());
			 			 	
			}	
			Mage::getResourceModel('dropship360/orderitems')->saveOrderItems($this->_itemData,$orderCollection,$crontype);
			$this->_itemData = array();
		}
		}else {
		Mage::helper('dropship360')->genrateLog(0,null,null,'Order collection is empty for => Cron_type: '.$crontype.' hence cannot continue');
			return;
		}
	}
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
        $product = $observer->getEvent()->getProduct();
        if(!empty($product['vendor_update']) || !empty($product['vendor_new'])){
            try {
                /**
                 * Perform any actions you want here
                 *
                 */
                $customFieldValue =  $this->_getRequest()->getPost('product');
                $result = Mage::getModel('dropship360/inventory')->saveTabVendorData($customFieldValue);
                
                /**
                 * Uncomment the line below to save the product
                 *
                 */
                //if(!$result)
                	//Mage::getSingleton('adminhtml/session')->addError(Mage::helper('dropship360')->__('Vendor Data Cannot be saved'));
                
                //$product->save();
                
                
                $this->_inventoryUpdate($result,$customFieldValue['sku']);
            }
            catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
         }
         return;
   	}
   	
   	protected function _inventoryUpdate($result,$sku)
   	{
   		if(!$result['result']){
   			return;
   		}
   			
   		$finalStock = $result['inventory'];
        $finalStock = Mage::helper('dropship360')->getIsQtyDecimal($sku, $finalStock);
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
		// send shipment email only when emai lshipment is enabled from module
		if(Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_EMAIL_SHIPMENT)){
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
    }
     
    public function salesOrderShipmentSaveBefore(Varien_Event_Observer $observer)
    {       
		if(!Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_EMAIL_SHIPMENT)){
			return $this;
		}
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
		if(!Mage::getStoreConfig(self::XML_PATH_LOGICBROKER_EMAIL_SHIPMENT)){
			return $this;
		}
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
	
	/*
	 * This function is used to delete the sku from vendor inventory 
	 *	when the same sku is deleted from catalog product
	 */
	public function catalogProductDeleteAfter(Varien_Event_Observer $observer)
	{
		$deletedProductSku = $observer->getEvent()->getProduct()->getSku();
		$orderItem = Mage::getModel ('dropship360/inventory')->getCollection()->addFieldToFilter('product_sku', $deletedProductSku);
		if($orderItem->getSize() > 0){
			foreach($orderItem as $data){
				try {
					Mage::getModel ('dropship360/inventory')->load($data->getId())->delete();
				} catch (Exception $e) {    
					Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
				}		
			}
		}
		return 	$this;		
	}
	//@function : notify cutomer for oudated product inventory through email,initiated by cron
	public function notifyForProductUpdateInventory(){
		if (!Mage::getStoreConfigFlag (self::XML_PATH_INVENTORY_NOTIFICATION_EMAIL_ENABLED) || !Mage::getStoreConfigFlag (self::XML_PATH_INVENTORY_NOTIFICATION_DAYS) || !Mage::getStoreConfigFlag (self::XML_PATH_INVENTORY_NOTIFICATION_EMAIL)) {
			return $this;
		}
		$itemObject;
		$fileInfo = array();
		$ioAdapter = new Varien_Io_File();
		$open_monitor_from = Date('Y-m-d h:i:s', strtotime('-'.Mage::getStoreConfig(self::XML_PATH_INVENTORY_NOTIFICATION_DAYS).' day'));
		$open_monitor_to = Mage::getModel('core/date')->gmtDate();
		$itemObject = Mage::getModel('dropship360/inventory')->getCollection()->addFieldTofilter('updated_at', array('from' => $open_monitor_from,'to' => $open_monitor_to));
		if($itemObject->getSize() <= 0){
			Mage::log('cannot send outdated product inventory email collection is empty for form :'.$open_monitor_from.' to :'.$open_monitor_to, null, 'notification_error.log');
			return $this;
		}
		$fileInfo = Mage::getModel('dropship360/csvparser')->getCsvFile($itemObject);
		$mailData['days'] = Mage::getStoreConfig(self::XML_PATH_INVENTORY_NOTIFICATION_DAYS);
		$mailData['subject'] = 'dropship360 list of outdated product inventory';
		$postObject = new Varien_Object();
		$postObject->setData($mailData);
		$email = trim(Mage::getStoreConfig(self::XML_PATH_INVENTORY_NOTIFICATION_EMAIL));
		$templateId = 'logicbroker_outdated_product_inventory';
		$isMailSent = Mage::helper('dropship360')->sendMail($postObject,$email,$templateId,$fileInfo['value']);
		$ioAdapter->rm($fileInfo['value']);
		return $this;
	}
}
