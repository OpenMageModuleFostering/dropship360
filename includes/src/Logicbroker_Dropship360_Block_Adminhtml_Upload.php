<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Upload extends Mage_Adminhtml_Block_Widget_Form_Container
{
	protected $_headerText = 'Upload Supplier Product';
	protected $_isProductSetupMode = false;
    public function __construct()
    {
    	$this->addButton('download_sample_csv',array(
    			'label'     => 'Download Sample Csv',
    			'onclick'   => 'downloadSampleCsv()',
    			'class'     => 'save',
    	));
    	$this->addButton('history',array(
    			'label'     => 'History',
    			'onclick'   => 'setLocation(\'' . $this->getUrl('*/adminhtml_upload/vendorsuploadhistory') .'\')',
    			'class'     => 'save',
    	));
    	parent::__construct();
		
        $this->removeButton('back')
            ->removeButton('reset')
            ->_updateButton('save', 'label', $this->__('Upload'))
            ->_updateButton('save', 'id', 'upload_button');
        $this->_formScripts[] = "
            function uploadVendorProductStep(){
                var activeRankers = ".$this->getActiveRankers()."
        		var url;
        		var selectBox = $('vendor');
        		if(selectBox.selectedIndex <= 0 ){
        			alert('please select a Supplier');
        		return;
    				}
				var r = confirm('Are you sure you want to setup this supplier for all Magento Products?');
					if (r == true) {
    					if(activeRankers.indexOf(selectBox.value) == -1)
                		{
    							alert('supplier is Inactive - Cannot Setup Supplier on All Products');
                				return;
    					}
                		url = '". $this->getSetupUrl() ."lb_vendor_code/'+selectBox.value;
                				//alert(url);
                		window.open(url);
                		$('vendor_product_setup').addClassName('disabled');		
                		$('vendor_product_setup').disable();
					} else {
					   return;
					}
            }";
            //->_updateButton('save', 'onclick', 'editForm.postToFrame();');
    }

    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_blockGroup = 'logicbroker';
        $this->_controller = 'adminhtml_upload';
    }

    /**
     *	set header text
     *
     * @return string
     */
    public function setHeaderText($header)
    {
    	return $this->_headerText = $header ;
    }
    public function setProductSetupMode($bool = false)
    {
    	$this->_isProductSetupMode = $bool;
    }
    public function getProductSetupMode()
    {
    	return $this->_isProductSetupMode;
    }
	public function getFormHtml()
    {
        $this->getChild('form')->setData('action', $this->getSaveUrl());
        $this->getChild('form')->setData('productsetupmode', $this->_isProductSetupMode);
        $this->getChild('form')->setData('legendtext', $this->_headerText);
        return $this->getChildHtml('form');
    }
    protected function getActiveRankers()
    {
    	$rankersArray = array();
    	$collection = Mage::getModel('logicbroker/ranking')->getCollection()->addFieldToFilter('is_dropship','yes');
    	if($collection->count() > 0){
    	foreach($collection as $data){
    		$rankersArray[] = $data->getLbVendorCode();
    	}
    	}
    	return Mage::helper('core')->jsonEncode($rankersArray);
    }
    protected function getSetupUrl()
    {
    	return $this->getUrl('*/adminhtml_upload/run');
    }
}
