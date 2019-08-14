<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
$installer = $this;
$importLog = $installer->getTable('dropship360/vendor_import_log');
$ranking = $installer->getTable('dropship360/ranking');
$installer->startSetup();
$installer->getConnection()->addColumn($importLog, 'error_id', array(
    'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
    'identity'  => true,
	'nullable' => false,
     'unsigned'  => true,
	 'primary'   => true,
    'comment' => 'Add error id row'
)
);
$installer->getConnection()->addKey($importLog, 'error_id', 'error_id');
$installer->getConnection()->addColumn($ranking, 'linking_attribute', array(
		'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
		'length' =>255,
		'nullable' => true,
		'default' => null,
		'comment' => 'linking attribute'));
$installer->run(
  "DROP TABLE IF EXISTS {$installer->getTable('dropship360/csvtmpdata')};
  CREATE TABLE IF NOT EXISTS {$installer->getTable('dropship360/csvtmpdata')} (
  `row_id` INT(11) NOT NULL AUTO_INCREMENT ,
  `vendor_code` varchar(50) NOT NULL,
  `csv_vendor_sku` varchar(50) NOT NULL,
  `csv_stock` varchar(11) NOT NULL,
  `csv_price` varchar(11) NOT NULL,
 `is_processed` int(1) NOT NULL DEFAULT '0',
  KEY `row_id` (`row_id`),
  KEY `vendor_code` (`vendor_code`),
  KEY `csv_vendor_sku` (`csv_vendor_sku`),
  KEY `csv_stock` (`csv_stock`),
  KEY `csv_price` (`csv_price`),
  KEY `is_processed` (`is_processed`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;
CREATE TABLE IF NOT EXISTS {$installer->getTable('dropship360/vendor_import_log_desc')} (
`id` int(11) NOT NULL AUTO_INCREMENT,
`error_id` int(10) unsigned NOT NULL,
`description` varchar(255) CHARACTER SET utf8 NOT NULL,
PRIMARY KEY (`id`),
KEY `error_id` (`error_id`),
FOREIGN KEY (`error_id`) REFERENCES {$installer->getTable('dropship360/vendor_import_log')} (`error_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;");

//patch apply for MAGENTO-CE/EE 1.9.2.2
$admin_permission_block = Mage::getConfig()->getTablePrefix().'permission_block';
if ($installer->getConnection()->isTableExists($admin_permission_block)){
	$installer->getConnection()->insertMultiple(
			$installer->getTable('admin/permission_block'),
			array(
					array('block_name' => 'dropship360/adminhtml_reports_staticemailgrid', 'is_allowed' => 1)
			)
	);
}
$admin_permission_var = Mage::getConfig()->getTablePrefix().'permission_variable';
if ($installer->getConnection()->isTableExists($admin_permission_var)){
	$installer->getConnection()->insertMultiple(
			$installer->getTable('admin/permission_variable'),
			array(
					array('variable_name' => 'logicbroker_sourcing/cron_settings_upload/ftp_site', 'is_allowed' => 1),
					array('variable_name' => 'logicbroker_sourcing/cron_settings_upload/ftp_accnumber', 'is_allowed' => 1),
					array('variable_name' => 'logicbroker_sourcing/cron_settings_upload/ftp_username', 'is_allowed' => 1),
			)
	);
}
$installer->endSetup();
