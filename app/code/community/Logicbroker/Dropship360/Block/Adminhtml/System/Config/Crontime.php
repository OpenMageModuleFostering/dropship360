<?php
class Logicbroker_Dropship360_Block_Adminhtml_System_Config_Crontime extends Mage_Adminhtml_Block_System_Config_Form_Field
{
	public function getName($name)
	{
		//$name = parent::getName();
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
        $value_sec = 0;

        if( $value = $element->getValue() ) {
            $values = explode(',', $value);
            if( is_array($values) && count($values) == 3 ) {
                $value_hrs = $values[0];
                $value_min = $values[1];
                $value_sec = $values[2];
            }
        }

        $html = '<input type="hidden" id="' . $element->getHtmlId() . '" />';
        $html .= '<select name="'. $this->getName($element->getName()) . '" '.$element->serialize($this->getHtmlAttributes()).' style="width:auto">'."\n";
        
        for( $i=-2;$i<24;$i++ ) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            if($i == -1 ) 
            	$html.= '<option value="'.$hour.'" '. ( ($value_hrs == $i) ? 'selected="selected"' : '' ) .'> Every-Hour </option>';
			else if($i == -2)
				$html.= '<option value="-2" selected="selected">Please select</option>';	
            else
            	$html.= '<option value="'.$hour.'" '. ( ($value_hrs == $i) ? 'selected="selected"' : '' ) .'>' . $hour . '</option>';
        }
        $html.= '</select>'."\n";

        $html.= '&nbsp;:&nbsp;<select name="'. $this->getName($element->getName()) . '" '.$element->serialize($this->getHtmlAttributes()).' style="width:auto">'."\n";
        //$html.= '<option value="*" '. ( ($value_min == '*') ? 'selected="selected"' : '' ) .'>*</option>';
        for( $i=-1;$i<60;$i++ ) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            if($i == -1 ) 
            	$html.= '<option value="'.$hour.'" '. ( ($value_min == $i) ? 'selected="selected"' : '' ) .'> Every-Min </option>';
            else
            	$html.= '<option value="'.$hour.'" '. ( ($value_min == $i) ? 'selected="selected"' : '' ) .'>' . $hour . '</option>';
        }
        $html.= '</select>'."\n";

        $html.= '&nbsp;:&nbsp;<select name="'. $this->getName($element->getName()) . '" '.$element->serialize($this->getHtmlAttributes()).' style="width:auto">'."\n";
        //$html.= '<option value="*" '. ( ($value_sec == '*') ? 'selected="selected"' : '' ) .'>*</option>';
        for( $i=-1;$i<60;$i++ ) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            if($i == -1 ) 
            	$html.= '<option value="'.$hour.'" '. ( ($value_sec == $i) ? 'selected="selected"' : '' ) .'> Every-Sec </option>';
            else
            	$html.= '<option value="'.$hour.'" '. ( ($value_sec == $i) ? 'selected="selected"' : '' ) .'>' . $hour . '</option>';
        }
        $html.= '</select>'."\n";
        $html.= $element->getAfterElementHtml();
        return $html;
	}
}