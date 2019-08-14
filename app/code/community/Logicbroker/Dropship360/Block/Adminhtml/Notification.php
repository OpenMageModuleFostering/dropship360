<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Notification extends Mage_Adminhtml_Block_Notification_Window
{
	protected function _construct(){
        parent::_construct();
        $this->setHeaderText($this->escapeHtml($this->__('Logicbroker Message')));
        $this->setCloseText($this->escapeHtml($this->__('close')));
        $this->setReadDetailsText($this->escapeHtml($this->__('Read details')));
        $this->setNoticeText($this->escapeHtml($this->__('NOTICE')));
        $this->setMinorText($this->escapeHtml($this->__('MINOR')));
        $this->setMajorText($this->escapeHtml($this->__('MAJOR')));
        $this->setCriticalText($this->escapeHtml($this->__('CRITICAL')));

	    }
	
	public function getNoticeMessageText(){
		
		$result = array();
		//soap Details
		$result['username'] = 'logicbroker';
		$apiResult = Mage::getModel('logicbroker/logicbroker')->createApiRoleAndUser(array('api_user_name'=>'logicbroker','email'=>'noreply@logicbroker.com'));
		$result['api_password'] = $apiResult['password'];
		$result['user_id'] = $apiResult['user_id'];
		
		//rest deatils
		$arrRest = Mage::getModel('logicbroker/api2_createroleandrule')->initiliazeRest();
		$result['consumer_key'] = $arrRest['consumer_key'];
		$result['consumer_secret'] = $arrRest['consumer_secret'];
		$result['token'] = $arrRest['token'];
		$result['secret'] =$arrRest['secret'];
		$coreConfigData = array(
					
				array(
						'scope'         => 'default',
						'scope_id'    => '0',
						'path'       => 'logicbroker_integration/integration/soapuser',
						'value'     => $result['username'],
							
				),
				
				array(
					'scope'         => 'default',
					'scope_id'    => '0',
					'path'       => 'logicbroker_integration/integration/cunsumer_key',
					'value'     => $result['consumer_key'],
			
						),
					array(
							'scope'         => 'default',
							'scope_id'    => '0',
							'path'       => 'logicbroker_integration/integration/consumer_secret',
							'value'     => $result['consumer_secret'],
					
					), array(
			        'scope'         => 'default',
			        'scope_id'    => '0',
			        'path'       => 'logicbroker_integration/integration/access_token',
			        'value'     => $result['token'],
			        
			    ),
			    array(
			        'scope'         => 'default',
			        'scope_id'    => '0',
			        'path'       => 'logicbroker_integration/integration/access_secret',
			        'value'     => $result['secret'],
			        
			    ));
		
		foreach ($coreConfigData as $data) {
			$this->setConfigValue($data);	
		}
		return $result;	
				
	}
	
	protected function setConfigValue($data)
	{	
		Mage::getModel('core/config_data')->load($data['path'],'path')->setData($data)->save();
	}
	
	public function canShow()
	{
		//$this->getRequest()->getModuleName() == 'logicbroker' &&
	  if (Mage::getStoreConfig('logicbroker_integration/integration/notificationstatus') == 0) {
		$this->setConfigValue(array(
						'scope'         => 'default',
						'scope_id'    => '0',
						'path'       => 'logicbroker_integration/integration/notificationstatus',
						'value'     => '1',
		
				));
	  	return true;
        }else
        {
        	return false;
        }
	}
}
