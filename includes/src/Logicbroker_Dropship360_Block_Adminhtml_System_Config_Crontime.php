<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_System_Config_Crontime extends Mage_Adminhtml_Block_System_Config_Form_Field
{
	public function getName($name)
	{
		if (strpos($name, '[]') === false) {
			$name.= '[]';
		}
		return $name;
	}
	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
	{
		$element->addClass('select');

        $value_hrs = 0;
        $value_min = 0;

        if( $value = $element->getValue() ) {
            $values = explode(',', $value);
            if( is_array($values) && count($values) == 2 ) {
                $value_hrs = $values[0];
                $value_min = $values[1];
            }
        }

        $html = '<input type="hidden" id="' . $element->getHtmlId() . '" />';
        $html .= '<div style = "float: left;width: 100px;"><select name="'. $this->getName($element->getName()) . '" style="width:auto">'."\n";
        
        for( $i=-2;$i<24;$i++ ) {
            $hour = $i;
            if($i == -1 ) 
            	$html.= '<option value="'.$hour.'" '. ( ($value_hrs == $i) ? 'selected="selected"' : '' ) .'> Every-Hour </option>';
			else if($i == -2)
				$html.= '<option value="-2" selected="selected">Please select</option>';	
            else
            	$html.= '<option value="'.$hour.'" '. ( ($value_hrs == $i) ? 'selected="selected"' : '' ) .'>' . $hour . '</option>';
        }
        $html.= '</select>'."\n	<p class='note'><span>Hours</span></p> </div>";

        $html.= '<div style = "padding-left:15px;float: left;width: 100px;"><select name="'. $this->getName($element->getName()) . '" style="width:auto">'."\n";

        for( $i=-1;$i<60;$i++ ) {
            $hour = $i;
            if($i == -1 ) 
            	$html.= '<option value="'.$hour.'" '. ( ($value_min == $i) ? 'selected="selected"' : '' ) .'> Every-Min </option>';
            else
            	$html.= '<option value="'.$hour.'" '. ( ($value_min == $i) ? 'selected="selected"' : '' ) .'>' . $hour . '</option>';
        }
        $html.= '</select>'."\n <p class='note'><span>Minutes</span></p></div>";
        $html.= $element->getAfterElementHtml();
        return $html;
	}
}