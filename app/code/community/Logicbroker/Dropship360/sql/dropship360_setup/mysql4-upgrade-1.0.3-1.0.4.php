<?php
 
$installer = $this;
 
$installer->startSetup();

$installer->run("

CREATE TABLE IF NOT EXISTS {$this->getTable('logicbroker_vendor_inventory_log')} (
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

CREATE TABLE IF NOT EXISTS {$this->getTable('logicbroker_vendor_product_import')} (
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

DROP TABLE IF EXISTS {$this->getTable('logicbroker_vendor_product_import_log')};
CREATE TABLE IF NOT EXISTS {$this->getTable('logicbroker_vendor_product_import_log')} (
  `lb_vendor_code` varchar(50) NOT NULL,
  `updated_by` text NOT NULL,
  `success` int(11) NOT NULL,
  `failure` int(11) NOT NULL,
  `ftp_error` text,
  `ftp_error_desc` text,  
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP  
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0 ;
");

$installer->run("ALTER TABLE  {$this->getTable('logicbroker_vendor_inventory_log')} CHANGE  `cost`  `cost` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
CHANGE  `stock`  `stock` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
CHANGE  `activity`  `activity` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL");
//$installer->run("ALTER TABLE {$this->getTable('logicbroker_vendor_product_import_log')} ADD `ftp_error` text");

//$installer->run("ALTER TABLE {$this->getTable('logicbroker_vendor_product_import_log')} ADD `ftp_error_desc` text");



$installer->endSetup();