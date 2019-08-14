<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Report 
{
	
	protected $_queryInput = array();
	protected $_totalDropshipOrderCount = 0;
	protected $_dropshipStausCount = 0;
	protected $_orderIds = array();
	protected $_collection;
	protected $_percent = 100;
	protected $_status = array('Sent to Supplier','Sent to Vendor');
	
	const XML_PATH_REPORT_EMAIL_ENABLED          = 'logicbroker_sourcing/cron_settings_report/enabled';
    /* 
     * save reports data function  
     * $type : string report type
     * $data : array
    */
	public function saveReportData($type,$data){
    	
    	if(!is_array($data))
    	{
    		return;
    	}
		switch ($type)
    	{
    		case 'business_activity_monitor' :
    			
    			$this->saveActivityData($data);
    		
    	}	
    	return true;
    }
    
	public function saveActivityData($data){
		
		$activityReportData = $this->converRequestDataToCoreConfig($data);
		Mage::getModel('core/config_data')->setData($activityReportData)->save();
		return; 
	}
	
	protected function converRequestDataToCoreConfig($data)
	{
		
		
				return array(
						'scope'         => 'default',
						'scope_id'    => '0',
						'path'       => 'logicbroker_report/business_activity',
						'value'     => serialize($data),
		
				);
	}
	
	public function getActivityReportData()
	{
		$result = array();
		
		
		if($data = Mage::getStoreConfig('logicbroker_report/business_activity'))
		{
			$result = unserialize($data);
		}
		return $result;
	}
	
	public function activityReportCollection($data){
		
		$queryInputs = $this->genrateQueryInput($data);
		$orderId = $this->getOrderIds($queryInputs);
		$this->_orderIds = $orderId;
		
		$this->_prepareItemCollection($orderId,$queryInputs);
		return $this;
		 
	}
	
	protected function getOrderIds($queryInputs){
		
		$orderId = array();
		$timeStringMonitorOrder = $this->getTimePeriod($queryInputs['monitor_order']);
		$monitor_order_from = Date('Y-m-d h:i:s', strtotime($timeStringMonitorOrder));
		$monitor_order_to = Mage::getModel('core/date')->gmtDate();
		
		$collectionOrder = Mage::getModel('sales/order')->getCollection()->addFieldTofilter('created_at', array(
				'from' => $monitor_order_from,
				'to' => $monitor_order_to
		));
		
		if($collectionOrder->count() > 0){
			foreach ($collectionOrder as $data ){
					
				$orderId[] = $data->getEntityId();
			}
		}
		return $orderId;
	}
	
	protected function genrateQueryInput($data){
		
		$this->_queryInput = array('dropshipStatus'=> $data['dropshipstatus'],'monitor_order'=> $data['input_monitor_order_post'],'open_monitor'=> $data['input_open_monitor_post']);
		return $this->_queryInput;
	}
	
	protected function getTimePeriod($time){

		$split = explode('-',$time);
		return ($split[1] == 'day') ? '-'.$split[0].' day' : '-'.$split[0].' hour';  
		
	}
	
	protected function _prepareItemCollection($orderId = array(),$queryInputs){
		
		$timeStringOpenMonitor = $this->getTimePeriod($queryInputs['open_monitor']);
		$open_monitor_from = Date('Y-m-d h:i:s', strtotime($timeStringOpenMonitor));
		$open_monitor_to = Mage::getModel('core/date')->gmtDate();
		
		$entityTypeId = Mage::getModel ( 'eav/config' )->getEntityType ( 'catalog_product' )->getEntityTypeId ();
		$prodNameAttrId = Mage::getModel('eav/entity_attribute')->loadByCode($entityTypeId, 'name')->getAttributeId();
		
		$collectionLbItem = $this->getOrderItemsCollection($open_monitor_from,$open_monitor_to,$orderId,$queryInputs['dropshipStatus']);
		
		$collectionLbItem->getSelect()->joinleft(array('salesOrder'=>Mage::getSingleton('core/resource')->getTableName('sales/order')),'salesOrder.entity_id = main_table.item_order_id', array('increment_id'));
		$collectionLbItem->getSelect()->joinleft(array('lbRanking'=>Mage::getSingleton('core/resource')->getTableName('logicbroker/ranking')),'lbRanking.lb_vendor_code = main_table.lb_vendor_code', array('lb_vendor_name'));
		$collectionLbItem->getSelect()->joinLeft(array('prod' => Mage::getSingleton('core/resource')->getTableName('catalog/product')),'prod.sku = main_table.sku',array('magento_pro_id'=>'entity_id'));
		$collectionLbItem->getSelect()->joinLeft(array('cpev' => Mage::getSingleton('core/resource')->getTableName('catalog/product').'_varchar'),'cpev.entity_id=prod.entity_id AND cpev.attribute_id='.$prodNameAttrId.'',array('product_name' => 'value'));
		$collectionLbItem->setOrder('updated_at', 'desc');
		$this->_dropshipStausCount = $collectionLbItem->count();
		//echo $collectionLbItem->getSelect();
		//die;
		
		$this->_collection = $collectionLbItem;
		$this->prepareGraphData();
		
		return $this;
	}
	
	protected function getOrderItemsCollection($open_monitor_from = null,$open_monitor_to =null,$orderId,$status =null,$includeTime = true)
	{
		$collectionLbItem = Mage::getModel('logicbroker/orderitems')->getCollection()->addFieldTofilter('item_order_id ', array('in' => $orderId));
		
		if(!empty($status))
		{
			if(in_array($status,$this->_status))
				$collectionLbItem->addFieldTofilter('lb_item_status',array('in', $this->_status));
			else
				$collectionLbItem->addFieldTofilter('lb_item_status',$status);
		}
		if($includeTime)
			$collectionLbItem->addFieldTofilter('main_table.updated_at', array('from' => $open_monitor_from,'to' => $open_monitor_to));
		
		return $collectionLbItem;
	}
	
	protected function setGraphData(){
		
		$queryInputs = $this->_queryInput;
		$orderIds =	$this->_orderIds;
		$collectionLbItem = $this->getOrderItemsCollection(null,null,$orderIds,null,false);
		$this->_totalDropshipOrderCount = $collectionLbItem->count();
		
	}
    
	protected function prepareGraphData(){
		
		$this->setGraphData();
		return $this;
	}
	
	public function getBlockGraphData(){
		
		return array('totalDropshipOrder'=>$this->_totalDropshipOrderCount,'dropshipStatus'=>$this->_dropshipStausCount,'collection'=>$this->_collection);
	}
	
	//function call by the cron
	public function sendNotification(){
		
		if (! Mage::getStoreConfigFlag ( self::XML_PATH_REPORT_EMAIL_ENABLED ) ) {
			return $this;
		}
		
		$reportData = $this->getActivityReportData();
		$otherStatus = $this->calculateNotificationForOtherStatus($reportData);
		$helper = Mage::helper('logicbroker');
		foreach($otherStatus as $key=>$status){
			$reportData['dropshipstatus'] = $key;
			$reportData['notificationPer'] = $status;
			if($key == $helper::LOGICBROKER_ITEM_STATUS_TRANSMITTING){
				if(!empty($reportData['notification_transmitting']))
				($status >= $reportData['notification_transmitting']) ? $this->sendEmail($reportData) : '';
			}
			if($key == $helper::LOGICBROKER_ITEM_STATUS_BACKORDER){
			     if(!empty($reportData['notification_backorder']))
				($status >= $reportData['notification_backorder']) ? $this->sendEmail($reportData) : '';
			}
			if($key == $helper::LOGICBROKER_ITEM_STATUS_SENT_TO_SUPPLIER){			
				if(!empty( $reportData['notification_sent_to_supplier']))
				($status >= $reportData['notification_sent_to_supplier']) ? $this->sendEmail($reportData) : '';
			}
		}
		return;
	}
	
	
	protected function sendEmail($reportData){
		
	try {
		
				$reportData['isnewreg'] = false;
				$reportData['emailbody'] = $reportData['dropshipstatus']. ' Status has reached '.$reportData['notificationPer']. '%';
				$postObject = new Varien_Object();
				$postObject->setData($reportData);
				$mailTemplate = Mage::getModel('core/email_template');
		
				/* @var $mailTemplate Mage_Core_Model_Email_Template */
				$mailTemplate->setDesignConfig(array('area' => 'frontend'));
				$emails = explode(',',$reportData['email']);
				
				foreach($emails as $email)
				{
				$name = explode('@',$email);	
				$mailTemplate->sendTransactional(
						'logicbroker_email_email_template',
						'general',
						$email,
						$name[0],
						array('templatevar' => $postObject)
				);
				}
				if (!$mailTemplate->getSentSuccess()) {
					Mage::helper('logicbroker')->genrateLog(0,'Installation notification started','Installation notification ended','Module installation notifiaction mail sending failed');
					return false;
					 
				}
		
				return true;
			} catch (Exception $e) {
				return false;
			}
	
	}
	
	protected function calculateNotificationForOtherStatus($reportData){
		
		$queryInputs = $this->genrateQueryInput($reportData);
		$orderId = $this->getOrderIds($queryInputs);
		$statusPercent = array();
		
		$timeStringOpenMonitor = $this->getTimePeriod($queryInputs['open_monitor']);
		$open_monitor_from = Date('Y-m-d h:i:s', strtotime($timeStringOpenMonitor));
		$open_monitor_to = Mage::getModel('core/date')->gmtDate();
		
		$otherStatus = array('Transmitting','Backorder','Sent to Supplier');
		
		foreach($otherStatus as $data){
			
			$allItemOrder = $this->getOrderItemsCollection(null,null,$orderId,null,false)->getSize();
			$allItemWithtime = $this->getOrderItemsCollection($open_monitor_from,$open_monitor_to,$orderId,$data,true)->getSize();
			$statusPercent[$data] =  round(($allItemWithtime / $allItemOrder) * $this->_percent);
			
		}
	
		return $statusPercent;
	}
}
	 