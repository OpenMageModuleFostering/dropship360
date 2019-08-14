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
            array('value' => '', 'label' => Mage::helper('dropship360')->__('--Please Select--')),
 			array('value' => 'default', 'label' => Mage::helper('dropship360')->__('Ranked Based')),
 			array('value' => 'cost', 'label' => Mage::helper('dropship360')->__('Cost Based'))
 			
 				);
    }
}
