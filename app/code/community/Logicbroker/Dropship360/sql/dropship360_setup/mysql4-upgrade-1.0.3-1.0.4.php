<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */ 
$installer = $this;
 
$installer->startSetup();

$installer->run("

CREATE TABLE IF NOT EXISTS {$installer->getTable('dropship360/inventorylog')} (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lb_vendor_code` varchar(50) NOT NULL,
  `lb_vendor_name` varchar(50) NOT NULL,
  `product_sku` varchar(50) NOT NULL,
  `cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `stock` int(10) NOT NULL DEFAULT '0',
  `updated_by` varchar(50) NOT NULL,
  `activity` enum('update','add','delete') NOT NULL DEFAULT 'add',
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0 ;

CREATE TABLE IF NOT EXISTS {$installer->getTable('dropship360/uploadvendor')} (
  `file_id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(50) NOT NULL,
  `import_status` enum('pending','done','processing') NOT NULL DEFAULT 'pending',
  `updated_by` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lb_vendor_code` varchar(50) NOT NULL,
  PRIMARY KEY (`file_id`),
  KEY `import_status` (`import_status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0 ;

-- DROP TABLE IF EXISTS {$installer->getTable('dropship360/vendor_import_log')};
CREATE TABLE IF NOT EXISTS {$installer->getTable('dropship360/vendor_import_log')} (
  `lb_vendor_code` varchar(50) NOT NULL,
  `updated_by` text NOT NULL,
  `success` int(11) NOT NULL,
  `failure` int(11) NOT NULL,
  `ftp_error` text,
  `ftp_error_desc` text,  
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP  
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0 ;
");

$installer->getConnection()->modifyColumn($installer->getTable('dropship360/inventorylog'),'cost', 'VARCHAR( 50 ) DEFAULT NULL');
$installer->getConnection()->modifyColumn($installer->getTable('dropship360/inventorylog'),'stock', 'VARCHAR( 50 ) DEFAULT NULL');
$installer->getConnection()->modifyColumn($installer->getTable('dropship360/inventorylog'),'activity', 'VARCHAR( 255 ) DEFAULT NULL');
$installer->endSetup();