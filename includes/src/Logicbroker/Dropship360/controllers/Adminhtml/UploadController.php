<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
 
class Logicbroker_Dropship360_Adminhtml_UploadController extends Mage_Adminhtml_Controller_Action
{

	protected function _initAction()
	{
		$this->loadLayout()
			->_setActiveMenu('logicbroker/upload_vendor_product')
			->_addBreadcrumb(Mage::helper('adminhtml')->__('logicbroker'), Mage::helper('adminhtml')->__('Supplier Inventory'));
		return $this;
	}   
 
	public function indexAction()
    {
        $maxUploadSize = Mage::helper('importexport')->getMaxUploadSize();
        $this->_getSession()->addNotice(
		$this->__('Total size of uploadable files must not exceed %s', $maxUploadSize)
        );
        $this->_initAction()
            ->_title($this->__('Supplier Inventory'))
            ->_addBreadcrumb($this->__('Logicbroker'), $this->__('Supplier Inventory'));
        $this->renderLayout();
    }   
	/**
     * sourcing grid
     */
    public function gridAction()
    {
    	$this->getResponse()->setBody(
    			$this->getLayout()->createBlock('logicbroker/adminhtml_inventory_grid')->toHtml()
    	);
    }
	public function uploadFileAction()
	{
		$data = $this->getRequest()->getPost();
		$productSetupMode = ($data['productsetupmode']) ? $data['productsetupmode'] : 0;
		if($productSetupMode)
		{
			$redirectUrl = '*/adminhtml_ranking/index'; 
		}
		else
		{
			$redirectUrl = '*/*/index';
		}
		if(Mage::helper('logicbroker')->isProcessRunning('bulk_assign')){
			$this->_getSession()->addError($this->__('Bulk product setup is currently running please try again later'));
			$this->_redirect($redirectUrl);
			return;
		}
		if ($data) {
			try {
				$import = Mage::getModel('logicbroker/uploadvendor');
				$validationResult = $import->setData($data)->uploadSource();
				if(!$validationResult){
					$this->initialize();
					$import->parseCsv(Mage::registry('file_name'),$data['vendor']);
					$this->_getSession()->addSuccess(Mage::helper('logicbroker')->__('File upload successfully '));
					$this->finalize();
				}else{
					$this->_getSession()->addError(Mage::helper('logicbroker')->__('File cannot be uploaded '));
				}
			} catch (Exception $e) {
				$this->_getSession()->addError($e->getMessage());
				$this->_redirect($redirectUrl);
			}		
		} elseif ($this->getRequest()->isPost() && empty($_FILES)) {
			$this->_getSession()->addError($this->__('File was not uploaded'));			
		} else {
			$this->_getSession()->addError($this->__('Data is invalid or file is not uploaded'));
		}
		$this->_redirect($redirectUrl);
	}
	
	protected function initialize(){
		Mage::helper('logicbroker')->startProcess('manual_upload');
	}
	
	protected function finalize(){
		Mage::helper('logicbroker')->finishProcess('manual_upload');
	}
	
