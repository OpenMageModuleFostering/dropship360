<?php
/**
 * Order API rewrite to add three Logicbroker fields in salesOrderItemEntity Array of salesorderinfo api
 * and to update Logicbroker item status
 * @category   Logicbroker
 * @package    Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Model_Order_Api extends Mage_Sales_Model_Order_Api_V2
{
	
	
	/**
	 * Retrieve list of orders. Filtration could be applied
	 *
	 * @param null|object|array $filters
	 * @return array
	 */
	public function items($filters = null)
	{
		$orders = array();
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
			
			$orders[] = array_merge($this->_getAttributes($order, 'order'),$itemTemp);
			unset($itemTemp);
		}else
		{
			$orders[] = $this->_getAttributes($order, 'order');
		}
		}

		
		return $orders;
	}
	
	protected function addItemDetails($order){
		$result = array();
		$lbItemCollection = Mage::getModel('logicbroker/orderitems')->getCollection()->addFieldToFilter('item_order_id',$order->getEntityId());
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
		$id    = $order->getId();
		foreach ($order->getAllItems() as $item) {
			$result['items'][] = $this->getProductLbDetails($item, $id);  
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
	protected function getProductLbDetails($item, $id){
		if ($item->getGiftMessageId() > 0) {
			$item->setGiftMessage(
				Mage::getSingleton('giftmessage/message')->load($item->getGiftMessageId())->getMessage()
			);
		}
		$productItems = array();
		$productItems = $this->_getAttributes($item, 'order_item');		
		$lbItems = Mage::getModel('logicbroker/orderitems')->getCollection()
				->addFieldToSelect(array('sku', 'lb_vendor_sku', 'lb_item_status', 'lb_vendor_code', 'item_id'))
				->addFieldToFilter('item_order_id',array('eq'=>$id))
				->addFieldToFilter('item_id', array('eq'=>$productItems['item_id']));	
				$productItems['lb_item_status'] =  $lbItems->getFirstItem()->getLbItemStatus();
				$productItems['lb_vendor_sku']  =  $lbItems->getFirstItem()->getLbVendorSku();					
				$productItems['lb_vendor_code'] =  $lbItems->getFirstItem()->getLbVendorCode(); 	
		return 	$productItems;		
	}

	
	 /**
     * Change Logic broker order item status
     *
     * @param string $orderIncrementId, array $sku, string $status
     * @return bool
     */
	public function updateItemStatus($orderIncrementId, $sku, $status){
		
		$order = $this->_initOrder($orderIncrementId);
		$result = false;
		
		try{
			//$order = Mage::getModel('sales/order')->load($orderIncrementId, 'increment_id');		
			$items = $order->getAllItems();
			foreach ($items as $item){
				if(in_array($item->getSku(), $sku)){
					$itemIdArr[] = $item->getItemId();
				}
			} 
			if(!$status){
				$status = 'Sent to Vendor';
			}
			if($itemIdArr && in_array(ucfirst($status),array('Sourcing','Backorder','Transmitting','Sent to Vendor','Cancelled','No Dropship','Completed'))){
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
		$lbStatus = Mage::getModel('logicbroker/orderitems')->load($itemId, 'item_id');
		if($lbStatus->getId()){			
			$lbStatus->setLbItemStatus(ucfirst($status))
					->setUpdatedBy('logicbroker')
					->save();	
			return true;
		}	
	}
	
	
	/**
	 * Retrive order by status 
	 *
	 * @param string $orderIncrementId, string $orderStatus, int $limit
	 * @return bool
	 */
	public function getLbOrderStatus($orderIncrementId,$orderStatus,$limit){
		
		$orderItemsdDetails = array();
		try{
			
			if($orderIncrementId){
				
				$result =  $this->info($orderIncrementId);
			}else
			{
				$orderCollection = Mage::getModel("sales/order")->getCollection();
				$orderCollection->addAttributeToFilter('status',$orderStatus);
				if($limit)
				$orderCollection->getSelect()->limit($limit);
				if($orderCollection->count() > 0){
					$result['ResultCount'] = $orderCollection->count();
					foreach($orderCollection as $order)
					{
					$result['orderDetails'][] = array('increment_id'=>$order->getIncrementId(),'status'=>$order->getStatus(),'state'=>$order->getState());
					}
				}else
				$result['error_message'] = 'Result not found'; 
			
			}
		}catch (Mage_Core_Exception $e) {
			$this->_fault('data_invalid', $e->getMessage());
		}
	
		
		return $result;
	}
	
	
	
	public function setLbOrderStatus($orderIncrementId,$orderStatus){
	
		$order = $this->_initOrder($orderIncrementId);
		$statusModel = Mage::getModel('sales/order_status')->load(trim($orderStatus));
		
		
		
		if(!$statusModel->getStatus()){
			$result['error_message'] = 'Order status does not exists';
		}else{
		try{
			$order->setStatus($orderStatus);
			$order->save();
			
		}catch (Mage_Core_Exception $e) {
			$this->_fault('data_invalid', $e->getMessage());
		}
		$result['success_message'] = 'Order status changed successfully';
		}
	
		return $result;
	}
	
	public function getLbOrderStatusConfig(){
	
		$result['begin_sourcing'] = Mage::getStoreConfig('logicbroker_sourcing/order/begin_sourcing');
		$result['backorder'] = Mage::getStoreConfig('logicbroker_sourcing/order/backorder');
		$result['awaiting_transmission'] = Mage::getStoreConfig('logicbroker_sourcing/order/awaiting_transmission');
		$result['sourcing_complete'] = Mage::getStoreConfig('logicbroker_sourcing/order/sourcing_complete');
	
		return $result;
	}

} 
