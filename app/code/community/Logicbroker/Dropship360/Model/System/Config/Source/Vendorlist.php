<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2014 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Config category source
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Logicbroker_Dropship360_Model_System_Config_Source_Vendorlist
{
    public function toOptionArray($addEmpty = true)
    {
    	$options =array();
    	$collectionVendor = Mage::getModel ( 'logicbroker/inventory' )->getCollection ();
    	
    	
    	$collectionVendor->getSelect ()->joinleft ( array (
    			'lbRanking' => Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/ranking' )
    	), 'lbRanking.lb_vendor_code = main_table.lb_vendor_code', array (
    			'*'
    	) )->where('lbRanking.is_dropship = ?','yes');
    	
    	$collectionVendor->getSelect ()->group('main_table.lb_vendor_code');
    	if($collectionVendor > 0){
    	foreach ($collectionVendor as $vendor) {
    		$options[] = array(
    				'label' => $vendor->getLbVendorName(),
    				'value' => $vendor->getLbVendorCode()
    		);
    	}
    	}
    	array_unshift($options,array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--')),array('value' => 'none', 'label' => Mage::helper('logicbroker')->__('None')));
 		return $options;
    }
    
    public function vendorList($addEmpty = true,$sku)
    {
    	$vendorModel = Mage::getModel('logicbroker/ranking')->getCollection();
    	$vendorModel->getSelect ()->where('main_table.lb_vendor_code not in (select lb_vendor_code from '.Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/inventory' ).' where product_sku = ?)',$sku);
    	$options = array();
    	if($vendorModel->count() > 0 ){
    	foreach ($vendorModel as $vendor) {
    		$options[] = array(
    				'label' => $vendor->getLbVendorCode().'--'.$vendor->getLbVendorName(),
    				'value' => $vendor->getLbVendorCode()
    		);
    	}
    	}
    	array_unshift($options,array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--'))
    	);
    	return $options;
    }
    
    public function vendorListSourcing($addEmpty = true,$sku)
    {
    	$options =array();
    	$collectionVendor = Mage::getModel ( 'logicbroker/inventory' )->getCollection ()->addFieldToFilter('product_sku',$sku);
    	$collectionVendor->getSelect ()->joinleft ( array (
    			'lbRanking' => Mage::getSingleton ( 'core/resource' )->getTableName ( 'logicbroker/ranking' )
    	), 'lbRanking.lb_vendor_code = main_table.lb_vendor_code', array (
    			'*'
    	) )->where('lbRanking.is_dropship = ?','yes');
    	 
    	$collectionVendor->getSelect ()->group('main_table.lb_vendor_code');
    	
    	if($collectionVendor > 0){
    	foreach ($collectionVendor as $vendor) {
    		$options[] = array(
    				'label' => $vendor->getLbVendorName(),
    				'value' => $vendor->getLbVendorCode()
    		);
    	}
    	}
    	array_unshift($options,array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--')));
 		return $options;
    }
    
    public function getAllVendor($addEmpty = true)
    {
    	$options =array();
    	$collectionVendor = Mage::getModel ( 'logicbroker/ranking' )->getCollection ();
    	
    	if($collectionVendor > 0){
    		foreach ($collectionVendor as $vendor) {
    			$options[] = array(
    					'label' => $vendor->getLbVendorCode().'--'.$vendor->getLbVendorName(),
    					'value' => $vendor->getLbVendorCode()
    			);
    		}
    	}
    	array_unshift($options,array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--')));
    	return $options;
    }
}
