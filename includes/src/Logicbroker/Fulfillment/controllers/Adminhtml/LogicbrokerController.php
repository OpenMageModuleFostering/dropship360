<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Fulfillment
 */
 
class Logicbroker_Fulfillment_Adminhtml_LogicbrokerController extends Mage_Adminhtml_Controller_Action
{

	protected function _initAction() {
		$this->loadLayout()
			->_setActiveMenu('logicbroker/suppliers')
			->_addBreadcrumb(Mage::helper('adminhtml')->__('Vendor Manager'), Mage::helper('adminhtml')->__('Vendor Manager'));
		
		return $this;
	}   
 
	public function indexAction() {
		$this->_initAction()
			->renderLayout();
	}
        
	/**
     * suppplier grid for AJAX request
     */
    
	public function sourcinggridAction() {
		
		$this->getLayout()->createBlock('logicbroker/adminhtml_sourcing_grid')->toHtml();
			$this->loadLayout()->renderLayout();
		
	}
	
    public function gridAction() {
        $this->getResponse()->setBody(
                $this->getLayout()->createBlock('logicbroker/adminhtml_logicbroker_grid')->toHtml()
        );
    }    
        public function editAction() {
		$id     = $this->getRequest()->getParam('vendor_id');
		$model  = Mage::getModel('logicbroker/supplier')->load($id);

		if ($model->getVendorId() || $id == 0) {
			$data = Mage::getSingleton('adminhtml/session')->getFormData(true);
			if (!empty($data)) {
                                $model->setData($data);
                                
			}
                        Mage::register('logicbroker_data', $model);

			$this->loadLayout();
			$this->_setActiveMenu('logicbroker/suppliers');

			$this->_addBreadcrumb(Mage::helper('adminhtml')->__('Vendor Manager'), Mage::helper('adminhtml')->__('Vendor Manager'));
			$this->getLayout()->getBlock('head')->setCanLoadExtJs(true);

			$this->_addContent($this->getLayout()->createBlock('logicbroker/adminhtml_logicbroker_edit'))
				->_addLeft($this->getLayout()->createBlock('logicbroker/adminhtml_logicbroker_edit_tabs'));

			$this->renderLayout();
		} else {
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('logicbroker')->__('Vendor does not exist'));
			$this->_redirect('*/*/');
		}
	}
 
	public function newAction()
	{
		$this->loadLayout();
		$this->_setActiveMenu('logicbroker/suppliers');
		$this->_addContent($this->getLayout()->createBlock('logicbroker/adminhtml_logicbroker_edit'))
				->_addLeft($this->getLayout()->createBlock('logicbroker/adminhtml_logicbroker_edit_tabs'));
		$this->renderLayout();

	}
        
        public function saveAction() {
		if ($data = $this->getRequest()->getPost()) {
			
			
	  		$model = Mage::getModel('logicbroker/supplier');		
			if ($id = $this->getRequest()->getParam('vendor_id')) {//the parameter name may be different
				$model->load($id);
			}
			$companyid = $this->getRequest()->getParam('company_id');
			$message = '';
			//echo $model->getCompanyId() .'=='. $companyid;
			//var_dump(strcmp($model->getCompanyId(),$companyid));
			//die();
			$result = array();
			if(empty($companyid) || strcmp($model->getCompanyId(),$companyid) == 0 )
				$validate = 0;
			else{
				$result = $model->validateCompany($companyid,$data);
				$validate = $result['validate'];
				$data = $result['data'];
				if(!$validate){
					$model->load($data['id']);
				}
				
			}   
			$message = ($result['message']) ? 'Vendor Recovered Successfully -'.$data['company_id'] : 'vendor was successfully saved';
			$model->addData($data);	
			
			
			try {
                            if(!empty($data['addnewoption'])){
                                $model->setData('magento_vendor_code',strtolower($data['addnewoption']));
                            }
				//validate compny id as unique
				
				
				if($validate == 1){
					Mage::getSingleton('adminhtml/session')->addError(Mage::helper('logicbroker')->__('Duplicate Company ID'));
					Mage::getSingleton('adminhtml/session')->setFormData($data);
				    $this->_redirect('*/*/edit', array('vendor_id' => $model->getVendorId()));
					return;
				}
				//$model->getFtpPassword = md5($model->getFtpPassword());
				//$model->setData('ftp_password',$model->getFtpPassword);
				$model->save();
				
                                if(!empty($data['addnewoption'])){
                                Mage::getModel('logicbroker/logicbroker')->createOptionValueOnSave($model->getMagentoVendorCode());
                            }
                Mage::getModel('logicbroker/ranking')->setVendorRanking($data,$model);
				Mage::getSingleton('adminhtml/session')->setFormData(false);
				Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('logicbroker')->__($message));		

				if ($this->getRequest()->getParam('back')) {
					$this->_redirect('*/*/edit', array('vendor_id' => $model->getVendorId()));
					return;
				}
				$this->_redirect('*/*/');
				return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
				$this->_redirect('*/*/edit', array('vendor_id'=>$model->getVendorId(), '_current'=>true)); 
                return;
            }
        }
        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('logicbroker')->__('Unable to find vendor to save'));
        $this->_redirect('*/*/');
	}
        
        public function deleteAction()
    {
       
        if ($id = $this->getRequest()->getParam('vendor_id')) {
            try {
       
                $model = Mage::getModel('logicbroker/supplier');
                $model->load($id);
       			$model->setData('status','deleted');
                $model->save();
                Mage::getModel('logicbroker/ranking')->rearrangeRank($id);
                Mage::getModel('logicbroker/ranking')->load($id,'lb_vendor_id')->setRanking('')->setIsDropship('no')->setIsActive('no')->save();
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('logicbroker')->__('The vendor has been deleted.'));
                $this->_redirect('*/*/');
                return;

            } catch (Exception $e) {
                
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                
                $this->_redirect('*/*/edit', array('vendor_id' => $id));
                return;
            }
        }
        
        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('logicbroker')->__('Unable to find a vendor to delete.'));
        
        $this->_redirect('*/*/');
    }

 	
	/**
     * Export vendor in csv format
     */
    public function exportCsvAction()
    {
        $fileName   = 'vendor.csv';
        $content    = $this->getLayout()->createBlock('logicbroker/adminhtml_logicbroker_grid')
            ->getCsvFile();

        $this->_prepareDownloadResponse($fileName, $content);
    }

    /**
     * Export vendor in Excel format
     */
    public function exportXmlAction()
    {
        $fileName   = 'vendor.xml';
        $content    = $this->getLayout()->createBlock('logicbroker/adminhtml_logicbroker_grid')
            ->getExcelFile($fileName);

        $this->_prepareDownloadResponse($fileName, $content);
    }
        
        public function validateajaxrequestAction()
        {
        
        $paramsArray = $this->getRequest()->getParams();
        $validation = Mage::getModel('logicbroker/logicbroker');
        $result = $validation->validation($paramsArray['groups']['integration']['fields']);
        //$result['message'] = 'JAI hohohoh';
        $result = Mage::helper('core')->jsonEncode($result);
        Mage::app()->getResponse()->setBody($result);
            
        }
	
}
