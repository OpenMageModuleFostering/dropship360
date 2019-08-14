<?php


/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Model_System_Config_Source_Optionvalues {
    
     
    public function toOptionArray()
    {
        
        $optionsArray = $this->getOptionValue();
        if(is_array($optionsArray)){
        array_unshift($optionsArray,array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--')));
        array_push($optionsArray,array('value' => 'addnew', 'label' => Mage::helper('logicbroker')->__('Add new code')));
        }
        else
        {
           $optionsArray = array(array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--')),
               array('value' => 'addnew', 'label' => Mage::helper('logicbroker')->__('Add new code'))); 
        }
//        echo '<pre>';
//        print_r($optionsArray);
//        die('save ememm');
        return  $optionsArray;
        
    }
    
    public function getOptionValue()
    {
        $integration = Mage::getStoreConfig('logicbroker_integration/integration/supplier_attribute');
        $logicbrokerCollection = Mage::getModel('logicbroker/supplier')->getCollection();
        $attributeArray = array();
        if($integration != null && $integration)
        {
        $attributeDetails = Mage::getSingleton("eav/config")->getAttribute("catalog_product", $integration);
        $options = $attributeDetails->getSource()->getAllOptions(false);
        foreach ($options as $option) {
            
            $attributeArray[] = array('value'=>$option["label"],'label' => Mage::helper('logicbroker')->__(strtolower($option["label"])));
        }
        }else
        {
          $logicbrokerCollection ->addFieldToFilter(array('is_update', 'verified'), array('1', '0'))->addFieldToFilter('status','')
          ->addFieldToSelect(array('company_id', 'magento_vendor_code'));
          $comapnyIds = $logicbrokerCollection->getData();
        if (count($comapnyIds) > 0) {
            foreach ($comapnyIds as $key=>$value) {
                $attributeArray[] = array('value'=>$value['magento_vendor_code'],'label' => Mage::helper('logicbroker')->__(strtolower($value['magento_vendor_code'])));
            }
        }
        }
        
        return $attributeArray;
        
    }
}

