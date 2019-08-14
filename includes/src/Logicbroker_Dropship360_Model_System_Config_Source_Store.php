<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Model_System_Config_Source_Store 
{
    protected $_options;
    
    
    public function toOptionArray()
    {
    	$options = array();
        if (!$this->_options) {
            $this->_options = Mage::getResourceModel('core/store_collection')
                ->load();
            
            foreach ($this->_options as $val){
            	$options[] =array(
            			'label' => $val->getName().'-'.$val->getStoreId(),
            			'value' =>  $val->getStoreId()
            	);
            }
        }
        
        return $options;
    }
}

