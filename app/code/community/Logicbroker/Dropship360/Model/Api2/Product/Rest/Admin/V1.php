<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Catalog
 * @copyright   Copyright (c) 2013 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * API2 for catalog_product (Admin)
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
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
			//print_r($e->getTrace());
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
