<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
$installer = $this;
//adding new dropship360 item status refer LBN-1444  
$installer->getConnection()->modifyColumn($installer->getTable('dropship360/orderitems'),'lb_item_status', ' ENUM(  "Sourcing",  "Reprocess",  "Backorder",  "Transmitting",  "Sent to Supplier", "Sent to Vendor",  "Cancelled",  "No Dropship",  "Completed" ,"Error") NOT NULL DEFAULT  "Sourcing" ');
$installer->endSetup();
