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
		
		$this->_queryInput = array('dropshipStatus'=> $data['dropshipstatus'],'monitor_order'=> isset($data['input_monitor_order_post']) ? $data['input_monitor_order_post'] : '0-day','open_monitor'=> isset($data['input_open_monitor_post']) ? $data['input_open_monitor_post'] : '0-day','transmitting_time'=>isset($data['input_transmitting_filter_post']) ? $data['input_transmitting_filter_post'] : '0-day','sts_time'=>(isset($data['input_sentosup_filter_post']) ? $data['input_sentosup_filter_post'] : '0-day'));
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
		$collectionLbItem->getSelect()->joinleft(array('lbRanking'=>Mage::getSingleton('core/resource')->getTableName('dropship360/ranking')),'lbRanking.lb_vendor_code = main_table.lb_vendor_code', array('lb_vendor_name'));
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
		$collectionLbItem = Mage::getModel('dropship360/orderitems')->getCollection()->addFieldTofilter('item_order_id', array('in' => $orderId));
		
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
		$helper = Mage::helper('dropship360');
		foreach($otherStatus['statusPercent'] as $itemStatus=>$percentage){
			$reportData['dropshipstatus'] = $itemStatus;
			$reportData['notificationPer'] = $percentage;
			$reportData['filter'] = false;
			$this->_selectStatus($itemStatus,$reportData,$percentage);
			
		}
		if(!empty($reportData['input_transmitting_filter']) || !empty($reportData['input_sentosup_filter']))
			$this->sendStaticStatusMail($otherStatus['orderid'],$otherStatus['queryInput'],$reportData);
		return $this;
	}
	
	protected function _selectStatus($itemStatus,$reportData,$perctange)
	{
		$helper = Mage::helper('dropship360');
		switch($itemStatus)
		{
			case $helper::LOGICBROKER_ITEM_STATUS_TRANSMITTING :
				if(!empty($reportData['notification_transmitting']))
					($perctange >= $reportData['notification_transmitting']) ? $this->sendEmail($reportData) : '';
				break;
			case $helper::LOGICBROKER_ITEM_STATUS_BACKORDER :
				if(!empty($reportData['notification_backorder']))
					($perctange >= $reportData['notification_backorder']) ? $this->sendEmail($reportData) : '';
				break;
			case $helper::LOGICBROKER_ITEM_STATUS_SENT_TO_SUPPLIER:
				if(!empty( $reportData['notification_sent_to_supplier']))
					($perctange >= $reportData['notification_sent_to_supplier']) ? $this->sendEmail($reportData) : '';
				break;
		}
	}
	
	protected function sendEmail($reportData){
		
	try {
		if($reportData['filter']){
			
			$reportData['subject'] = 'dropship360 has overdue orders sitting in '.$reportData['type'].' Status';
			$postObject = new Varien_Object();
			$postObject->setData($reportData);
			//$emails = explode(',',$reportData['email']);
			$templateId = 'logicbroker_activty_report_staticstatus';
			
		}else{
			$reportData['isnewreg'] = false;
			$reportData['emailbody'] = $reportData['dropshipstatus']. ' Status has reached '.$reportData['notificationPer']. '%';
			$reportData['subject'] = 'Activity monitor report data';
			$postObject = new Varien_Object();
			$postObject->setData($reportData);
			$templateId = 'logicbroker_email_email_template';
		}
			$emails = explode(',',$reportData['email']);
			foreach($emails as $email)
				{
					$isMailSent = Mage::helper('dropship360')->sendMail($postObject,$email,$templateId);
					if(!$isMailSent)
						Mage::log('Activity report email not sent to :'.$email, null, 'logicbroker_debug.log');
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
		$helper = Mage::helper('dropship360');
		$timeStringOpenMonitor = $this->getTimePeriod($queryInputs['open_monitor']);
		$otherStatus = array($helper::LOGICBROKER_ITEM_STATUS_TRANSMITTING,$helper::LOGICBROKER_ITEM_STATUS_BACKORDER,$helper::LOGICBROKER_ITEM_STATUS_SENT_TO_SUPPLIER);
		
		foreach($otherStatus as $statusType){
			$statusPercent[$statusType] = $this->calculatePer($orderId,$statusType,$timeStringOpenMonitor);
		}
	
		return array('statusPercent'=>$statusPercent,'queryInput'=>$queryInputs,'orderid'=>$orderId);
	}
	
	protected function calculatePer($orderId,$statusType,$openMonitorTime){
		
		$open_monitor_from = Date('Y-m-d h:i:s', strtotime($openMonitorTime));
		$open_monitor_to = Mage::getModel('core/date')->gmtDate();
		$allItemOrder = $this->getOrderItemsCollection(null,null,$orderId,null,false)->getSize();
		$allItemWithtime = $this->getOrderItemsCollection($open_monitor_from,$open_monitor_to,$orderId,$statusType,true)->getSize();
		return  round(($allItemWithtime / $allItemOrder) * $this->_percent);
	}
	
	protected function sendStaticStatusMail($orderId,$queryInputs,$reportData)
	{
		$helper = Mage::helper('dropship360');
		$this->generateMailData($reportData,$orderId,$this->getTimePeriod($queryInputs['transmitting_time']),$helper::LOGICBROKER_ITEM_STATUS_TRANSMITTING);
		$this->generateMailData($reportData,$orderId,$this->getTimePeriod($queryInputs['sts_time']),$helper::LOGICBROKER_ITEM_STATUS_SENT_TO_SUPPLIER);
		
	}
	
	protected function generateMailData($reportData,$orderId,$time,$statusType)
	{
		$open_monitor_from = Date('Y-m-d h:i:s', strtotime($time));
		$open_monitor_to = Mage::getModel('core/date')->gmtDate();
		$reportData['itemOject'] = $this->prepareMailGridData($this->getOrderItemsCollection($open_monitor_from,$open_monitor_to,$orderId,$statusType,true));
		$percentage = $this->calculatePer($orderId,$statusType,$time);
		$reportData['filter'] = true;
		$reportData['type'] = $statusType;
		$reportData['canshow'] = ($statusType == 'Transmitting') ? true : false;
		$this->_selectStatus($statusType,$reportData,$percentage);
		
	}
	
	protected function prepareMailGridData($collectionLbItem){
		$entityTypeId = Mage::getModel ( 'eav/config' )->getEntityType ( 'catalog_product' )->getEntityTypeId ();
		$prodNameAttrId = Mage::getModel('eav/entity_attribute')->loadByCode($entityTypeId, 'name')->getAttributeId();
		$collectionLbItem->getSelect()->joinleft(array('salesOrder'=>Mage::getSingleton('core/resource')->getTableName('sales/order')),'salesOrder.entity_id = main_table.item_order_id', array('increment_id'));
		$collectionLbItem->getSelect()->joinleft(array('lbRanking'=>Mage::getSingleton('core/resource')->getTableName('dropship360/ranking')),'lbRanking.lb_vendor_code = main_table.lb_vendor_code', array('lb_vendor_name'));
		$collectionLbItem->getSelect()->joinLeft(array('prod' => Mage::getSingleton('core/resource')->getTableName('catalog/product')),'prod.sku = main_table.sku',array('magento_pro_id'=>'entity_id'));
		$collectionLbItem->getSelect()->joinLeft(array('cpev' => Mage::getSingleton('core/resource')->getTableName('catalog/product').'_varchar'),'cpev.entity_id=prod.entity_id AND cpev.attribute_id='.$prodNameAttrId.'',array('product_name' => 'value'));
		$collectionLbItem->setOrder('updated_at', 'desc');
		return $collectionLbItem;
	}
}
	 