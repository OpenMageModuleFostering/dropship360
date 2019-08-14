<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Upload extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
    	$this->addButton('download_sample_csv',array(
    			'label'     => 'Download Sample Csv',
    			'onclick'   => 'setLocation(\'' . $this->getUrl('*/*/download') .'\')',
    			'class'     => 'save',
    	));
    	$this->addButton('history',array(
    			'label'     => 'History',
    			'onclick'   => 'setLocation(\'' . $this->getUrl('*/*/vendorsuploadhistory') .'\')',
    			'class'     => 'save',
    	));
    	parent::__construct();
		
        $this->removeButton('back')
            ->removeButton('reset')
            ->_updateButton('save', 'label', $this->__('Upload'))
            ->_updateButton('save', 'id', 'upload_button');
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

        //$this->_objectId   = '';
        $this->_blockGroup = 'logicbroker';
        $this->_controller = 'adminhtml_upload';
    }

    /**
     * Get header text
     *
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('importexport')->__('Upload Vendor Product');
    }
}
