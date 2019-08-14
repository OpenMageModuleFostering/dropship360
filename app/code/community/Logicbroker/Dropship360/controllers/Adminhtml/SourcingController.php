<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
 
class Logicbroker_Dropship360_Adminhtml_SourcingController extends Mage_Adminhtml_Controller_Action
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
			$orderId = $data['item_order_id'];
			
	  		$model = Mage::getModel('logicbroker/orderitems');		
			
			if($this->getRequest()->getParam('cancel') == 'item'){
				$model->load($this->getRequest()->getParam('lb_item_id'));
				//$data['lb_item_id'] = $this->getRequest()->getParam('lb_item_id');
				$model->setLbItemStatus('Cancelled');
				$model->setUpdatedBy('User');
				$is_success = $model->setSourcingOrderStatus($data);
				if(!$is_success){
					Mage::getSingleton('adminhtml/session')->addError(Mage::helper('logicbroker')->__('Item %s Cannot Cancelled ',$model->getSku()));
					$this->_redirect('*/*/sourcinggrid');
					return;
				}
				try{
					$model->save();
					Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('logicbroker')->__('Item %s Cancelled Successfully ',$model->getSku()));
					
				}catch(Exception $e){
					Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
				}
				$this->_redirect('*/*/sourcinggrid');
				return;	
			}
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
				$model->save();
                $model->updateOrderStatus($model->getItemOrderId(),$model->getItemId());
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
	
	public function saveSoapApiAction()
	{
	
		$paramsArray = $this->getRequest()->getParams();
		if(!empty($paramsArray['user_id']) && !empty($paramsArray['api_key'])){
			
			$data = array(
					'scope'         => 'default',
					'scope_id'    => '0',
					'path'       => 'logicbroker_integration/integration/apipassword',
					'value'     => $paramsArray['api_key'],
						
			);
		Mage::getModel('api/user')->load($paramsArray['user_id'])->setApiKey($paramsArray['api_key'])->save();
		//Mage::getModel('core/config_data')->setData($data)->save();
		$result['message'] = 'password save successfully';
		$result['success'] = 1;
		}else{
			$result['message'] = 'can not save password';
			$result['success'] = 1;
		}
		//$result = $validation->validation($paramsArray['groups']['integration']['fields']);
		
		$result = Mage::helper('core')->jsonEncode($result);
		Mage::app()->getResponse()->setBody($result);
	
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
