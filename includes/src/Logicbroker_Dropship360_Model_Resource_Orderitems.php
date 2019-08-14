<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Resource_Orderitems extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init("dropship360/orderitems", "id");
    }
    public function saveOrderItems($itemData,$orderObj,$crontype)
    {
    	try {
    		$adapter = $this->_getWriteAdapter();
    		$adapter->beginTransaction();
    		foreach ($itemData as $key => $item) {
    			$condition = array(
    					'item_id = ?' => (int) $key,
    			);
    			unset($item['updateInventory']);
    			unset($item['qtyInvoiced']);
    			$adapter->update($this->getMainTable(),$item,$condition);
    		}
    		$adapter->commit();
    		foreach ($itemData as $key => $item) {
    			if($item ['updateInventory'])
    				Mage::getModel ( 'dropship360/orderitems' )->updateLbVendorInvenory ( $item['lb_vendor_code'],$item['sku'], $item['qtyInvoiced']);
    			if ($item['lb_item_status'] == Logicbroker_Dropship360_Helper_Data::LOGICBROKER_ITEM_STATUS_TRANSMITTING)
    				Mage::getModel('dropship360/logicbroker')->setupNotification();;
    			$this->saveOrderItemsComments($item,$orderObj);
    		}
    	} catch (Mage_Core_Exception $e) {
    		$adapter->rollBack();
    		throw $e;
    	} catch (Exception $e){
    		$adapter->rollBack();
    		Mage::logException($e);
    		Mage::helper('dropship360')->genrateLog(0,null,null,'Section :Error In saving order item data: '.$e->getMessage().' for orderid : '.$orderObj->getEntityId());
    		Mage::getModel('dropship360/ordersourcing')->sourcingCompleted($crontype);
    	}
    }
    
    protected function saveOrderItemsComments($itemData,$orderObj){
    	try {

    		$orderObj->addStatusHistoryComment($itemData['sku'].': Item status changed to '.$itemData['lb_item_status']);
    		$orderObj->save();
    	} catch (Exception $e) {
    		throw $e;
    	}
    	
    }
}