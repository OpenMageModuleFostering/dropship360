<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Reports_Form_Timestatus extends Varien_Data_Form_Element_Abstract {

	protected $_element;
 
 public function getElementHtml()
 {
 	$required = ($this->getRequired()) ? 'required-entry' : "";
 	$options = '<option value="">Please Select</option>'."\n";
 	$selectOption = $this->getOptions();
 	$inputname = trim($this->getInputname());
 	$selectValue = ($this->getSelectvalue()) ? $this->getSelectvalue() : '';
 	$inputValue = ($this->getInputvalue()) ? $this->getInputvalue() : '';
 	$display = ($inputValue) ? 'display:block;' : 'display:none;';
	$classInputbox = ($inputValue) ? 'required-entry validate-digits validate-number-range number-range-1-999' : '';
 	
 	foreach($selectOption as $value=>$label){
 		$isSelect = ($value == $selectValue) ? 'selected="selected"' : '';
 		$options .= '<option value="'.$value.'" '.$isSelect.'>'.$label.'</option>'."\n";
 	}
 	
 	$html = '<div style = "float: left;"><select name= "'.$this->getName().'" id="'.$this->getId().'" title="'.$this->getTitle().'" class="select '.$required.'" onchange = showinputbox("'.$inputname.'",this)>'.$options.'</select></div>';
 	$html .= '<div id = "input_box_'.$inputname.'" style = "padding-left:15px;float: left;" class = "'.$inputname.'#'.$this->getName().'" ><input maxlength = "3" name="'.$inputname.'" id="'.$inputname.'" value="'.$inputValue.'" type="text" class="'.$classInputbox.' input-text" style="'.$display.'width:110px !important">';
	$html .= '<input name="'.$inputname.'_post" id="'.$inputname.'_post" type= "hidden" ></div>';
 	return $html;
 
 }
 }
