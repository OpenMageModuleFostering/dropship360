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
	const XML_PATH_INVENTORY_NOTIFICATION_EMAIL_ENABLED  = 'logicbroker_sourcing/inventory_notification/enabled';
	
	
	
	public static function getWorkingDir()
	{
		return Mage::getBaseDir();
	}
	
	/**
	 * @event : sales_order_place_after
	 * insert order items details in logicbroker_sales_orders_items for sourcing  
	 * processing is dependent dropship360 extension configuration 
	 */
	public function insertProcessOrder($object)
	{
		
		if($object->getOrder()->getLogicbrokerItemProcessed())
		{
			return;
		}
		$this->_orderStatus = $object->getOrder()->getStatus();
			foreach ($object->getOrder ()->getAllItems() as $item){
				if(in_array($item->getProductType(),array('simple','grouped')) ){
					$started = 0;
					$ended = 1;
					$logMsg = 'Item inserted @'.Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/orderitems' ). ' sku : '.$item->getSku().','.$object->getOrder()->getIncrementId();
					Mage::helper('dropship360')->genrateLog(++$started,'Order Item Inserted Started',null,$logMsg);
					Mage::getModel('dropship360/ordersourcing')->getOrderSourcing($item, $object);
					Mage::helper('dropship360')->genrateLog(++$ended,null,'Order Item Inserted Ended',null);
				}
			}
			$object->getOrder()->setLogicbrokerItemProcessed(true);
		}
		
	/**
	 * @cron : logicbroker_dropship360
	 * logicbroker main/reprocess souring logic execution begins  
	 */
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
		$sourcingObj->setLbVendorRanking (Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS,true);
		$this->addCronStatus('logicbroker_sourcing/cron_settings/dispaly_sourcing_updated_time', Mage::helper('core')->formatDate(now(), 'medium', true));
		$sourcingObj->sourcingCompleted(Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_SOURCING);
		Mage::helper('dropship360')->genrateLog(2,null,'Sourcing Ended for ' .Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_REPROCESS.' Item status',null);
		return; 
	}
	
	/**
	 * @cron : logicbroker_backorder
	 * logicbroker backorder souring logic execution begins
	 */
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
		$sourcingObj->setLbVendorRanking (Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER);
		$this->addCronStatus('logicbroker_sourcing/cron_settings/display_backorder_updated_time', Mage::helper('core')->formatDate(now(), 'medium', true));
		$sourcingObj->sourcingCompleted(Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER);
		Mage::helper('dropship360')->genrateLog(1,'Backorder Sourcing Ended for '.Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_BACKORDER .' item status',null,null);
		return;
	}
		
		
	/** 
	* Save last cron status
	* @param string $statusPath
	* @param string $status
	*/
	protected function addCronStatus($statusPath, $status) {
		$config = new Mage_Core_Model_Config();
		$config->saveConfig($statusPath, $status, 'default', 0);
		return;
	}
	
	 /**
     * Flag to stop observer executing more than once
     *
     * @var static bool
     */
    //static protected $_singletonFlag = false;
 
    /**
     * This method will run when the product is saved from the Magento Admin
     * Use this function to update the logicbroker dropship360 model, process the 
     * data or anything you like
     *
     * @param Varien_Event_Observer $observer
     */
	
	public function saveProductTabData(Varien_Event_Observer $observer)
   	{       
        $product = $observer->getEvent()->getProduct();
        if(!empty($product['vendor_update']) || !empty($product['vendor_new'])){
            try {
                $customFieldValue =  Mage::app()->getRequest()->getPost('product');
                $result = Mage::getModel('dropship360/inventory')->saveTabVendorData($customFieldValue);
                Mage::getModel('dropship360/inventory')->productInventoryUpdate($result,$customFieldValue['sku']);
            }
            catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
         }
         return $this;
   	}
   	
    
    public function preDispatch(Varien_Event_Observer $observer)
    {    
		return;
    }
    
    /**
     * @event : sales_order_shipment_save_before
     * send email when shipping tracking information is added  
     */
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
    
    /**
     *@event : sales_order_shipment_save_after
     *send email when shipping tracking information is added 
     */ 
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
	
    /**
     * validate shipping email need to send
     */
    protected function _isValidForShipmentEmail($shipment)
    {
    	// send shipment email only when email shipment is enabled from module
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
    
	/**
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
	
	/**
	 * @function : notify customer for outdated product inventory through email,initiated by cron
	 */
	public function notifyForProductUpdateInventory(){
		if (!Mage::getStoreConfigFlag (self::XML_PATH_INVENTORY_NOTIFICATION_EMAIL_ENABLED) || !Mage::getStoreConfigFlag (self::XML_PATH_INVENTORY_NOTIFICATION_DAYS) || !Mage::getStoreConfigFlag (self::XML_PATH_INVENTORY_NOTIFICATION_EMAIL)) {
			return $this;
		}
		Mage::getModel('dropship360/inventory')->notificationProductInventoryUpdate();
		return $this;
	}
}
