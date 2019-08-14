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

class Logicbroker_Dropship360_Block_Adminhtml_Reports_Activitymonitor extends Mage_Adminhtml_Block_Widget_Grid_Container
{
	protected $_divWidth = 400;
	protected $_percent = 100;

    public function __construct()
    {
        $this->_controller = 'adminhtml_reports_activitymonitor';
        $this->_blockGroup = 'dropship360';
        $this->_headerText = Mage::helper('reports')->__('Business Activity Monitoring');
        parent::__construct();
        $this->_removeButton('add');
        $this->addButton('filter_form_refresh', array(
        		'label'     => Mage::helper('reports')->__('Refresh'),
        		'onclick'   => 'filterFormRefresh()'
        ));
        $this->addButton('filter_form_submit', array(
            'label'     => Mage::helper('reports')->__('Save Config'),
            'onclick'   => 'filterFormSubmit()'
        ));
        
    }

    public function getFilterUrl()
    {
        $this->getRequest()->setParam('filter', null);
        return $this->getUrl('*/*/activitymonitor', array('_current' => true));
    }
    
    public function getRefreshUrl()
    {
    	$this->getRequest()->setParam('refresh', null);
    	return $this->getUrl('*/*/refresh', array('_current' => true));
    }
    
    
 
    public function getHtmlElementName()
	{
    	return array('input_monitor_order','input_open_monitor','input_transmitting_filter','input_sentosup_filter');    
    }
    
    public function getGraphData(){
    	
    	$graphData = $this->getData();
    	if(!isset($graphData['dropshipStatus']) && !isset($graphData['totalDropshipOrder']))
    		return;
    	$red = ($graphData['totalDropshipOrder'] > 0 ) ? round(($graphData['dropshipStatus'] / $graphData['totalDropshipOrder']) * $this->_percent) : 0;
    	$green = $this->_percent - $red;

    	return $this->calculateDivWidth($red,$green);
    }
    
    protected function calculateDivWidth($red,$green){
    	
    	$divRed = round(($red/$this->_percent) * $this->_divWidth);
    	$divGreen = $this->_divWidth - $divRed;
    	return array('divred'=>$divRed,'divgreen'=> $divGreen,'redper'=> $red,'greenper'=> $green);
    	
    }
    
    protected function updateReportData($redper){
    	
    	if($this->getRequest()->getParam('filter'))
    	$reportData = Mage::helper('adminhtml')->prepareFilterString($this->getRequest()->getParam('filter'));
    	else
    	$reportData = Mage::getModel('dropship360/report')->getActivityReportData();
    	
    	$reportData['notificationPer'] = $redper;
    	Mage::getModel('dropship360/report')->saveActivityData($reportData);
    }
}
