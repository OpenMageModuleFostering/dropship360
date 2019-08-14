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
class Logicbroker_Dropship360_Model_Api2_Inventory_Rest_Admin_V1 extends Logicbroker_Dropship360_Model_Api2_Inventory_Rest
{
	protected function _create(array $data)
	{
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
