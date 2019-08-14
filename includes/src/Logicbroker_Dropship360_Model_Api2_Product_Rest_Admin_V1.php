<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360

 */
class Logicbroker_Dropship360_Model_Api2_Product_Rest_Admin_V1 extends Logicbroker_Dropship360_Model_Api2_Product_Rest
{
	protected function _create(array $data)
	{
		//request data parse in to chunks 
		$requestData = array_chunk($data['productdata'], 1, true);	
		try {
			$time_start = now(); 
			foreach($requestData as $chunkData)
			{
				$processedData['productdata'] = $chunkData;
				$result[] = Mage::getModel('logicbroker/productimport')->_init()->processData($processedData);
			}
			$time_end = now();
			echo 'Start Time = '.$time_start;
			echo '<hr>';
			echo 'End Time = '.$time_end;
			echo '<hr>'; 
		}catch (Exception $e) {
				echo $e->getMessage();
		}catch (Mage_Core_Exception $e) {
				$this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
		} catch (Exception $e) {
				$this->_critical(self::RESOURCE_UNKNOWN_ERROR);
		}
		foreach($result as $row){
			foreach($row as $vendor=>$msg){
				echo '<br>'.$vendor.' ';
				foreach($msg as $err){
					echo $err;
				}			
			}
		}
		die();
	}
}
