<?php
/**

 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Upload_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Add fieldset
     *
     * @return Mage_ImportExport_Block_Adminhtml_Import_Edit_Form
     */
    protected function _prepareForm()
    {
    	$isProductSetupMode = $this->getProductsetupmode();
        $form = new Varien_Data_Form(array(
            'id'      => 'edit_form',
            'action'  => ($isProductSetupMode) ? $this->getUrl('*/adminhtml_upload/uploadFile') : $this->getUrl('*/*/uploadFile'),
            'method'  => 'post',
            'enctype' => 'multipart/form-data'
        ));
        $legend = ($isProductSetupMode) ? $this->getLegendtext() : 'Import Settings';
        $fieldset = $form->addFieldset('base_fieldset', array('legend' => Mage::helper('importexport')->__($legend)));
        $isProcessContinue = Mage::helper('logicbroker')->isProcessRunning('bulk_assign');
        $fieldset->addField('productsetupmode', 'hidden', array(
        		'name'     => 'productsetupmode',
        		'value'   => $isProductSetupMode
        ));
        //var_dump($isProcessContinue);
        $fieldset->addField('vendor', 'select', array(
            'name'     => 'vendor',
            'title'    => Mage::helper('importexport')->__('Supplier'),
            'label'    => Mage::helper('importexport')->__('Supplier'),
            'required' => true,
            'values'   => Mage::getModel('logicbroker/system_config_source_vendorlist')->getAllVendor()
        ));
        if($isProductSetupMode){
        $fieldset->addType('custombutton', 'Logicbroker_Dropship360_Block_Adminhtml_Upload_Edit_Button');
        $fieldset->addField('vendor_product_setup', 'custombutton', array(
        		'name'     => 'vendor_product_setup',
        		'title'    => Mage::helper('importexport')->__('Setup Supplier on all Products'),
        		'required' => false,
        		'disabled' => ($isProcessContinue) ? true : false,
        		'value'   => 'Setup Supplier on all Products',
        		'onclick' => 'uploadVendorProductStep()'
        ));
        }
        $fieldset->addField(Mage_ImportExport_Model_Import::FIELD_NAME_SOURCE_FILE, 'file', array(
            'name'     => Mage_ImportExport_Model_Import::FIELD_NAME_SOURCE_FILE,
            'label'    => Mage::helper('importexport')->__('Select File to Import'),
            'title'    => Mage::helper('importexport')->__('Select File to Import'),
            'required' => true
        ));

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
