<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Block_Adminhtml_Reports_Activitymonitor_Periodopen extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{

	/**
     * Renders column
     *
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
    	
    	$currentDate = Mage::getModel('core/date')->date();
    	$lastUpdateDate = Mage::app()->getLocale()->date($row->getData('updated_at'), Varien_Date::DATETIME_INTERNAL_FORMAT);
    	$start_date = new DateTime($currentDate);
   		$since_start = $start_date->diff(new DateTime($lastUpdateDate));
    	return $since_start->d.' days '.$since_start->h.' hours '.$since_start->i.' minutes'  ;
    	 
    }
}
