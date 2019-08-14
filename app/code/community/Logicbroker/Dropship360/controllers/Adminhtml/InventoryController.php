<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
 
class Logicbroker_Dropship360_Adminhtml_InventoryController extends Mage_Adminhtml_Controller_Action
{

	protected function _initAction() {
		$this->loadLayout()
			->_setActiveMenu('logicbroker/invemtory')
			->_addBreadcrumb(Mage::helper('adminhtml')->__('Inventory Manager'), Mage::helper('adminhtml')->__('Inventory Manager'));
		
		return $this;
	}   
 
	public function indexAction() {
		$this->_initAction()
			->renderLayout();
	}

	public function vendorsAction(){
		$this->loadLayout();
		$this->getLayout()->getBlock("vendors_product_tab")
		->setProductId($this->getRequest()->getParam('id'));
		$this->renderLayout();
	}
	
	public function vendorshistoryAction(){
		$this->loadLayout();
		$this->getLayout()->getBlock("vendors_product_tab_history")
		->setProductId($this->getRequest()->getParam('id'));
		$this->renderLayout();
	}
	
	/**
     * suppplier grid for AJAX request
     */
    public function gridAction() {
        $this->getResponse()->setBody(
                $this->getLayout()->createBlock('logicbroker/adminhtml_inventory_grid')->toHtml()
        );
    } 

    /**
     * Export vendor in csv format
     */
    public function exportCsvAction()
    {
    	$fileName   = 'vendor_inventory.csv';
    	$content    = $this->getLayout()->createBlock('logicbroker/adminhtml_inventory_grid')
    	->getCsvFile();
    
    	$this->_prepareDownloadResponse($fileName, $content);
    }
    
    /**
     * Export vendor in Excel format
     */
    public function exportXmlAction()
    {
    	$fileName   = 'vendor_inventory.xml';
    	$content    = $this->getLayout()->createBlock('logicbroker/adminhtml_inventory_grid')
    	->getExcelFile($fileName);
    
    	$this->_prepareDownloadResponse($fileName, $content);
    }
         
}
