<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
$collectionSize = (Mage::getModel('dropship360/inventory')->getCollection()->getSize() >= 1) ? 1 : '';
 $coreConfigData = array(
  array(
        'scope'         => 'default',
        'scope_id'    => '0',
        'path'       => 'logicbroker/notification/vendor_setup',
        'value'     => $collectionSize,
        
    )

);

/**
 * Insert default blocks
 */
foreach ($coreConfigData as $data) {
	Mage::getModel('core/config_data')->setData($data)->save();
} 