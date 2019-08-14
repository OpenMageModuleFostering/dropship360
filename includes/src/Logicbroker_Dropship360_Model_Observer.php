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
	const STATUS_RUNNING = 'running';
	const STATUS_SUCCESS = 'success';
	protected $_orderStatus;
	
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
					$logMsg = 'Item inserted @'.Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/orderitems' ). ' sku : '.$item->getSku().','.$object->getOrder()->getIncrementId();
					Mage::helper('logicbroker')->genrateLog(++$started,'Order Item Inserted Started',null,$logMsg);
						$this->getOrderSourcing($item, $object);
					Mage::helper('logicbroker')->genrateLog(++$ended,null,'Order Item Inserted Ended',null);
				}
			}
		}
	
	protected function getOrderSourcing($item, $object){
		$orderSourcingInstance = Mage::getModel ( 'logicbroker/orderitems' );
		$orderStatus = $object->getOrder()->getStatus();
    	$itemStatusHistory = Mage::helper('logicbroker')->getSerialisedData($orderSourcingInstance, 'Sourcing', $orderStatus);
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
			Mage::helper('logicbroker')->genrateLog(0,null,null,'Section : order item inserted Error: '.$e->getMessage().' sku : '.$item->getSku());
			echo $e->getMessage();
		}
		
		//As item get saved in logicbroker_sales_orders_items we run our sourcing logic
		$assigned = $this->assignToVendor($item);
		
	}

	public function logicbrokerSourcing() {
		if(!Mage::getStoreConfig(self::CRON_STRING_PATH_SOURCING)) {
			Mage::helper('logicbroker')->genrateLog(0,'Sourcing started','Sourcing started','Sourcing can not be started as cron time not set');
			return;
		}
		Mage::helper('logicbroker')->genrateLog(1,'Sourcing Started for '.Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS.' Item status',null,null);
		$this->setLbVendorRanking (Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS);
		$this->addCronStatus('logicbroker_sourcing/cron_settings/dispaly_sourcing_updated_time', Mage::helper('core')->formatDate(now(), 'medium', true));
		Mage::helper('logicbroker')->genrateLog(2,null,'Sourcing Ended for ' .Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS.' Item status',null);
		return; 
	}
	
	public function logicbrokerBackorder()
	{
		if(!Mage::getStoreConfig(self::CRON_STRING_PATH_BACKORDER)) {
			Mage::helper('logicbroker')->genrateLog(0,'Backorder sourcing started','Backorder sourcing ended','Backorder Sourcing can not be started as cron time not set');
			return;
        }
        Mage::helper('logicbroker')->genrateLog(1,'Backorder Sourcing Started for '.Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER . ' item status',null,null);
		$this->setLbVendorRanking (Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER,true);
		$this->addCronStatus('logicbroker_sourcing/cron_settings/display_backorder_updated_time', Mage::helper('core')->formatDate(now(), 'medium', true));
		Mage::helper('logicbroker')->genrateLog(1,'Backorder Sourcing Ended for '.Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER .' item status',null,null);
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
	
	protected function setLbVendorRanking($crontype,$isBackorderedCron = false)
	{
		$reprocess = Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS;
		$lbOrderInstances = Mage::getModel('logicbroker/ordersourcing');
		$collection = $lbOrderInstances->prepareItemCollection($crontype);
		if($collection->count() > 0 ){
		foreach ( $collection as $orderData ) {
		$orderCollection = Mage::getModel('sales/order')->Load($orderData->getItemOrderId());
		
		Mage::helper('logicbroker')->genrateLog(0,null,null,'<---->Item Processing Started : '.$orderData->getSku());
				
			if ($crontype == Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS) {
					
				//Patch : skip sourcing process if order is deleted	
				if (! $orderCollection->getEntityId ()) {
						Mage::helper ( 'logicbroker' )->genrateLog ( 0, null, null, 'Order not exists for => order_id: ' . $orderData->getItemOrderId () . ' hence cannot continue' );
						continue;
					}
				$this->_orderStatus = $orderCollection->getStatus();
				$assigned = $this->assignToVendor(Mage::getModel('sales/order_item')->Load($orderData->getItemId()));
				$orderCollection->addStatusHistoryComment($orderData->getSku().': Item status changed to '.$assigned);
				
			}else
			{
				$orderItems = Mage::getModel ( 'logicbroker/orderitems' )->load($orderData->getItemId(),'item_id');	
				$itemStatusHistory = Mage::helper('logicbroker')->getSerialisedData($orderItems, $reprocess, $orderCollection->getStatus());
				$orderItems->setLbItemStatus ($reprocess)
				->setitemStatusHistory($itemStatusHistory)
				->save();
				$orderCollection->addStatusHistoryComment($orderData->getSku().': Item status changed to '.$reprocess);
				
			}
			$orderCollection->save();
			Mage::helper('logicbroker')->genrateLog(0,null,null,'####### Item Processing ended : '.$orderData->getSku());
			 			 	
			
		}
		}else {
		Mage::helper('logicbroker')->genrateLog(0,null,null,'Order collection is empty for => Cron_type: '.$crontype.' hence cannot continue');
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
		$orderItemInstance = Mage::getModel ( 'logicbroker/orderitems' );
		$orderItemInstance->load ( $itemId, 'item_id' );
		$collectionVendor = $orderItemInstance->prepareOrderItemData($item);
		$arrDefaultVendorDetails = array();
		
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
								
				$itemStatus = 'Transmitting';
				$itemStatusHistory = Mage::helper('logicbroker')->getSerialisedData($orderItemInstance, $itemStatus, $this->_orderStatus);
				$orderItemInstance->setItemData($orderItemInstance,$itemStatusComplete,$item,$vendorCode,$vendorCost*$qtyInvoiced,$vendorSku,$itemStatusHistory);
					$orderItemInstance->updateLbVendorInvenory ( $vendorCode, $productSku,$qtyInvoiced );
				Mage::helper('logicbroker')->genrateLog(0,null,null,'@@@@@@@@ Sourcing Details==> stock('.$inventoryStock.') >= qtyinvoiced('.$qtyInvoiced.'),vendor_code ->'.$vendorCode.', item-status->'.$itemStatus);
				return $itemStatusComplete;
			}
			if ($isDefaultVendor && $inventoryStock <= $qtyInvoiced && !empty($defaultVendor) && in_array($defaultVendor,$arrVendorAvailable)) {	
				$itemStatusHistory = Mage::helper('logicbroker')->getSerialisedData($orderItemInstance, 'Transmitting', $this->_orderStatus);		
				$orderItemInstance->setItemData($orderItemInstance,$itemStatusComplete,$item,$defaultVendor,$arrDefaultVendorDetails['cost']*$qtyInvoiced,$arrDefaultVendorDetails['lb_vendor_sku'],$itemStatusHistory);
				Mage::helper('logicbroker')->genrateLog(0,null,null,'@@@@@@@@ Sourcing Details Default vendor set ==>stock('.$inventoryStock.') >= qtyinvoiced('.$qtyInvoiced.'),vendor_code ->'.$vendorCode.', item-status->Transmitting');
				return $itemStatusComplete;			
			}
			if ($vendorCode && $inventoryStock <= $qtyInvoiced) {				
				$itemStatus = 'Backorder';
				$itemStatusHistory =Mage::helper('logicbroker')->getSerialisedData($orderItemInstance, $itemStatus, $this->_orderStatus);
				$orderItemInstance->setItemData($orderItemInstance,$itemStatusBackorder,$item,$vendorCode,$vendorCost*$qtyInvoiced,$vendorSku,$itemStatusHistory);
				Mage::helper('logicbroker')->genrateLog(0,null,null,'@@@@@@@@ Sourcing Details==>stock('.$inventoryStock.') <= qtyinvoiced('.$qtyInvoiced.'),vendor_code ->'.$vendorCode.', item-status->'.$itemStatus);
				return $itemStatusBackorder;				
			}		
		}else{
			$itemStatusHistory = Mage::helper('logicbroker')->getSerialisedData($orderItemInstance,'No Dropship', $this->_orderStatus);	
			$orderItemInstance->setItemData($orderItemInstance,'No Dropship',$item,$vendorCode,$qtyInvoiced,"",$itemStatusHistory);
			Mage::helper('logicbroker')->genrateLog(0,null,null,'@@@@@@@@ Sourcing Details==> No vendor Set ,vendor_code ->'.$vendorCode.', item-status->No Dropship');
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
   	}
   	
   	protected function _inventoryUpdate($result,$sku)
   	{
   		if(!$result['result']){
   			return;
   		}
   			
   		$finalStock = $result['inventory'];
        $finalStock = Mage::helper('logicbroker')->getIsQtyDecimal($sku, $finalStock);
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
		$orderItem = Mage::getModel ('logicbroker/inventory')->getCollection()->addFieldToFilter('product_sku', $deletedProductSku);
		if($orderItem->getSize() > 0){
			foreach($orderItem as $data){
				try {
					Mage::getModel ('logicbroker/inventory')->load($data->getId())->delete();
				} catch (Exception $e) {    
					Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
				}		
			}
		}
		return 	$this;		
	}
}
