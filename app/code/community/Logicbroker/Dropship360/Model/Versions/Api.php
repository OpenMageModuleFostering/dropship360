<?php

class Logicbroker_Dropship360_Model_Versions_Api extends Mage_Api_Model_Resource_Abstract
{
	public function getdropship360version() {
    
          $magento_edition = Mage::getEdition();                      
          $dropship_version = Mage::getStoreConfig('logicbroker_integration/integration/ds360_version',Mage::app()->getStore());
          
          $magento_version = Mage::getStoreConfig('logicbroker_integration/integration/magento_version',Mage::app()->getStore());
          
          $moduleName = 'Logicbroker_Dropship360'; 
          $moduleVersion = Mage::getConfig()->getNode('modules/' . $moduleName . '/version');
          
          $versions = array();
          $versions['magentoVer'] = $magento_edition ." ".$magento_version;
          $versions['dropship360Ver'] = $dropship_version;
          $versions['dropshipDbScriptVer'] = $moduleVersion;
          
          return $versions; 
  }

} 
