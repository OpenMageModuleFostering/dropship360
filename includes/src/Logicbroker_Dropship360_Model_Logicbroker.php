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
        $definedRole = Mage::helper('dropship360')->getConfigObject('apiconfig/soap_role');
        $ruleNodes = (!empty($definedRole)) ? explode(',',$definedRole) : array('all');

        if (!is_array($fieldsetData)) {
            return false;
        }
        $role->load($roleName, 'role_name');
        //$ruleNodes = array('all');
        //$ruleNodes = $definedRole;
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
	    		$version = Mage::helper('dropship360')->getConfigObject('default/logicbroker_integration/integration/ds360_version');
	    		$fieldsetData['subject'] = 'New DS360 Package Extension version '.$version.' was installed';
	    		$postObject = new Varien_Object();
	    		$postObject->setData($fieldsetData);
	    		$templateId = 'logicbroker_email_email_template';
	    		$email = Mage::helper('dropship360')->getConfigObject('apiconfig/email/toaddress');
	    		$isMailSent = Mage::helper('dropship360')->sendMail($postObject,$email,$templateId);
	    		if (!$isMailSent) {
	    			Mage::helper('dropship360')->genrateLog(0,'Installation notification started','Installation notification ended','Module installation notifiaction mail sending failed');
	    		}
	    		Mage::getSingleton('adminhtml/session')->unsNotification();
	    		return true;
    	} catch (Exception $e) {
    		return false;//$e->getMassage();
    	}
    }
    
    public function prepareNotification($object,$orderId){
    	if($object == null || $orderId == null)
    		return;
    	$collection = $object->getCollection();
    	if($collection->getSize() == 0 )
    		$this->saveNotificationValue($orderId,'logicbroker/setup_notification/order');
    	}
    public function saveNotificationValue($value = null,$path){
    	$data = array(
    			'scope'         => 'default',
    			'scope_id'    => '0',
    			'path'       => $path,
    			'value'     => $value,
    	);
    	try{
    		Mage::getModel('core/config_data')->load($data['path'],'path');
			Mage::getModel('core/config_data')->setData($data)->save();
    	}catch(Exception $e){
    		return false;
    	}
    }
    
    public function setupNotification()
    {
    	$order = Mage::getStoreConfig('logicbroker/setup_notification/order');
    	
    	if($order)
    	{
    		$inventory = Mage::getModel('dropship360/orderitems')->getCollection()->addFieldToFilter('item_order_id',$order)->addFieldToFilter('lb_item_status','Transmitting');
    		if($inventory->getSize() > 0){
    			
    			try {
    				$fieldsetData['order'] = Mage::getModel('sales/order')->load($order);
    				$fieldsetData['subject'] = 'DS360 Order has been Placed on Magento';
    				$postObject = new Varien_Object();
    				$postObject->setData($fieldsetData);
    				$templateId = 'logicbroker_order_notification';
    				$email = Mage::helper('dropship360')->getConfigObject('apiconfig/email/toaddress');
    				$isMailSent = Mage::helper('dropship360')->sendMail($postObject,$email,$templateId);
    				if (!$isMailSent) {
    					Mage::helper('dropship360')->genrateLog(0,'Order notification started','Order notification ended','First order goes to transmitting successfully email sending failed');
    				}
    				$this->saveNotificationValue(null,'logicbroker/setup_notification/order');
    				return true;
    			} catch (Exception $e) {
    				return false;//$e->getMassage();
    			}
    			
    		}
    		
    	}
    }
        
}