	public function vendorsuploadhistoryAction()
	{
		$this->loadLayout();
		$this->getLayout()->getBlock("vendors_product_upload_history");
		$this->renderLayout();
	}
	
       
	public function editAction() 
	{
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
 

	public function validateftpconnectionAction()
	{
		$paramsArray = $this->getRequest()->getParams();
		$validateConnection = Mage::getModel('logicbroker/uploadvendor');
		$result = $validateConnection->testFtpConnection($paramsArray['groups']['cron_settings_upload']['fields']);
		$result = Mage::helper('core')->jsonEncode($result);
		Mage::app()->getResponse()->setBody($result);
	
	}
	
    public function saveAction() 
	{
		if ($data = $this->getRequest()->getPost()) {
			$vendorCode = $data['lb_vendor_code'];
			$sku = $data['sku'];
	  		$model = Mage::getModel('logicbroker/orderitems');		
			
			if($this->getRequest()->getParam('cancel') == 'item'){
				$model->load($this->getRequest()->getParam('lb_item_id'));
				$model->setLbItemStatus('Cancelled');
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
			Mage::getModel('core/config_data')->setData($data)->save();
			$result['message'] = 'password save successfully';
		}else{
			$result['message'] = 'can not save password';

		}
		$result['success'] = 1;
		$result = Mage::helper('core')->jsonEncode($result);
		Mage::app()->getResponse()->setBody($result);
	
	}

	/**
	 * download vendor product import file in csv format
	 */
	public function DownloadAction()
	{
		$paramsArray = $this->getRequest()->getParams();
		$isProductSetupMode = (isset($paramsArray['isproductsetupmode']) && $paramsArray['isproductsetupmode']) ? true : false;
		$type = ($isProductSetupMode) ? 'setup' : 'upload';
		$fileName   = 'logicbroker_supplier_product_'.$type.'.csv';
		$content = Mage::getModel('logicbroker/uploadvendor')->getCsvFile($isProductSetupMode);
		$this->_prepareDownloadResponse($fileName, $content);
		$this->_redirect('*/*/index');
		
	}
	
	/* Bulk assignment vendor code to all product */

	protected function _initSystem()
	{
		$this->_title($this->__('logicbroker'))
		->_title($this->__('Bulk vendor assignment'));
		$vendorCode = $this->getRequest()->getParam('lb_vendor_code');
		if (!$vendorCode) {
			Mage::getSingleton('adminhtml/session')->addError(
			$this->__('Please select a supplier code'));
			$this->_redirect('*/*');
			return false;
		}
		Mage::register('bulk_vendor_Code', $vendorCode);
		return $this;
	}
	public function runAction()
	{
		$this->_initSystem();
		$this->loadLayout();
		$this->renderLayout();
	}
	public function batchRunAction()
	{
		if ($this->getRequest()->isPost()) {
			$vendorCode = $this->getRequest()->getPost('vendor_code');
			$rowIds  = $this->getRequest()->getPost('rows');
			if (!is_array($rowIds) || count($rowIds) < 1) {
				return;
			}
			$vendorName = Mage::getModel('logicbroker/ranking')->load($vendorCode,'lb_vendor_code');
			$errors = array();
			$saved  = 0;
			$skuError = array();
			$skuSuccuess = array();
			if(!Mage::getSingleton('adminhtml/session')->getTerminateExecution()){
			Mage::helper('logicbroker')->startProcess('bulk_assign');
			}else
			{
				return;
			}
				foreach($rowIds as $sku){
				$collection = Mage::getModel('logicbroker/inventory')->getCollection()->addFieldToFilter('product_sku',$sku)->addFieldToFilter('lb_vendor_code',$vendorCode);
				$inventoryId = ($collection->getSize() > 0) ? $collection->getFirstItem()->getId() : '';
				if(!$inventoryId)
				{
				$inventoryCollection = Mage::getModel('logicbroker/inventory');
				$inventoryCollection->setLbVendorCode($vendorCode);
				$inventoryCollection->setLbVendorName(($vendorName) ? $vendorName->getLbVendorName() : '');
				$inventoryCollection->setProductSku($sku);
				$inventoryCollection->setLbVendorSku($sku);
				$inventoryCollection->setStock(0);
				$inventoryCollection->setCost(0);
				$inventoryCollection->setUpdatedAt(now());
				$inventoryCollection->setCreatedAt(now());
				try {
					$inventoryCollection->save();
					Mage::getModel('logicbroker/inventory')->_saveInventoryLog('add',array('lb_vendor_code'=>$vendorCode,'lb_vendor_name'=>($vendorName) ? $vendorName->getLbVendorName() : '','product_sku'=>$sku,'cost'=>0,'stock'=>0,'updated_by'=>'bulk_setup'));
					$saved ++;
					$skuSuccuess[] = $sku;
				} catch (Exception $e) {
					$errors[] = $e->getMessage();
					$skuError[] = $sku;
					continue;
				}
				}else
				{
					$errors[] = Mage::helper('logicbroker')->__('Skip %s as supplier %s already assigned.',$sku,$vendorCode);
					$skuError[] = $sku;
					continue;
				}
				}
	$result = array(
					'savedRows' => $saved,
					'errors'    => $errors,
					'skuError' => $skuError,
					'skuSuccess'=>$skuSuccuess
			);
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
		}
	}
	public function batchFinishAction()
	{
		$exeuctionTerminated = $this->getRequest()->getParam('exeuctionTerminated');
		if($exeuctionTerminated){
			Mage::getSingleton('adminhtml/session')->setTerminateExecution(true);
			Mage::helper('logicbroker')->finishProcess('bulk_assign');
		}else
		{
			Mage::helper('logicbroker')->finishProcess('bulk_assign');
		}
		$result = array();
		$data = $this->getRequest()->getPost();
		Mage::getResourceModel('logicbroker/vendorimportlog')->insertlog($data['lb_vendor_code'],'Bulk-product-setup',$data['sucees_sku'],$data['errorSkuCount']);
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
	}
	
	
	public function terminateAction(){
		
		Mage::helper('logicbroker')->finishProcess('manual_upload');
		$result = array();
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
	}
		
	/**
	 * Export vendor in csv format
	 */
	public function exportCsvAction()
	{
		$fileName   = 'inventory.csv';
		$content    = $this->getLayout()->createBlock('logicbroker/adminhtml_inventory_grid')->getCsvFile();
		$this->_prepareDownloadResponse($fileName, $content);
	}
	
	/**
	 * Export vendor in Excel format
	 */
	public function exportXmlAction()
	{
		$fileName   = 'inventory.xml';
		$content    = $this->getLayout()->createBlock('logicbroker/adminhtml_inventory_grid')->getExcelFile($fileName);
		$this->_prepareDownloadResponse($fileName, $content);
	}
	
	public function exportErrorCsvAction()
	{
		$params = $this->getRequest()->getParams();
		$filename = 'upload_error.csv';
        $content = Mage::helper('logicbroker')->generateErrorList($params);
 
        $this->_prepareDownloadResponse($filename, $content);
	}
	
	
}
