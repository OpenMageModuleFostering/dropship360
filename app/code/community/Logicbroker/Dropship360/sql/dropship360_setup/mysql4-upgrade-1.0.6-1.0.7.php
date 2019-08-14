<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
$installer = $this;
//apply patch for insert on duplicate
$csvTempData = $installer->getTable('dropship360/csvtmpdata');
$installer->getConnection()->addKey($csvTempData,'csv_vendor_sku_unique','csv_vendor_sku','unique');
$installer->endSetup();
