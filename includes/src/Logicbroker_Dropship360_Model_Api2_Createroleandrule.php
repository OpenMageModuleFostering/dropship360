<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 * No use of this class currently as Rest may be a part of future release refer lbn-1351
 */
class Logicbroker_Dropship360_Model_Api2_Createroleandrule 
{
	
	public function initiliazeRest(){
		
	 $restRoleId = $this->createRoleRest();
	 $this->createAttributeAttributeRest();
	 $arrConsumer = $this->createOauthuser();
	 $adminUserId = $this->createAdminUserAndRole();
	  	$resourceModel = Mage::getResourceModel('api2/acl_global_role');
		try{
		$resourceModel->saveAdminToRoleRelation($adminUserId, $restRoleId);
		}catch(Exception $e)
		{
			echo $e->getMessage();
		}
		
		$restOauthAuthorization = $this->restOauthAuthorization(array('consumer_id'=>$arrConsumer['consumer_id'],'admin_id'=>$adminUserId));
		$result = array_merge($arrConsumer,$restOauthAuthorization); 
		return $result;
		
	}
	
	protected function restOauthAuthorization($oauthRequest)
	{
		$helper = Mage::helper('oauth');
		
		$oauthRequest['type']= 'access';
		$oauthRequest['token']= $helper->generateToken();
		$oauthRequest['secret']= $helper->generateTokenSecret();
		$oauthRequest['verifier']= $helper->generateVerifier();
		$oauthRequest['revoked']= 0;
		$oauthRequest['authorized']= 1;
		$oauthRequest['callback_url']= Mage::getBaseUrl();
		
		$token = Mage::getModel('oauth/token')->load($oauthRequest['consumer_id'],'consumer_id');
		
		if(!$token->getEntityId())
		$token->addData($oauthRequest);
		
		try {
			
			$token->save();
			
		} catch (Exception $e) {
			echo $e->getMessage();
		}
		return array('token'=>$token->getToken(),'secret'=>$token->getSecret());
	}
	
	protected function createRoleRest(){
		
		$role = Mage::getModel ( 'api2/acl_global_role' );
		$roleName = 'logicbroker';
		$ruleAsString = '__root__,group-logicbroker,group-product_import,resource-import,privilege-import-create,group-inventory_import,resource-inventory_import,privilege-inventory_import-create';
		Mage::app ()->getRequest ()->setParam ( 'resource', $ruleAsString );
		
		$role->load ( $roleName, 'role_name' );
		
		try {
			$role->setRoleName ( $roleName )->save ();
			$rule = Mage::getModel ( 'api2/acl_global_rule' );
			if ($role->getId ()) {
				$collection = $rule->getCollection ();
				$collection->addFilterByRoleId ( $role->getId () );
				foreach ( $collection as $model ) {
					$model->delete ();
				}
			}
			$ruleTree = Mage::getModel ( 'api2/acl_global_rule_tree', array (
					'type' => Mage_Api2_Model_Acl_Global_Rule_Tree::TYPE_PRIVILEGE 
			) );
			$resources = $ruleTree->getPostResources ();
			$id = $role->getId ();
			foreach ( $resources as $resourceId => $privileges ) {
				foreach ( $privileges as $privilege => $allow ) {
					if (! $allow) {
						continue;
					}
					
					$rule->setId ( null )->isObjectNew ( true );
					
					$rule->setRoleId ( $id )->setResourceId ( $resourceId )->setPrivilege ( $privilege )->save ();
				}
			}
		} catch ( Exception $e ) {
			echo $e->getMessage ();
		}
		
		return $id;
	}

	
	protected function createAttributeAttributeRest(){
		
		$attributeRule = '__root__,group-logicbroker,group-product_import,resource-import,operation-import-write,attribute-import-write-productdata,group-inventory_import,resource-inventory_import,operation-inventory_import-write,attribute-inventory_import-write-vendordata';
		$type = 'admin';
		Mage::app()->getRequest()->setParam('resource',$attributeRule);
		try {
		
			$ruleTree = Mage::getModel(
					'api2/acl_global_rule_tree',
					array('type' => Mage_Api2_Model_Acl_Global_Rule_Tree::TYPE_ATTRIBUTE)
			);
		
		
			$attribute = Mage::getModel('api2/acl_filter_attribute');
		
		
			$collection = $attribute->getCollection();
			$collection->addFilterByUserType($type);
		
			/** @var $model Mage_Api2_Model_Acl_Filter_Attribute */
			foreach ($collection as $model) {
				$model->delete();
			}
		
			foreach ($ruleTree->getPostResources() as $resourceId => $operations) {
				if (Mage_Api2_Model_Acl_Global_Rule::RESOURCE_ALL === $resourceId) {
					$attribute->setUserType($type)
					->setResourceId($resourceId)
					->save();
				} else {
					foreach ($operations as $operation => $attributes) {
						$attribute->setId(null)
						->isObjectNew(true);
		
						$attribute->setUserType($type)
						->setResourceId($resourceId)
						->setOperation($operation)
						->setAllowedAttributes(implode(',', array_keys($attributes)))
						->save();
					}
				}
			}
		} catch (Exception $e) {
			$e->getMessage();
		}
		
	}
	
