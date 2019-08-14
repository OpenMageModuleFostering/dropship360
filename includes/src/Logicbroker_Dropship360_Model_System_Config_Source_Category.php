<?php
/**
 * Logicbroker
 *

 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Model_System_Config_Source_Category
{
    public function toOptionArray($addEmpty = true)
    {
        $collection = Mage::getResourceModel('catalog/category_collection');

        $collection->addAttributeToSelect('name')->load();

        $options = array();

       
        foreach ($collection as $category) {
            $options[] = array(
               'label' => $category->getName(),
               'value' => $category->getId()
            );
        }
       
        	array_unshift($options,array('value' => '', 'label' => Mage::helper('dropship360')->__('--Please Select--'))
        	);
        
        return $options;
    }
}
