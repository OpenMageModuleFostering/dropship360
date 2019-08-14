<?php
/**
 * Logicbroker
 *

 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Model_System_Config_Source_Ranktype
{
    public function toOptionArray($addEmpty = true)
    {
        
 		return array(
            array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--')),
 			array('value' => 'default', 'label' => Mage::helper('logicbroker')->__('Ranked Based')),
 			array('value' => 'cost', 'label' => Mage::helper('logicbroker')->__('Cost Based'))
 			
 				);
    }
}
