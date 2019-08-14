<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_System_Config_Ftpconnectionbutton
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
	protected $_buttonId;
    /*
     * Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('logicbroker/system/config/submitbutton.phtml');
    }

    /**
     * Remove scope label
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->_buttonId = $element->getHtmlId();
		return $this->_toHtml();
    }

    /**
     * Return ajax url for synchronize button
     *
     * @return string
     */
    public function getAjaxSyncUrl()
    {
        return $this->getUrl('logicbroker/adminhtml_upload/validateftpconnection');
    }

    
    /**
     * Generate synchronize button html
     *
     * @return string
     */
    public function getSubmitButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id'        => $this->_buttonId,//'ftp_submit_button',
                'label'     => $this->helper('adminhtml')->__('Test Connection'),
                'onclick'   => 'javascript:synchronize(this.id); return false;',
                'style' => 'float:right'
            ));

        return $button->toHtml();
    }

    /**
     * Generate Save button html
     *
     * @return string
     */
    public function getSaveButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id'        => 'save_button',
                'label'     => $this->helper('adminhtml')->__('Save for Later'),
                'onclick'   => 'javascript:synchronize(this.id); return false;',
                
            ));

        return $button->toHtml();
    }
    
    public function getTermsAndConditionsUrl()
    {
        return Mage::helper('logicbroker')->getConfigObject('apiconfig/termsandconditions/link');
    }

    public function getHelpUrl()
    {
        return Mage::helper('logicbroker')->getConfigObject('apiconfig/helpurl/link');
    }
}
