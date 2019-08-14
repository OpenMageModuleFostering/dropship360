<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
 
class Logicbroker_Dropship360_Adminhtml_ReportController extends Mage_Adminhtml_Controller_Action
{

	protected function _initAction() {
		$this->loadLayout()
			->_setActiveMenu('logicbroker/bar_report')
			->_addBreadcrumb(Mage::helper('adminhtml')->__('Report'), Mage::helper('adminhtml')->__('Report'));
		
		return $this;
	}   
 
	public function indexAction() {
		$this->loadLayout();
		$this->_initAction()->_title($this->__('Activity Monitor'));
		$this->renderLayout();
	}
	
	protected function checkRefreshRequest(){
		
		$result = true;
		if(Mage::getSingleton('adminhtml/session')->getCounter())
		{
		$result = ($this->getRequest()->getParam('counter') == Mage::getSingleton('adminhtml/session')->getCounter()) ? false : true; 
		Mage::getSingleton('adminhtml/session')->unsCounter();
		}else
		{
			Mage::getSingleton('adminhtml/session')->setCounter($this->getRequest()->getParam('counter'));
		}
		
		return $result;
		
	}
	public function activitymonitorAction() {
		$this->_initAction()->_title($this->__('Activity Monitor'));
		$formData = array();
		
		if($this->getRequest()->getParam('refresh')){
			
			if(!$this->checkRefreshRequest())
			{
				Mage::getSingleton('adminhtml/session')->unsCounter();
				$this->_redirect('*/*/activitymonitor');
				return;
			}
			$formData = Mage::helper('adminhtml')->prepareFilterString($this->getRequest()->getParam('refresh'));
			$this->_prepareReportData($formData);
			Mage::getSingleton('adminhtml/session')->setFormData($formData);
			Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('logicbroker')->__('Report Refreshed Successfully '));
			$this->renderLayout();
			return $this;
		}
		
		if($this->getRequest()->getParam('filter')){
		$formData = Mage::helper('adminhtml')->prepareFilterString($this->getRequest()->getParam('filter'));
		//print_r($data);
		//die();
		$formData['email'] = rtrim($formData['email'],','); 
		Mage::getModel('logicbroker/report')->saveReportData('business_activity_monitor', $formData);
		$this->_prepareReportData($formData);
		Mage::getSingleton('adminhtml/session')->setFormData($formData);
		Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('logicbroker')->__('Report Data Save Successfully '));
		$this->_redirect('*/*/activitymonitor');
		}else
		{
			$formData = Mage::getModel('logicbroker/report')->getActivityReportData();
			if(is_array($formData) && !empty($formData)){
				$this->_prepareReportData($formData);
				Mage::getSingleton('adminhtml/session')->setFormData($formData);
			}
			
		}
		
		$this->renderLayout();
		return $this;
	}
	
	
	protected function _prepareReportData($data = array()){
		
		$collection = Mage::getModel('logicbroker/report')->activityReportCollection($data)->getBlockGraphData();
		Mage::register('activity_report_collection', $collection['collection']);
		$this->getLayout()->getBlock('report.activitymonitor')->setData(array('totalDropshipOrder'=>$collection['totalDropshipOrder'],'dropshipStatus'=> $collection['dropshipStatus']));
	}
	
	/**
	 * Prepare block for chooser
	 *
	 * @return void
	 */
	public function chooserAction()
	{
		$block = $this->getLayout()->createBlock('logicbroker/adminhtml_reports_activitymonitor_email', 'adminhtml_chooser_email');
	
		if ($block) {
			$this->getResponse()->setBody($block->toHtml());
		}
	
	}
	
	
}
