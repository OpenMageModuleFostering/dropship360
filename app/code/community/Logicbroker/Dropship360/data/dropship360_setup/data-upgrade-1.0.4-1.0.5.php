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
        'path'       => 'logicbroker_sourcing/cron_settings/backorder_time',
        'value'     => '-1,0',
        
    ),
	array(
        'scope'         => 'default',
        'scope_id'    => '0',
        'path'       => 'logicbroker_sourcing/cron_settings_upload/time',
        'value'     => '23,59',
        
    ),
	array(
        'scope'      => 'default',
        'scope_id'   => '0',
        'path'       => 'crontab/jobs/logicbroker_backorder/schedule/cron_expr',
		'value'     => '0 * * * *',  
    ),
	array(
        'scope'      => 'default',
        'scope_id'   => '0',
        'path'       => 'crontab/jobs/logicbroker_uploadvendor/schedule/cron_expr',
		'value'     => '59 23 * * *',  
    ),
	array(
        'scope'      => 'default',
        'scope_id'   => '0',
        'path'       => 'crontab/jobs/logicbroker_uploadvendor/run/model',
		'value'    => 'dropship360/observer::ftpParseCsv',  
    )

);

/**
 * Insert default blocks
 */
foreach ($coreConfigData as $data) {
	Mage::getModel('core/config_data')->setData($data)->save();
} 