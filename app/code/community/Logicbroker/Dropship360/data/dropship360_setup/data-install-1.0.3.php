<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
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
        
    ));

/**
 * Insert default blocks
 */
foreach ($coreConfigData as $data) {
    
    Mage::getModel('core/config_data')->setData($data)->save();
}

/* Add attribute to catalog default set */

/*
$eavModel=Mage::getModel('eav/entity_setup','core_setup');
$attributeId=$eavModel->getAttribute('catalog_product','manufacturer');
$attributeSetId=$eavModel->getAttributeSetId('catalog_product','Default');
//Get attribute group info
$attributeGroupId=$eavModel->getAttributeGroup('catalog_product',$attributeSetId,'General');
//add attribute to a set
if(!empty($attributeId) && !empty($attributeId) && $attributeSetId)
$eavModel->addAttributeToSet('catalog_product',$attributeSetId,$attributeGroupId['attribute_group_id'],$attributeId['attribute_id']);
*/
Mage::getModel('dropship360/ranking')->addPreDefineVendorList();



