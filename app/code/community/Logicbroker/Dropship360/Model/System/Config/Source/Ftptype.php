<?php
/**
 * Logicbroker
 *

 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Model_System_Config_Source_Ftptype
{
    public function toOptionArray($addEmpty = true)
    {
        
 		return array(
            array('value' => '', 'label' => Mage::helper('dropship360')->__('--Please Select--')),
 			array('value' => 'ftp', 'label' => Mage::helper('dropship360')->__('FTP'))
 		);
    }
}
