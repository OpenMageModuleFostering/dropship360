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
    
    
   
   
    
    public function getGroup($element = null)
    {
        if (is_null($element)) {
            $element = $this->getElement();
        }
        if ($element && $element->getGroup() instanceof Mage_Core_Model_Config_Element) {
            return $element->getGroup();
        }

        return new Mage_Core_Model_Config_Element('<config/>');
    }

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
       //print_r($element->getElement('zip'));
        $groupConfig = $this->getGroup($element)->asArray();
        $html .= $this->genrateHtml($groupConfig['fields'],$element);
        //print_r($element->getForm()->getElements());
        $elementHtmlObject = $element->getElementHtml();
        $elementOriginalData = $element->getOriginalData();
        if (isset($elementOriginalData['page_heading'])) {
            $this->setPageHeading($elementOriginalData['page_heading']);
        }
         if ($elementHtmlObject) {
            $this->setHtmlSupplier($elementHtmlObject);
        }
        
        $html .= $this->toHtml();
        return $html;
    }
    
    public function genrateHtml($fields,$element)
    {
        $html .= $this->_getHeaderHtml($element);
        $html .= $this->_getContentHtml($fields);
        $html .= $this->_getFooterHtml($element);
        
        return $html;
    }
    
    public function _getContentHtml($fields)
    {
        foreach($fields as $key=>$val)
        {
            if($val['frontend_type'] == 'text')
            {
             $html .=  $this->_getTextBoxHtml($key,$val);
            
            }
            
            if($val['frontend_type'] == 'select')
            {
             $html .=   $this->_getSelectBoxHtml($key,$val);
            }
        }
        return $html;
    }
    
    protected function _getHeaderHtml($element)
    {
        $html = '<h4 class="icon-head head-edit-form">'.$element->getLegend().'</h4>';
        $html.= '<fieldset class="config" id="'.$element->getHtmlId().'">';
        $html.= '<legend>'.$element->getLegend().'</legend>';

        // field label column
        $html.= '<table cellspacing="0"><colgroup class="label" /><colgroup class="value" />';
        $html.= '<tbody>';

        return $html;
        
    }

    protected function _getFooterHtml($element)
    {
        $html = '</tbody></table></fieldset>';
        return $html;
        
    }

    protected function _getConfigurationValue($val)
    {
        return isset($val) ? $val:'';
    }
    
    protected function _getTextBoxHtml($key,$val)
    {
        return '<tr id="row_'.$key.'"><td class="label"><label for="'.$key.'">'.$this->_getConfigurationValue($val['label']).'</label></td><td class="value"><input id="'.$key.'" name="'.$key.'" value="" class="input-text '.$this->_getConfigurationValue($val['validate']).'" type="text"></td><td class=""></td></tr>';
        
        /*  return '<div id="row_'.$key.'"><div style="200px;float:left;" class="label"><label style="display: block;width: 185px;padding-right: 15px;padding-top: 1px;"for="'.$key.'">'.$this->_getConfigurationValue($val['label']).'</label></div><div style="300px"class="value"><input style="274px" id="'.$key.'" name="'.$key.'" value="" class="input-text '.$this->_getConfigurationValue($val['validate']).'" type="text">
</div></div><div clear="both"></div>';*/
        
    }
    
    protected function _getSelectBoxHtml($key,$val)
    {
        $optionArray = Mage::getSingleton("adminhtml/system_config_source_country")->toOptionArray(); 
        
        foreach($optionArray as $code=>$label){
        $optionHtml .= '<option value = "'.$label['value'].'">'.$label['label'].'</option>';    
            
        }
        
        return '<tr id="row_'.$key.'"><td class="label"><label for="'.$key.'">'.$this->_getConfigurationValue($val['label']).'</label></td><td class="value"><select id="'.$key.'" name="'.$key.'" class=" select '.$this->_getConfigurationValue($val['validate']).'">'.$optionHtml.'</select></td><td class=""></td></tr>';
        /*return '<div id="row_'.$key.'"><div class="label"><label for="'.$key.'">'.$this->_getConfigurationValue($val['label']).'</label></div><div class="value"><select id="'.$key.'" name="'.$key.'" class="select '.$this->_getConfigurationValue($val['validate']).'">'.$optionHtml.'
</select>
</div></div>';*/
        
    }

}
