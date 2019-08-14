<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Orderitems extends Mage_Core_Model_Abstract
{
    protected function _construct(){
       $this->_init("dropship360/orderitems");
    }
	
	public function prepareOrderItemData($item){		
		$productSku = $item->getSku ();
		$ruletype = Mage::getStoreConfig ( 'logicbroker_sourcing/rank/ranktype' );
		
		
		if ($ruletype == 'default')
			$orderBy = 'ranking ASC';
		else
			$orderBy = 'cost ASC';
		
		$collectionVendor = Mage::getModel ( 'dropship360/inventory' )->getCollection ()->addFieldToFilter ( 'product_sku', $productSku );
		$collectionVendor->getSelect ()->joinleft ( array ('lbRanking' => Mage::getSingleton ( 'core/resource' )->getTableName ( 'dropship360/ranking' )), 'lbRanking.lb_vendor_code = main_table.lb_vendor_code', array ('*') )->where('lbRanking.is_dropship = "yes" and lbRanking.is_active = "yes"');
		$collectionVendor->getSelect ()->order ( $orderBy );
		return $collectionVendor;
	}
	
	
	public function isVendorCollectionAvailable()
	{	
		if (Mage::getModel ( 'dropship360/inventory' )->getCollection ()->count() > 0 ) 
			return true;
		else 
			return false;
	}
	
	
	
	public function setItemData($orderItemInstance,$status,$item,$vendorCode,$vendorCost,$vendorSku,$itemStatusHistory)
	{
		$orderItemInstance->setItemId ( $item->getItemId () );
		$orderItemInstance->setLbItemStatus ( $status );
		$orderItemInstance->setLbVendorCode ( $vendorCode );
		$orderItemInstance->setLbVendorSku ( $vendorSku );
		$orderItemInstance->setVendorCost ( $vendorCost );
		$orderItemInstance->setItemOrderId ($item->getOrderId () );
		$orderItemInstance->setSku ( $item->getSku() );
		$orderItemInstance->setUpdatedBy ('Cron');
		$orderItemInstance->setUpdatedAt (now());
		$orderItemInstance->setCreatedAt ( Mage::getModel('core/date')->gmtDate());
		$orderItemInstance->setItemStatusHistory($itemStatusHistory);		
		try {
			$orderItemInstance->save ();
		} catch ( Exception $e ) {
			Mage::helper('dropship360')->genrateLog(0,null,null,'Section :Error In Setting order item data: '.$e->getMessage().' sku : '.$item->getSku().','.$item->getOrderId ());
			echo $e->getMessage ();
		}	
	}
	public function updateLbVendorInvenory($vendorCode,$productSku,$qtyInvoiced) 
	{
		$inventory = Mage::getModel ( 'dropship360/inventory' )->getCollection()
					->addFieldToFilter('lb_vendor_code',$vendorCode)->addFieldToFilter('product_sku',$productSku);		
		$filedData = $inventory->getFirstItem()->getData();
		$LbInventoryStock = $filedData['stock'];
		$finalStock = $LbInventoryStock - $qtyInvoiced;
		$inventory->getFirstItem()->setStock ( ($finalStock > 0) ? $finalStock : 0 );	
		try {
			$inventory->getFirstItem()->save ();
			Mage::getModel('dropship360/inventory')->_saveInventoryLog('update',array('lb_vendor_name'=>$filedData['lb_vendor_name'],'updated_by'=>'system','product_sku'=>$productSku,'lb_vendor_code'=>$vendorCode,'cost'=>$filedData['cost'],'stock'=>($finalStock > 0) ? $finalStock : 0));
		} catch ( Exception $e ) {
			echo $e->getMessage ();
		}
	}
	
	public function updateSourcingByUser($request)
	{		
		$arrData = array();
		$inventoryModel = Mage::getModel('dropship360/inventory')->getCollection()->addFieldToFilter('lb_vendor_code',$request['lb_vendor_code'])->addFieldToFilter('product_sku',$request['product_sku']);
		$arrData['lb_vendor_code'] = $request['lb_vendor_code'];
		$arrData['lb_item_status'] = Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_TRANSMITTING;
		$arrData['updated_by'] = 'User';
		$arrData['lb_vendor_sku'] = $inventoryModel->getFirstItem()->getLbVendorSku();
		$arrData['vendor_cost'] = $request['qty']*$inventoryModel->getFirstItem()->getCost();
		$arrData['item_status_history'] = $request['item_status_history'];
		return $arrData;
	}
	
	public function updateOrderStatus($orderId,$itemId)
	{	
		$arrData = array();
		$orderStatus = $this->_changeAllItemStatus($orderId,$itemId);
		if(is_null($orderStatus))
		return;
		$orderCollection = Mage::getModel('sales/order')->Load($orderId);
		$orderCollection->setStatus(Mage::getStoreConfig($orderStatus));       
		$orderCollection->addStatusToHistory(Mage::getStoreConfig($orderStatus), 'Order status and sourcing changed by user', false);
		try{
			$orderCollection->save();
		}catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }
		return $arrData;
	}
	
	protected function _changeAllItemStatus($orderId){
		$orderItemCollection = $this->getCollection()->addFieldToFilter('item_order_id',$orderId);
		$orderStatus = null;
		$arrItemStatus = array();
		if($orderItemCollection->count() > 0){			
			if($orderItemCollection->count() > 1)
			{
				foreach($orderItemCollection as $item){		
					$arrItemStatus[] = $item->getLbItemStatus();				
				}
			}else{
				$arrItemStatus[] = 'Transmitting';
			}			
		}
		$arrUnique = array_unique($arrItemStatus);
		if(count($arrUnique) == 1){
			switch($arrUnique[0]){
				case 'Backorder' :
						$orderStatus = 'logicbroker_sourcing/order/backorder';
				break;
				case 'Transmitting' :
						$orderStatus = 'logicbroker_sourcing/order/awaiting_transmission';
				break;
				case 'No Dropship' :
						$orderStatus = 'logicbroker_sourcing/order/sourcing_complete';
				break;
			}
		}elseif(count($arrUnique) > 1){
			if(in_array('Backorder',$arrUnique))
				$orderStatus = 'logicbroker_sourcing/order/backorder';
			else
				$orderStatus = 'logicbroker_sourcing/order/awaiting_transmission';		
		}			
		return $orderStatus;
	}
	
	public function setSourcingOrderStatus($data)
	{		
		$itemStatus = array();
		$itemCollection = $this->getCollection()->addFieldToFilter('item_order_id',$data['item_order_id']);		
		foreach($itemCollection as $items){		
			if($items->getItemId() != $data['item_id'])
			$itemStatus[] = $items->getLbItemStatus();
		}		
		$uniqueArray = array_unique($itemStatus);
		$orderCollection = Mage::getModel('sales/order')->Load($data['item_order_id']);
		switch($itemStatus)
		{
			case (count($uniqueArray) == 1 && $uniqueArray[0] == 'Cancelled' ) ://case : when all item status are cancelled
				$orderCollection->setStatus('canceled');       
				$orderCollection->setState('canceled');
				$orderCollection->addStatusToHistory('canceled', 'Order status changed to canceled', false);
				
				break;
			case in_array('Backorder',$itemStatus) :
					$orderCollection->setStatus(Mage::getStoreConfig(Logicbroker_Dropship360_Model_Observer::XML_PATH_LOGICBROKER_ORDER_BACKORDERED));       
					$orderCollection->setState('processing');
					$orderCollection->addStatusToHistory(Mage::getStoreConfig(Logicbroker_Dropship360_Model_Observer::XML_PATH_LOGICBROKER_ORDER_BACKORDERED), 'Order status changed to backorder', false);
					break;
			case (in_array('Transmitting', $uniqueArray)) :
				$orderCollection->setStatus(Mage::getStoreConfig(Logicbroker_Dropship360_Model_Observer::XML_PATH_LOGICBROKER_ORDER_AWAITING_TRANSMISSION));       
				$orderCollection->setState('processing');
				$orderCollection->addStatusToHistory(Mage::getStoreConfig(Logicbroker_Dropship360_Model_Observer::XML_PATH_LOGICBROKER_ORDER_AWAITING_TRANSMISSION), 'Order status changed to transmitting', false);
				break;			
					
			default : 
				$orderCollection->setStatus(Mage::getStoreConfig(Logicbroker_Dropship360_Model_Observer::XML_PATH_LOGICBROKER_ORDER_SOURCING_COMPLETE));       
				$orderCollection->setState('processing');
				$orderCollection->addStatusToHistory(Mage::getStoreConfig(Logicbroker_Dropship360_Model_Observer::XML_PATH_LOGICBROKER_ORDER_SOURCING_COMPLETE), 'Order status changed to sourcing complete', false);
				break;								
		}		
		try{
			$orderCollection->save();
			return true;
		}catch (Exception $e) {
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
			return false;
        }
	}	
}
	 