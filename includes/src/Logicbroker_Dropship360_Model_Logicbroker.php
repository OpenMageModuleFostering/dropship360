<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Logicbroker {

	/* Creae API role */

    public function createApiRoleAndUser($fieldsetData) {

        $role = Mage::getModel('api/roles');
        $roleName = 'logicbroker';
        $parentId = '';
        $roleType = 'G';
        $ruleNodes = array();

        if (!is_array($fieldsetData)) {
            return false;
        }
        $role->load($roleName, 'role_name');
        $ruleNodes = array('all');
        try {
            $role = $role->setName($roleName)
                    ->setPid($parentId)
                    ->setRoleType($roleType)
                    ->save();

            Mage::getModel("api/rules")->setRoleId($role->getId())
                    ->setResources($ruleNodes)
                    ->saveRel();
        } catch (Exception $e) {
            return false; 
        }
        $password = '';
        $userExist = $this->_userExists($fieldsetData['api_user_name'],$fieldsetData['email']);
        $userId = '';
        if (is_array($userExist)) {
          $modelData =  Mage::getModel('api/user')->load($userExist[1]);
          $userId = $modelData->getUserId();
        }else
        {
          $modelData =  Mage::getModel('api/user');  
        }       
        $modelData->setData(array(
            'user_id'=> $userId,
            'username' => $fieldsetData['api_user_name'],
            'firstname' => 'logicbroker',
            'lastname' => '',
            'email' => $fieldsetData['email'],
            'api_key' => $password,
            'api_key_confirmation' => $password,
            'is_active' => 1,
            'user_roles' => '',
            'assigned_user_role' => '',
            'role_name' => '',
            'roles' => array($role->getId()) // your created custom role
                ));

        try {
            
                Mage::register('api_password', $modelData->getApiKey());
                $modelData->save();
                $modelData->setRoleIds(array($role->getId()))  // your created custom role
                    ->setRoleUserId($modelData->getUserId())
                    ->saveRelations();
            }
            
         catch (Exception $e) {
            return false;
        }
        return array('password'=>$modelData->getApiKey(),'user_id'=>$modelData->getUserId());
    }
    
    protected function _userExists($username,$email)
    {
        $resource = Mage::getSingleton('core/resource');
        $usersTable = $resource->getTableName('api/user');
        $adapter    = $resource->getConnection('core_read');
        $condition  = array(
            $adapter->quoteInto("{$usersTable}.username = ?", $username),
            $adapter->quoteInto("{$usersTable}.email = ?", $email),
        );
        $select = $adapter->select()
            ->from($usersTable)
            ->where(implode(' OR ', $condition))
            ->where($usersTable.'.user_id != ?', '');
        $result =  $adapter->fetchRow($select);
        if(is_array($result) && count($result) > 0 )
        {
            return array(true,(int)$result['user_id']); 
        }else
        {
            return false;
        }
        
    }

    protected function _generatePassword($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $count = mb_strlen($chars);

        for ($i = 0, $result = ''; $i < $length; $i++) {
            $index = rand(0, $count - 1);
            $result .= mb_substr($chars, $index, 1);
        }

        return $result;
    }



    public function checkAndCreateOrderStatus($fieldsetData) {
        if (!is_array($fieldsetData) && empty($fieldsetData)) {
            return false;
        }
        $statuses = $fieldsetData;
        $state = 'processing';
        $isDefault = '0';
        foreach ($statuses as $status => $label) {
            $this->assignStatus($status, $label, $state, $isDefault);
        }
        return true;
    }

	protected function assignStatus($status, $label, $state, $isDefault){
		$statusModel = Mage::getModel('sales/order_status')->load($status);
		if (!$statusModel->getStatus()) {
			$statusModel->setData(array('status' => $status, 'label' => $label))->setStatus($status);
			try {
				$statusModel->save();
				$statusModel->assignState($state, $isDefault);
				
			}catch (Mage_Core_Exception $e) {
				return false; 
			}
		}
	}	

    
    public function updateOrderState($fieldsetData) {
    	$state = 'processing';
    	$isDefault = '0';
    	$statusModel = Mage::getModel('sales/order_status')->load('lb_ready_to_source');
    		try {
    				$statusModel->unassignState('new');
    				$statusModel->assignState($state, $isDefault);
    
    			}catch (Mage_Core_Exception $e) {
    				return false;
    			}	
    	return true;
    }
    
    public function send($attachment = null,$fieldsetData) {
    	try {
    
    		$fieldsetData['isnewreg'] = true;
    		$postObject = new Varien_Object();
    		$postObject->setData($fieldsetData);
    		$mailTemplate = Mage::getModel('core/email_template');
    		/* @var $mailTemplate Mage_Core_Model_Email_Template */
    		$mailTemplate->setDesignConfig(array('area' => 'frontend'))
    		->sendTransactional(
    				'logicbroker_email_email_template',
    				'general',
    				Mage::helper('logicbroker')->getConfigObject('apiconfig/email/toaddress'),
    				Mage::helper('logicbroker')->getConfigObject('apiconfig/email/toname'),
    				array('templatevar' => $postObject)
    		);
    		if (!$mailTemplate->getSentSuccess()) {
    			Mage::helper('logicbroker')->genrateLog(0,'Installation notification started','Installation notification ended','Module installation notifiaction mail sending failed');
    			return false;
    		}
    		return true;
    	} catch (Exception $e) {
    		return false;//$e->getMassage();
    	}
    }
        
}
