<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2014 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml sales report page content block
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @author      Magento Core Team <core@magentocommerce.com>
 */

class Logicbroker_Dropship360_Block_Adminhtml_Reports_Staticemailgrid extends Mage_Core_Block_Template
{
	public function getTemplateFile()
	{
		$params = array('_relative'=>true);
		$area = $this->getArea();
		$package = 'default';
		if ($area) {
			$params['_area'] = $area;
			$params['_package'] = $package;
		}
		$templateName = Mage::getDesign()->getTemplateFilename($this->getTemplate(), $params);
		return $templateName;
	}
	
	public function getPeriodToOpen($updatedat){
		
		$currentDate = Mage::getModel('core/date')->date();
		$lastUpdateDate = Mage::app()->getLocale()->date($updatedat, Varien_Date::DATETIME_INTERNAL_FORMAT);
		$start_date = new DateTime($currentDate);
		$since_start = $start_date->diff(new DateTime($lastUpdateDate));
		return $since_start->d.' days '.$since_start->h.' hours '.$since_start->i.' minutes'  ;
	}
	public function formatDate($date){
		$format = Mage::app()->getLocale()->getDateTimeFormat(
                        Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM
                    );
		$date = Mage::app()->getLocale()
		->date($date, Varien_Date::DATETIME_INTERNAL_FORMAT)->toString($format);
		return $date;
	}
}
