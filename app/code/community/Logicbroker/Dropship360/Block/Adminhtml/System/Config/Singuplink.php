<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_System_Config_Singuplink extends Mage_Adminhtml_Block_System_Config_Form_Field
{
	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
	{
		$url = 'https://portal.logicbroker.com/';//Mage::helper('logicbroker')->getConfigObject('apiconfig/helpurl/link');
		$html = parent::_getElementHtml($element);
		$html .= "<a href='{$url}' target='_blank' title='logicbroker'>Sign up here</a>";
		return $html;
	}
}
