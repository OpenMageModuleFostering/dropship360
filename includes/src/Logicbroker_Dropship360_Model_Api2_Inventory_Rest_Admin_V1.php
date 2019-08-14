<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360

 */
class Logicbroker_Dropship360_Model_Api2_Inventory_Rest_Admin_V1 extends Logicbroker_Dropship360_Model_Api2_Inventory_Rest
{
	protected function _create(array $data)
	{
		if(Mage::helper('logicbroker')->isProcessRunning('bulk_assign')){
			$message = 'Bulk product setup is currently running hence cannot run REST import';
			echo $message;
			//Mage::log($message, null, 'logicbroker_log_report.log');
			die;
		}
		$requestData = array_chunk($data['vendordata'], 1, true);
		try {
			foreach($requestData as $chunkData)
			{
				$processedData['vendordata'] = $chunkData;
				$result[] = Mage::getModel('logicbroker/inventory')->prepareInventoryTable($processedData);
			}
			 
			foreach($result as $row){
				foreach($row as $vendor=>$msg){
					echo $msg.' '.$vendor.'<br>';
				}
			}
			die();
		} catch (Exception $e) {
			die($e->getMessage());
		}	
	}
}
