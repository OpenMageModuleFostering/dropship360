<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Fulfillment
 */
class Logicbroker_Fulfillment_Adminhtml_RankingController extends Mage_Adminhtml_Controller_Action {
	protected function _initAction() {
		$this->loadLayout ()->_setActiveMenu ( 'logicbroker/vendor_ranking' )->_addBreadcrumb ( Mage::helper ( 'adminhtml' )->__ ( 'Vendor Ranking' ), Mage::helper ( 'adminhtml' )->__ ( 'Vendor Ranking' ) );
		
		return $this;
	}
	public function indexAction() {
		$layout = $this->_initAction ();
		$layout->getLayout()->getBlock('head')->setCanLoadExtJs(false);
		$layout->renderLayout ();
		
	}
	public function popupAction() {
		$this->loadLayout ();
		$this->renderLayout ();
	}
	
	/**
	 * Ranking grid
	 */
	public function gridAction() {
		$this->getResponse ()->setBody ( $this->getLayout ()->createBlock ( 'logicbroker/adminhtml_ranking_grid' )->toHtml () );
	}
	public function editAction() {
		
	}
	public function newAction() {
		
	}
	
	public function showhistoryAction() {
		$this->loadLayout ();
		$this->renderLayout ();
	}
	
	public function addNewVendorAction() {
		
		$isSuccess = false;
		$data = $this->getRequest ()->getPost ();
		$arrVendor = array();
		$vendorRankCollection =  Mage::getModel ( 'logicbroker/ranking' );
		$eavModel=Mage::getModel('eav/entity_setup','core_setup');
		$genrateVendorCode = $vendorRankCollection->getCollection()->addFieldToFilter('lb_vendor_code',array('like'=>'%MagVendID%'));
		
		foreach($genrateVendorCode as $vendorCode)
		{
			if(preg_match('!\d+!',  $vendorCode->getLbVendorCode(), $matches)){
				$arrVendor[] = (int) $matches[0];
			}
			
		}
		$suffix = ((int) max($arrVendor) + 1);
		$code = 'MagVendID'.$suffix;
		$vendorRankCollection->setLbVendorCode($code);
		$vendorRankCollection->setRanking($data['rank']);
		$vendorRankCollection->setLbVendorName($data['name']);
		$vendorRankCollection->setLbVendorType('user');
		$vendorRankCollection->setUpdatedAt(gmdate('Y-m-d H:i:s'));
		try{
		$vendorRankCollection->save();
		$option = array ('values' =>array ($suffix => $code));
		$option['attribute_id'] = $eavModel->getAttributeId(Mage::getModel ( 'eav/config' )->getEntityType ( 'catalog_product' )->getEntityTypeId (), 'lb_vendor_code_list');
		$eavModel->addAttributeOption($option);
		
		$isSuccess = true;
		}catch ( Exception $e ) {
				Mage::getSingleton ( 'adminhtml/session' )->addError ( $e->getMessage () );
				$isSuccess = false;
			}
		$result = array('success'=>$isSuccess,'message'=>$code);
		
		$result = Mage::helper('core')->jsonEncode($result);
		Mage::app()->getResponse()->setBody($result);
		
		return;
	}
	
	
	public function saverankingAction() {
		$updateVendorRank = array ();
		$vendorRanking = array ();
		$rankingArray = array ();
		$vendorName = array();
		
		$data = $this->getRequest ()->getPost ();
		
		$tableName = $data['partent_save_table_input'];
		$dropShip = json_decode((urldecode($data['dropship_data'])),true); 
		$nonDropShip = json_decode((urldecode($data['nondropship_data'])),true);
		$vendorName = json_decode((urldecode($data['vendorname_data'])),true);
		$modelRanking = Mage::getModel ( 'logicbroker/rankinglog' )->load($tableName,'label');
		/* echo '<pre>';
		print_r($dropShip);
		print_r($nonDropShip);
		print_r($vendorName);
		die */;
		
		if (!$tableName || $modelRanking->getId()) {
		Mage::getSingleton ( 'adminhtml/session' )->addError ( Mage::helper ( 'logicbroker' )->__ ( 'Ranking Table Name Is Empty Or Already Exists' ) );
		$this->_redirect ( '*/*/' );
		return;
			}
		
		$this->_saveVendorRanking($dropShip,true);
		$this->_saveVendorRanking($nonDropShip,false);
		$this->_updateVendorName($vendorName);
		$result = $this->_saveTableRanking(trim($tableName));
		
		Mage::getSingleton ( 'adminhtml/session' )->addSuccess ( Mage::helper ( 'logicbroker' )->__ ( 'Vendor ranking saved successfully' ) );
		$this->_redirect ( '*/*/' );
		return;
	}
	protected function _saveVendorRanking($collection,$rank = false) {
		if(!empty($collection))
		{
			try {
				foreach ( $collection as $key => $val ) {
					$model = Mage::getModel ( 'logicbroker/ranking' )->load ( $val['code'], 'lb_vendor_code' );
					$model->setUpdatedAt(gmdate('Y-m-d H:i:s'));
					
					if($rank)
					$model->setIsDropship ('yes');
					else
					$model->setIsDropship ('no');
					
					$model->setRanking ( ($rank) ? $key+1:0 );
					$model->save ();
				}
				
			} catch ( Exception $e ) {
				Mage::getSingleton ( 'adminhtml/session' )->addError ( $e->getMessage () );
			}
		}
		
	}
	
	protected function _updateVendorName($collection) {
		if(!empty($collection))
		{
			try {
				foreach ( $collection as $key => $val ) {
				$model = Mage::getModel ( 'logicbroker/ranking' )->load ( $val['code'], 'lb_vendor_code' );
				if($model->getLbVendorCode())
				$model->setLbVendorName ($val['name'])->save();
				
				$modelInventory = Mage::getModel ( 'logicbroker/inventory' )->load ( $val['code'], 'lb_vendor_code' );
				if($modelInventory->getLbVendorCode())
				$modelInventory->setLbVendorName ($val['name'])->save();
				
				}
				
			} catch ( Exception $e ) {
				Mage::getSingleton ( 'adminhtml/session' )->addError ( $e->getMessage () );
			}
		}
		
	}
	
	protected function _saveTableRanking($tableName) {
		
			$serializedArray = array ();
			$model = Mage::getModel ( 'logicbroker/ranking' );
			$modelRanking = Mage::getModel ( 'logicbroker/rankinglog' );
			$collection = $model->getCollection ();
			$collection->getSelect()->order('ranking asc');
			
			
			
			if($collection->count() > 0){
			foreach ( $collection as $value ) {
				$serializedArray [] = array (
						$value->getLbVendorName (),
						$value->getLbVendorCode (),
						$value->getRanking (),
						$value->getIsDropship()
												 
				);
			}
			$modelRanking->setRankingData ( serialize ( $serializedArray ) );
			$modelRanking->setLabel ( trim ( $tableName ) );
				
			try {
				$modelRanking->save ();
				
			} catch ( Exception $e ) {
				Mage::getSingleton ( 'adminhtml/session' )->addError ( $e->getMessage () );
			}
			}
			return;
	}
	
	public function deleteAction() {
		
	}
	
	/**
	 * Export vendor in csv format
	 */
	public function exportCsvAction() {
		
	}
	
	/**
	 * Export vendor in Excel format
	 */
	public function exportXmlAction() {
		
	}
}
