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
 * @package     Mage_Cms
 * @copyright   Copyright (c) 2013 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

$coreConfigData = array(
    array(
        'scope'         => 'default',
        'scope_id'    => '0',
        'path'       => 'logicbroker_integration/integration/notificationstatus',
        'value'     => '0',
        
    ),
    array(
        'scope'         => 'default',
        'scope_id'    => '0',
        'path'       => 'logicbroker_integration/integration/dateofinstalltion',
        'value'     => date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time())),
        
    ),
    array(
        'scope'         => 'default',
        'scope_id'    => '0',
        'path'       => 'logicbroker_integration/integration/magento_version',
        'value'     => Mage::getVersion()
        
    ),
    array(
        'scope'         => 'default',
        'scope_id'    => '0',
        'path'       => 'logicbroker_integration/integration/soap_webservice_url',
        'value'     => Mage::getBaseUrl().'api/v2_soap/?wsdl=1'
        
    ),
    array(
        'scope'         => 'default',
        'scope_id'    => '0',
        'path'       => 'logicbroker_integration/integration/rest_webservice_url',
        'value'     => Mage::getBaseUrl().'api/rest/productsimport'
        
    )
    );

/**
 * Insert default blocks
 */
foreach ($coreConfigData as $data) {
    
    Mage::getModel('core/config_data')->setData($data)->save();
}

/* Add attribute to catalog default set */

$eavModel=Mage::getModel('eav/entity_setup','core_setup');
$attributeId=$eavModel->getAttribute('catalog_product','manufacturer');
$attributeSetId=$eavModel->getAttributeSetId('catalog_product','Default');
//Get attribute group info
$attributeGroupId=$eavModel->getAttributeGroup('catalog_product',$attributeSetId,'General');
//add attribute to a set
if(!empty($attributeId) && !empty($attributeId) && $attributeSetId)
$eavModel->addAttributeToSet('catalog_product',$attributeSetId,$attributeGroupId['attribute_group_id'],$attributeId['attribute_id']);


/* Add order statuses */

$orderStatuses = array('lb_backorder'=>'Backorder','lb_transmitting'=>'Transmitting','lb_order_sourced'=>'Order Sourced','lb_ready_to_source'=>'Ready to Source');
Mage::getModel('logicbroker/logicbroker')->checkAndCreateOrderStatus($orderStatuses);

/* Add attribute options to vendor list */

//Mage::getModel('logicbroker/ranking')->addAttributeOptions();

/* Add default vendor list  */

Mage::getModel('logicbroker/ranking')->addPreDefineVendorList();

/* If compiler is enabled than we need to run the compilation  */

/* if (defined('COMPILER_INCLUDE_PATH')) {
	try {
		Mage::getModel('compiler/process')->run();
		Mage::getSingleton('adminhtml/session')->addSuccess(
		Mage::helper('compiler')->__('The compilation has completed.')
		);
	} catch (Mage_Core_Exception $e) {
		Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
	} catch (Exception $e) {
		Mage::getSingleton('adminhtml/session')->addError(
		Mage::helper('compiler')->__('Compilation error')
		);
	}
} */ 

