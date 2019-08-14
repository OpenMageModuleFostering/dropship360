<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Fulfillment
 */
 
class Logicbroker_Fulfillment_Adminhtml_SourcingController extends Mage_Adminhtml_Controller_Action
{

	protected function _initAction() {
		$this->loadLayout()
			->_setActiveMenu('logicbroker/order_sourcing')
			->_addBreadcrumb(Mage::helper('adminhtml')->__('Order Sourcing'), Mage::helper('adminhtml')->__('Oreder Sourcing'));
		
		return $this;
	}   
 
	public function indexAction() {
		$this->_initAction()
			->renderLayout();
	}
        
	/**
     * sourcing grid
     */
    
	public function sourcinggridAction() {
		
		$this->getLayout()->createBlock('logicbroker/adminhtml_sourcing_grid')->toHtml();
			$this->loadLayout()->renderLayout();
		
	}
	
       
        public function editAction() {
		$id     = $this->getRequest()->getParam('lb_item_id');
		$model  = Mage::getModel('logicbroker/orderitems')->load($id);

		if ($model->getId()) {
			$data = Mage::getSingleton('adminhtml/session')->getFormData(true);
			if (!empty($data)) {
                                $model->setData($data);
                                
			}
            Mage::register('sourcing_data', $model);

			$this->loadLayout();
			$this->_setActiveMenu('logicbroker/order_sourcing');

			$this->_addBreadcrumb(Mage::helper('adminhtml')->__('Order Sourcing'), Mage::helper('adminhtml')->__('Order Sourcing'));
			$this->_addContent($this->getLayout()->createBlock('logicbroker/adminhtml_sourcing_edit'))
				->_addLeft($this->getLayout()->createBlock('logicbroker/adminhtml_sourcing_edit_tabs'));

			$this->renderLayout();
		} else {
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('logicbroker')->__('Sourcing does not exist'));
			$this->_redirect('*/*/sourcinggrid');
		}
	}
 

        
        public function saveAction() {
		if ($data = $this->getRequest()->getPost()) {
			
			$vendorCode = $data['lb_vendor_code'];
			$sku = $data['sku'];
	  		$model = Mage::getModel('logicbroker/orderitems');		
			if ($id = $this->getRequest()->getParam('lb_item_id')) {//the parameter name may be different
				$model->load($id);
			}
			
			if(!$data['lb_vendor_code']){
				Mage::getSingleton('adminhtml/session')->addError(Mage::helper('logicbroker')->__('Under Processing By Logicbroker Can Not Update %s Sku',$model->getSku()));
				$this->_redirect('*/*/sourcinggrid');
				return;
			}
			
			//echo $model->getCompanyId() .'=='. $companyid;
			//var_dump(strcmp($model->getCompanyId(),$companyid));
			//die();
			$arrData = $model->updateSourcingByUser(array('lb_vendor_code'=>$vendorCode,'product_sku'=>$sku));
			$model->addData($arrData);	
			
			
			try {
				$model->updateOrderStatus($model->getItemOrderId(),$model->getItemId());
                $model->save();
               	Mage::getSingleton('adminhtml/session')->setFormData(false);
				Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('logicbroker')->__('Sourcing Updated For %s',$model->getSku()));		

				if ($this->getRequest()->getParam('back')) {
					$this->_redirect('*/*/edit', array('lb_item_id' => $model->getId()));
					return;
				}
				$this->_redirect('*/*/sourcinggrid');
				return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
				$this->_redirect('*/*/edit', array('lb_item_id' => $model->getId(), '_current'=>true)); 
                return;
            }
        }
        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('logicbroker')->__('Unable to save sourcing'));
        $this->_redirect('*/*/sourcinggrid');
	}
        

	/**
	 * Export vendor in csv format
	 */
	public function exportCsvAction()
	{
		$fileName   = 'sourcing.csv';
		$content    = $this->getLayout()->createBlock('logicbroker/adminhtml_sourcing_grid')
		->getCsvFile();
	
		$this->_prepareDownloadResponse($fileName, $content);
	}
	
	/**
	 * Export vendor in Excel format
	 */
	public function exportXmlAction()
	{
		$fileName   = 'sourcing.xml';
		$content    = $this->getLayout()->createBlock('logicbroker/adminhtml_sourcing_grid')
		->getExcelFile($fileName);
	
		$this->_prepareDownloadResponse($fileName, $content);
	}
	
	
}
