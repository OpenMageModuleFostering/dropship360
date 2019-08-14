<?php
/**


 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Model_System_Config_Backend_Uploadvendor_Cron extends Mage_Core_Model_Config_Data
{
    const CRON_STRING_PATH  = 'crontab/jobs/logicbroker_uploadvendor/schedule/cron_expr';
    const CRON_MODEL_PATH   = 'crontab/jobs/logicbroker_uploadvendor/run/model';

    /**
     * Cron settings after save
     *
     * @return Mage_Adminhtml_Model_System_Config_Backend_Log_Cron
     */
    protected function _afterSave()
    {
        $enabled    = $this->getData('groups/cron_settings_upload/fields/enabled/value');
        $time       = $this->getData('groups/cron_settings_upload/fields/time/value');
		$cronExprString = '';
         if ($enabled) {
			if($time[0] == -2){
				$cronExprArray = array(
                ($time[1] == -1) ? '*'  : '*/'.intval($time[1]),         
                '*',         
                '*',       # Day of the Month
				'*',       # Month of the Year
				'*',       # Day of the Week
				);
			}else{
				$cronExprArray = array(
				   ($time[1] == -1) ? '*'  : intval($time[1]),           # Minute
					($time[0] == -1) ? '*' : intval($time[0]),          # Hour
					'*',       # Day of the Month
					'*',       # Month of the Year
					'*',       # Day of the Week
				);
			}
            $cronExprString = join(' ', $cronExprArray);
        }
        try {
            Mage::getModel('core/config_data')
                ->load(self::CRON_STRING_PATH, 'path')
                ->setValue($cronExprString)
                ->setPath(self::CRON_STRING_PATH)
                ->save();

            Mage::getModel('core/config_data')
                ->load(self::CRON_MODEL_PATH, 'path')
                ->setValue((string) Mage::getConfig()->getNode(self::CRON_MODEL_PATH))
                ->setPath(self::CRON_MODEL_PATH)
                ->save();
        } catch (Exception $e) {
            Mage::throwException(Mage::helper('adminhtml')->__('Unable to save the cron expression.'));
        }
    }
}
