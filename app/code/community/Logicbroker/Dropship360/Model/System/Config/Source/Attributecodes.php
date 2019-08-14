<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Attributecode
 *
 * @author shubhs
 */

class Logicbroker_Dropship360_Model_System_Config_Source_Attributecodes extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    public function getAllOptions()
    {
    	$vendorModel = Mage::getModel('logicbroker/ranking')->getCollection();
    	$options = array();
    	if($vendorModel->count() > 0 ){
    		foreach ($vendorModel as $vendor) {
    			$options[] = array(
    					'label' => $vendor->getLbVendorName(),
    					'value' => $vendor->getLbVendorCode()
    			);
    		}
    	}
    	array_unshift($options,array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--'))
    	);
    
        if (!$this->_options) {
        	$this->_options = $options;
            
        }
        
        return $this->_options;
    }
}