	protected function createOauthuser(){
		
		$model = Mage::getModel('oauth/consumer');
		$helper = Mage::helper('oauth');
		$data = array(
		
				'name'=>'logicbroker',
				'key'=> $helper->generateConsumerKey(),
				'secret'=>  $helper->generateConsumerSecret(),
		) ;
		$model->load($data['name'],'name');
		try {
			if(!$model->getId()){
			$model->addData($data);
			$model->save();
			}
		}catch (Exception $e) {
			echo $e->getMessage();
			exit;
		}
		
		return array('consumer_id' => $model->getId(),'consumer_key'=>$model->getKey(),'consumer_secret'=>$model->getSecret());
	}
	
	protected function createAdminUserAndRole(){
		
		$resource = '__root__,admin/logicbroker,admin/dropship360/integration,admin/dropship360/order_sourcing,admin/dropship360/vendor_ranking,admin/dropship360/inventory,admin/dropship360/suppliers';
		$roleName = 'logicbrokerds360';

		$role =  Mage::getModel('admin/roles');
		$username = 'logicbrokerds360';
		$email = Mage::helper('dropship360')->getConfigObject('apiconfig/email/toaddress');
		$user = Mage::getModel('admin/user');
		$user->loadByUsername($username);
		if(!$user->getId()){
			$user->setData(array(
					'username'  => $username,
					'firstname' => 'logicbroker',
					'lastname'    => 'logicbroker',
					'email'     => $email.'.'.rand(1,99),
					'password'  =>'logicbroker@123',
					'is_active' => 1
			));
			
			try {
			
			$user->save();
			
		} catch (Exception $e) {
		echo $e->getMessage();
			exit;
		}
		}
		
		 $role->load($roleName,'role_name');
			//Assign Role Id
	try {
            
			$role->setName($roleName)
                 ->setPid(0)
                 ->setRoleType('G')
                 ->setGwsIsAll(1)
                 ->setGwsWebsites(NULL)
                 ->setGwsStoreGroups(NULL);
            
			$role->save();

            Mage::getModel("admin/rules")
                ->setRoleId($role->getId())
                ->setResources($resource)
                ->saveRel();

			$this->_addUserToRole($user->getId(), $role->getId());
           

		} catch (Exception $e) {
           echo $e->getMessage();
			exit;
        }
		return $user->getId();
	}
	
	protected function userExists($user)
    {
        $result = Mage::getResourceModel('admin/user')->userExists($user);
        return (is_array($result) && count($result) > 0) ? true : false;
    }
	protected function _addUserToRole($userId, $roleId)
	{
		$user = Mage::getModel("admin/user")->load($userId);
		$user->setRoleId($roleId)->setUserId($userId);
	
		if( $user->roleUserExists() === true ) {
			return false;
		} else {
			$user->add();
			return true;
		}
	}
}
