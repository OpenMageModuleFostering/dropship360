<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
$installer = $this;
$installer->startSetup();
$installer->getConnection()->modifyColumn($installer->getTable('logicbroker/inventory'),'stock', 'VARCHAR(11) NOT NULL');
$installer->getConnection()->modifyColumn($installer->getTable('logicbroker/inventory'),'created_at', 'TIMESTAMP NULL');
$installer->getConnection()->modifyColumn($installer->getTable('logicbroker/inventorylog'),'created_at', 'TIMESTAMP NULL');
$installer->getConnection()->modifyColumn($installer->getTable('logicbroker/uploadvendor'),'created_at', 'TIMESTAMP NULL');
$installer->getConnection()->modifyColumn($installer->getTable('logicbroker/vendor_import_log'),'created_at', 'TIMESTAMP NULL');
$installer->getConnection()->modifyColumn($installer->getTable('logicbroker/ranking'),'created_at', 'TIMESTAMP NULL');
$installer->getConnection()->modifyColumn($installer->getTable('logicbroker/rankinglog'),'created_at', 'TIMESTAMP NULL');
//$installer->run("ALTER TABLE {$this->getTable('logicbroker_sales_orders_items')} ADD COLUMN `item_status_history` TEXT NULL");
$installer->getConnection()->addColumn($installer->getTable('logicbroker/orderitems'), 'item_status_history', 'TEXT NULL');
$installer->run("CREATE TABLE IF NOT EXISTS {$installer->getTable('logicbroker/tmpdata')} (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `tmpdata` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;");
$installer->getConnection()->modifyColumn($installer->getTable('logicbroker/orderitems'),'lb_item_status', ' ENUM(  "Sourcing",  "Reprocess",  "Backorder",  "Transmitting",  "Sent to Supplier", "Sent to Vendor",  "Cancelled",  "No Dropship",  "Completed" ) NOT NULL DEFAULT  "Sourcing" ');

$installer->endSetup();
