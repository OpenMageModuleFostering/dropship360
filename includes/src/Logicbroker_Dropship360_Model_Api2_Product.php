<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360

 */
class Logicbroker_Dropship360_Model_Api2_Product extends Mage_Api2_Model_Resource
{
	public function getAvailableAttributes($userType, $operation)
    {
    	return array (
			'productdata' => 'productdata'
		);	
    }

}
