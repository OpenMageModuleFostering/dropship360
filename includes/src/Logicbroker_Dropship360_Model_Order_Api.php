<?php
/**
 * Order API rewrite to add three Logicbroker fields in salesOrderItemEntity Array of salesorderinfo api
 * and to update Logicbroker item status
 * @category   Logicbroker
 * @package    Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Model_Order_Api extends Mage_Sales_Model_Order_Api_V2
{
	protected $_itemStatusTansmitting = Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_TRANSMITTING;
	
	/**
	 * Retrieve list of orders. Filtration could be applied
	 *
	 * @param null|object|array $filters
	 * @return array
	 */
	public function items($filters = null)
	{
		$orders = array();
		$itemTemp = array();
		//TODO: add full name logic
		$billingAliasName = 'billing_o_a';
		$shippingAliasName = 'shipping_o_a';
	
		/** @var $orderCollection Mage_Sales_Model_Mysql4_Order_Collection */
		$orderCollection = Mage::getModel("sales/order")->getCollection();
		$billingFirstnameField = "$billingAliasName.firstname";
		$billingLastnameField = "$billingAliasName.lastname";
		$shippingFirstnameField = "$shippingAliasName.firstname";
		$shippingLastnameField = "$shippingAliasName.lastname";
		$orderCollection->addAttributeToSelect('*')
		->addAddressFields()
		->addExpressionFieldToSelect('billing_firstname', "{{billing_firstname}}",
				array('billing_firstname' => $billingFirstnameField))
				->addExpressionFieldToSelect('billing_lastname', "{{billing_lastname}}",
						array('billing_lastname' => $billingLastnameField))
						->addExpressionFieldToSelect('shipping_firstname', "{{shipping_firstname}}",
								array('shipping_firstname' => $shippingFirstnameField))
								->addExpressionFieldToSelect('shipping_lastname', "{{shipping_lastname}}",
										array('shipping_lastname' => $shippingLastnameField))
										->addExpressionFieldToSelect('billing_name', "CONCAT({{billing_firstname}}, ' ', {{billing_lastname}})",
												array('billing_firstname' => $billingFirstnameField, 'billing_lastname' => $billingLastnameField))
												->addExpressionFieldToSelect('shipping_name', 'CONCAT({{shipping_firstname}}, " ", {{shipping_lastname}})',
														array('shipping_firstname' => $shippingFirstnameField, 'shipping_lastname' => $shippingLastnameField)
												);
	
		/** @var $apiHelper Mage_Api_Helper_Data */
		$apiHelper = Mage::helper('api');
		$filters = $apiHelper->parseFilters($filters, $this->_attributesMap['order']);
		try {
			foreach ($filters as $field => $value) {
				$orderCollection->addFieldToFilter($field, $value);
			}
		} catch (Mage_Core_Exception $e) {
			$this->_fault('filters_invalid', $e->getMessage());
		}
		
		/* Patch apply to display item list with 
		 * vendor_cost,lb_vendor_code,lb_vendor_sku,magento_sku 
		*/
		foreach ($orderCollection as $order) {
			
			$results = $this->addItemDetails($order);
			if(count($results) > 0){
			foreach($results as $result)
			{
				$itemTemp['item_details'][] = $result;
			}
			$itemTemp['dropship_item'] = $this->isDropshipItemReady($order);
			$orders[] = array_merge($this->_getAttributes($order, 'order'),$itemTemp);
			unset($itemTemp);
		}else
		{
			$orders[] = $this->_getAttributes($order, 'order');
		}
		}

		
		return $orders;
	}
	protected function isDropshipItemReady($order)
	{
		$result = false;
		$lbItemCollection = Mage::getModel('dropship360/orderitems')->getCollection()->addFieldToFilter('lb_item_status',$this->_itemStatusTansmitting)->addFieldToFilter('item_order_id',$order->getEntityId());
		if($lbItemCollection->count() > 0)
			$result = true;
		return $result;
	}
	
	protected function addItemDetails($order){
		$result = array();
		$lbItemCollection = Mage::getModel('dropship360/orderitems')->getCollection()->addFieldToFilter('item_order_id',$order->getEntityId());
		if($lbItemCollection->count() > 0){
			unset($result);
			foreach($lbItemCollection as $item)
			{
				$result[] = array('item_sku'=>$item->getSku(),'lb_vendor_sku' => $item->getLbVendorSku(),'lb_vendor_code' => $item->getLbVendorCode(),'lb_vendor_cost' => $item->getVendorCost());;
			} 
		}
		
		return $result;
	}
   /**
     * Retrieve full order information
     *
     * @param string $orderIncrementId
     * @return array
     */
    public function info($orderIncrementId)
    {
        $order = $this->_initOrder($orderIncrementId);

        if ($order->getGiftMessageId() > 0) {
            $order->setGiftMessage(
                Mage::getSingleton('giftmessage/message')->load($order->getGiftMessageId())->getMessage()
            );
        }

        $result = $this->_getAttributes($order, 'order');

        $result['shipping_address'] = $this->_getAttributes($order->getShippingAddress(), 'order_address');
        $result['billing_address']  = $this->_getAttributes($order->getBillingAddress(), 'order_address');
        $result['items'] = array();
		
		/* Start of changes for adding lbs fields*/
		//$id    = $order->getId();
		foreach ($order->getAllItems() as $item) {
			$result['items'][] = $this->getProductLbDetails($item, $order);  
        }

        $result['payment'] = $this->_getAttributes($order->getPayment(), 'order_payment');

        $result['status_history'] = array();

        foreach ($order->getAllStatusHistory() as $history) {
            $result['status_history'][] = $this->_getAttributes($history, 'order_status_history');
        }

        return $result;
    }
	/**
	* This function will add lb details to order items
	* @param array $item, int $id
	* @return array $productItems
	*/
	protected function getProductLbDetails($item, $order){
		if ($item->getGiftMessageId() > 0) {
			$item->setGiftMessage(
				Mage::getSingleton('giftmessage/message')->load($item->getGiftMessageId())->getMessage()
			);
		}
		$productItems = array();
		$productItems = $this->_getAttributes($item, 'order_item');		
		$lbItems = Mage::getModel('dropship360/orderitems')->getCollection()
				->addFieldToSelect(array('sku', 'lb_vendor_sku', 'lb_item_status', 'lb_vendor_code', 'item_id','vendor_cost'))
					->addFieldToFilter('item_order_id',array('eq'=>$order->getId()))
					->addFieldToFilter('item_id', array('eq'=>$productItems['item_id']))
					->addFieldToFilter('lb_item_status', array('eq'=>$this->_itemStatusTansmitting));
		$lbItems->getSelect()->join(array('salesOrder'=>Mage::getSingleton('core/resource')->getTableName('sales/order')),
  			'salesOrder.entity_id = main_table.item_order_id', array('state'))->where('salesOrder.state = ?','processing');
		$lbItems->getSelect()->limit(1);
		if($lbItems->getSize() > 0){
				$productItems['lb_item_status'] =  $lbItems->getFirstItem()->getLbItemStatus();
				$productItems['lb_vendor_sku']  =  $lbItems->getFirstItem()->getLbVendorSku();					
				$productItems['lb_vendor_code'] =  $lbItems->getFirstItem()->getLbVendorCode(); 	
				$productItems['lb_vendor_cost'] =  $lbItems->getFirstItem()->getVendorCost();
		} 	
		return 	$productItems;		
	}

	
	 /**
     * Change Dropship360 order item status for given item sku
     *
     * @param string $orderIncrementId, array $sku, string $status
     * @return bool
     */
	public function updateItemStatus($orderIncrementId, $sku, $status){
		
		$order = $this->_initOrder($orderIncrementId);
		$result = false;
		
		try{		
			$items = $order->getAllItems();
			foreach ($items as $item){
				if(in_array($item->getSku(), $sku)){
					$itemIdArr[] = $item->getItemId();
				}
			} 
			if(!$status){
				$status = Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_SENT_TO_SUPPLIER;
			}
			if($itemIdArr && in_array(ucfirst($status),Mage::helper('dropship360')->getItemStatuses())){
				foreach($itemIdArr as $itemId){
					$result = $this->saveLbStatus($itemId, $status);						
				}
			}else{
				$result = false;
			}
		}catch (Mage_Core_Exception $e) {
            $this->_fault('status_not_changed', $e->getMessage());
        }
		return $result;
	}
	
	/**
	* This function is used to save lb order item status
	* @param int $itemId, string $status
	* @return bool
	*/
	protected function saveLbStatus($itemId, $status){
		$lbStatus = Mage::getModel('dropship360/orderitems')->load($itemId, 'item_id');
		$orderCollection = Mage::getModel('sales/order')->load($lbStatus->getItemOrderId());
		$orderStatus = $orderCollection->getStatus();
		$itemStatusHistory = Mage::helper('dropship360')->getSerialisedData($lbStatus, ucfirst($status), $orderStatus);
		if($lbStatus->getId()){			
			$lbStatus->setLbItemStatus(ucfirst($status))
					->setUpdatedBy('logicbroker')
					->setItemStatusHistory($itemStatusHistory)
					->setUpdatedAt(Mage::getModel('core/date')->gmtDate())
					->save();	
			Mage::helper('dropship360')->genrateLog(0,'API Item Update started','API Item Update ended','Item Status updated by Logicbroker API item-status->'.$status.' ,sku->'.$lbStatus->getSku().' ,orderId->'.$lbStatus->getItemOrderId());
			return true;
		}	
	}
	
	
	/**
	 * Retrive orders by Item status 
	 *
	 * @param string $orderIncrementId, string $orderStatus, int $limit
	 * @return bool
	 */
	public function getLbOrderByItemStatus($store_id,$orderItemStatus){
		
		if (!$store_id) {
			$this->_fault('invaild_store');
		}
		//Default DS item status will be TRANSMITTING
		$orderItemStatus = (!empty($orderItemStatus)) ? $orderItemStatus : Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_TRANSMITTING;
			
		$orderItemsdDetails = array();
		try{
			
				$orderCollection = Mage::getModel('dropship360/orderitems')->getCollection();
				$orderCollection->addFieldToFilter('lb_item_status',$orderItemStatus);
				$orderCollection->getSelect()->join(array('salesOrder'=>Mage::getSingleton('core/resource')->getTableName('sales/order')),
  			'salesOrder.entity_id = main_table.item_order_id', array('increment_id','store_id'))->where('store_id = ?', (int)$store_id);
				$orderCollection->getSelect()->group('item_order_id');
				
				if($orderCollection->getSize() > 0){
					$result['ResultCount'] = $orderCollection->count();
					foreach($orderCollection as $order)
					{
					$result['orderDetails'][] = array('increment_id'=>$order->getIncrementId());
					}
				}else
				$result['error_message'] = 'Result not found'; 
			
			
		}catch (Mage_Core_Exception $e) {
			$this->_fault('data_invalid', $e->getMessage());
		}
	
		
		return $result;
	}
	
	
	/**
	 * set itemStatus to all dropship360 order items irrespective of SKU   
	 * @param Mage_sales_order $orderIncrementId
	 * @param Logicbroker_Dropship360_Model_Orderitems $itemStatus
	 * @return multitype:string
	 */
	public function setLbOrderItemStatus($orderIncrementId,$itemStatus){
	
		$order = $this->_initOrder($orderIncrementId);
		$itemId = array();
		$itemOrderId = $order->getEntityId();
		$result = false;
		$orderCollection = Mage::getModel('dropship360/orderitems')->getCollection();
		$orderCollection->addFieldToFilter('lb_item_status','Transmitting');
		$orderCollection->addFieldToFilter('item_order_id',$itemOrderId);
		
		
		
		try{		
		
			if($orderCollection->getSize() > 0){
			foreach($orderCollection as $itemDetails)
			{
				$itemId[] = $itemDetails->getItemId();
			}
			
		}
		if(empty($itemStatus)){
			$itemStatus = Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_SENT_TO_SUPPLIER;
		}
			
			if(!empty($itemId) && in_array(ucfirst($itemStatus),Mage::helper('dropship360')->getItemStatuses())){
				foreach($itemId as $itemId){
					$result = $this->saveLbStatus($itemId, $itemStatus);						
				}
			}else{
				$result = false;
			}
		}catch (Mage_Core_Exception $e) {
            $this->_fault('status_not_changed', $e->getMessage());
        }
		return ($result) ? array('success_message'=>'Item Status Upated Successfully') : array('error_message'=>'Error In Updating Item Status');
	}
	

} 
