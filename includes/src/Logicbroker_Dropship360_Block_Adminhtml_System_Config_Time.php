<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_System_Config_Time extends Mage_Adminhtml_Block_System_Config_Form_Field
{
	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
	{
		$html = '';
		if(strstr($element->getName(),'schedule_backorder') ){
		$selectedHourValue = Mage::getStoreConfig('logicbroker_sourcing/cron_settings/schedule_backorder_hour');
		$selectedMinValue = Mage::getStoreConfig('logicbroker_sourcing/cron_settings/schedule_backorder_min');
		$idHour = $element->getHtmlId().'_hour';
		$nameHour = str_replace("schedule_backorder","schedule_backorder_hour",$element->getName());
		$idMin = $element->getHtmlId().'_min';
		$nameMin = str_replace("schedule_backorder","schedule_backorder_min",$element->getName());
		}else {
			$selectedHourValue = Mage::getStoreConfig('logicbroker_sourcing/cron_settings/schedule_sourcing_hour');
			$selectedMinValue = Mage::getStoreConfig('logicbroker_sourcing/cron_settings/schedule_sourcing_min');
			$idHour = $element->getHtmlId().'_hour';
			$nameHour = str_replace("schedule_sourcing","schedule_sourcing_hour",$element->getName());
			$idMin = $element->getHtmlId().'_min';
			$nameMin = str_replace("schedule_sourcing","schedule_sourcing_min",$element->getName());
			
		}
		
		$html .= '<div style="float:left;width:100px"><select onchange = "onChangeHour'.$idHour.'(this.value)" style="width:109px" id="'.$idHour.'" name="'.$nameHour.'" class=" select">';
		for( $i=0;$i<=24;$i++ ) {
			$isSelected = ($selectedHourValue == $i) ? 'selected="selected"':'';
			$html .= "<option value='{$i}'{$isSelected}>{$i}</option>";
		}
		
		$html .= '</select>';
		$html .= '<p class="note"><span>Hours.</span></p></div>';
		//$html .= '&nbsp;';
		$html .= '<div style="float:right;width:100px"><select style="width:109px" id="'.$idMin.'" name="'.$nameMin.'" class=" select">';
		
		$j = 0;
		for( $i=10;$i<=60;$i++ ) {
			
			if($i >= $j+10 ){
				$isSelected = ($selectedMinValue == $i) ? 'selected="selected"':'';
				$html .= "<option value='{$i}'{$isSelected}>{$i}</option>";
				$j = $j+10;
			}
		}
		
		$html .= '</select>';
		$html .= '<p class="note"><span>Minutes.</span></p></div>';
		$html .= '<script type="text/javascript">//<![CDATA[
        function onChangeHour'.$idHour.'(value){
				
				var x = document.getElementById("'.$idMin.'");
			if(value > 0){
				var options = $$("select#'.$idMin.' option");
				var len = options.length;
				for (var i = 0; i < len; i++) {
				    if(options[i].value == 0)
						return;
				}
				var option = document.createElement("option");
				option.text = "0";
				option.value = "0";
				x.add(option,0);
			}else
				{
					//x.remove(0); // Disabled this for Jira ticket LBN-769
				}
			}	
        //]]></script>';
		return $html;
	}
}