<?php
/**
 * Adminhtml System Store Model
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @author      Magento Core Team <core@magentocommerce.com>
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

