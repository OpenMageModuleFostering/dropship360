<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_System_Config_Fieldset_Hint
    extends Mage_Adminhtml_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{
    protected $_template = 'logicbroker/system/config/fieldset/hint.phtml';

    /**
     * Render fieldset html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        
        return $this->toHtml();
    }
    
        
    
    public function getStoreList(){

    	return Mage::getModel('logicbroker/system_config_source_store')->toOptionArray();
      
    }

        

    
    
        
            
        

}
