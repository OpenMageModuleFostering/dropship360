<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2014 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml report filter form
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class Logicbroker_Dropship360_Block_Adminhtml_Reports_Activitymonitor_Form extends Mage_Adminhtml_Block_Widget_Form
{

    /**
     * Add fieldset with general report fields
     *
     * @return Mage_Adminhtml_Block_Report_Filter_Form
     */
    protected function _prepareForm()
    {
        $actionUrl = $this->getUrl('*/*/activitymonitor');
        $form = new Varien_Data_Form(
            array('id' => 'filter_form', 'action' => $actionUrl, 'method' => 'get')
        );
        $formData = Mage::getSingleton('adminhtml/session')->getFormData();
        
        $htmlIdPrefix = 'sales_report_';
        $form->setHtmlIdPrefix($htmlIdPrefix);
        $fieldset = $form->addFieldset('base_fieldset', array('legend'=>Mage::helper('reports')->__('Filter')));
        $this->_addElementTypes($fieldset);
		$fieldset->addField('store_ids', 'hidden', array(
            'name'  => 'store_ids'
        ));
        
       Mage::getSingleton('adminhtml/session')->getFormData();
        
       $fieldset->addField('notification_transmitting', 'text', array(
       		'name'     => 'notification_transmitting',
       		'label' => Mage::helper('logicbroker')->__('Percentage to Trigger Notification For Transmitting (%)'),
       		'required'  => true,
       		'title' => Mage::helper('logicbroker')->__('Percentage to Trigger Notification For Transmitting'),
       		'value'    => isset($formData['notification_transmitting']) ? $formData['notification_transmitting'] : '',
       		'class' => ' validate-digits validate-number-range number-range-1-99'
       
       ));
       
       $fieldset->addField('notification_backorder', 'text', array(
       		'name'     => 'notification_backorder',
       		'label' => Mage::helper('logicbroker')->__('Percentage to Trigger Notification For Backorder(%)'),
       		'required'  => true,
       		'title' => Mage::helper('logicbroker')->__('Percentage to Trigger Notification For Backorder'),
       		'value'    => isset($formData['notification_backorder']) ? $formData['notification_backorder'] : '',
       		'class' => ' validate-digits validate-number-range number-range-1-99'
       
       ));
        $fieldset->addField('notification_sent_to_supplier', 'text', array(
        		'name'     => 'notification_sent_to_supplier',
        		'label' => Mage::helper('logicbroker')->__('Percentage to Trigger Notification For Sent To Supplier (%)'),
				'required'  => true,
        		'title' => Mage::helper('logicbroker')->__('Percentage to Trigger Notification For Sent To Supplier'),
        		'value'    => isset($formData['notification_sent_to_supplier']) ? $formData['notification_sent_to_supplier'] : '',
        		'class' => ' validate-digits validate-number-range number-range-1-99'
        
        ));
		
        $fieldset->addField('curr', 'hidden', array(
        		'name'     => 'current_value',
        		'value'    => isset($formData['email']) ? $formData['email'] : '',
        ));
        
        $fieldset->addField('refresh', 'hidden', array(
        		'name'     => 'refresh',
        		'value'    => 'no',
        ));
        $fieldset->addField('email_adress', 'text', array(
        		'name'     => 'email',
        		'label' => Mage::helper('logicbroker')->__('Email Address'),
        		'title' => Mage::helper('logicbroker')->__('Email Address'),
        		'required'=> true,
        		'value'    => isset($formData['email']) ? $formData['email'] : '',
        		'class' => 'email-adress'
        		
        ));
        
        $fieldset->addField('choose-email', 'getemailaddress', array(
        		'name'     => 'get_email',
        		'title'    => Mage::helper('importexport')->__('Get Email Address'),
        		'value'   => 'Get Email Address',
        		'class' => 'rule-chooser-trigger'
        ));
        
        $fieldset->addField('dropshipstatus', 'select', array(
        		'name'     => 'dropshipstatus',
        		'options' => $this->prepareOptionValue(),
        		'label' => Mage::helper('logicbroker')->__('Dropship Status'),
        		'title' => Mage::helper('logicbroker')->__('Dropship Status'),
        		'value'    => isset($formData['dropshipstatus']) ? $formData['dropshipstatus'] : '',
        		'required'=> true
        
        ));
        
        $fieldset->addField('select_monitor_order', 'timestatus', array(
        		'name'     => 'select_monitor_order',
        		'options' => array(
                'day'   => Mage::helper('logicbroker')->__('Days'),
                'hour' => Mage::helper('logicbroker')->__('Hours')
                
            	),
        		'label' => Mage::helper('logicbroker')->__('Period to Monitor All Orders'),
        		'title' => Mage::helper('logicbroker')->__('Period to Monitor All Orders'),
        		'inputname' => 'input_monitor_order',
        		'selectvalue' => isset($formData['select_monitor_order']) ? $formData['select_monitor_order'] : '',
        		'inputvalue' => isset($formData['input_monitor_order']) ? $formData['input_monitor_order'] : '',
        
        ));
        
        $fieldset->addField('select_open_monitor', 'timestatus', array(
        		'name'     => 'select_open_monitor',
        		'options' => array(
        				'day'   => Mage::helper('logicbroker')->__('Days'),
        				'hour' => Mage::helper('logicbroker')->__('Hours')
        
        		),
        		'label' => Mage::helper('logicbroker')->__('Period Open to Monitor'),
        		'title' => Mage::helper('logicbroker')->__('Period Open to Monitor'),
        		'inputname' => 'input_open_monitor',
        		'selectvalue' => isset($formData['select_open_monitor']) ? $formData['select_open_monitor'] : '' ,
        		'inputvalue' => isset($formData['input_open_monitor']) ? $formData['input_open_monitor'] : '',
        
        ));
        
       
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
    
    protected function prepareOptionValue(){
    	 
    	$itemStatus = Mage::helper('logicbroker')->getItemStatuses();
    	$options = array();
    	$options[' '] = Mage::helper('logicbroker')->__('--Please Select--');
    	foreach($itemStatus as $status )
    	{
    		if(in_array($status,array('Transmitting','Backorder','Sent to Supplier')))
    		$options[$status] = Mage::helper('logicbroker')->__($status);
    	}
    	return $options;
    }
   
    
    protected function _getAdditionalElementTypes()
    {
    	return array(
    			'timestatus' => Mage::getConfig()->getBlockClassName('logicbroker/adminhtml_reports_form_timestatus'),
    			'getemailaddress' => Mage::getConfig()->getBlockClassName('logicbroker/adminhtml_upload_edit_button')
    	);
    }
    
}
