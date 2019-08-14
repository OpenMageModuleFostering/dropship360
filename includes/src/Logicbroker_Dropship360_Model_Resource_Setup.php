<?php
/**
 * Catalog entity setup
 *
 * @category    Logicbroker
 * @package     Logicbroker_Dropship360
 * @author      Cybage Magento Core Team
 */
class Logicbroker_Dropship360_Model_Resource_Setup extends Mage_Eav_Model_Entity_Setup
{
    /**
     * Prepare catalog attribute values to save
     *
     * @param array $attr
     * @return array
     */
    
    /**
     * Default entites and attributes
     *
     * @return array
     */
     public function getDefaultEntities()
    {
        return array(
            'catalog_product'                => array(
                'entity_model'                   => 'catalog/product',
                'attribute_model'                => 'catalog/resource_eav_attribute',
                'table'                          => 'catalog/product',
                'additional_attribute_table'     => 'catalog/eav_attribute',
                'entity_attribute_collection'    => 'catalog/product_attribute_collection',
                'attributes'                     => array(
                    
                    'lb_manufacturer_product_number'             => array(
                    	'attribute_set' 			 =>  'Default',
                    	'group'                      => 'Logicbroker',
                        'type'                       => 'varchar',
                        'label'                      => 'LBManufacturerProductNumber',
                        'input'                      => 'text',
                    	'required' 				 	 => false,
                    	'user_defined'           	 => true,
                        'source'                     => '',
                        'sort_order'                 => 9,
                        'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
                        'searchable'                 => false,
                        'used_in_product_listing'    => false,
                    	'visible'           	 	 => true,
                    	
                    ),
                				'lb_upc'=> array(
                				'attribute_set' 			 =>  'Default',
                				'group'                      => 'Logicbroker',
                				'type'                       => 'varchar',
                				'label'                      => 'LBUPC',
                				'input'                      => 'text',
                				'required' 				 	 => false,
                				'user_defined'           	 => true,
                				'source'                     => '',
                				'sort_order'                 => 9,
                				'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
                				'searchable'                 => false,
                				'used_in_product_listing'    => false,
                				'visible'           	 	 => true,
                				 
                		)
                )
            )
        );
    } 

   
}
