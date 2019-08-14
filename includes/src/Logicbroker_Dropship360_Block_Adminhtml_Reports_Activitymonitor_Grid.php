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
 * Adminhtml sales report grid block
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Logicbroker_Dropship360_Block_Adminhtml_Reports_Activitymonitor_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
   

    public function __construct()
    {
      parent::__construct();
      $this->setId('activitymonitorgrid');
      $this->setSaveParametersInSession(false);
      $this->setDefaultSort('updated_at');
      $this->setDefaultDir('DESC');
      $this->setFilterVisibility(false);
      $this->setPagerVisibility(true);
      
    }

protected function _prepareCollection()
  {
   		    
  	$collection = Mage::registry('activity_report_collection');
  	$this->setCollection($collection);
    return parent::_prepareCollection();
  }

  
 
  protected function _prepareColumns()
  {
	  	$this->addColumn('period', array(
	  			'header'    => Mage::helper('logicbroker')->__('Period Open'),
	  			'align'     =>'right',
	  			'width'     => 50,
	  			'index'     => 'period',
	  			'sortable'      => false,
	  			 'filter' => false,
	  			'renderer'      => 'logicbroker/adminhtml_reports_activitymonitor_periodopen',
	  	));
	  	$this->addColumn('increment_id', array(
	  			'header'    => Mage::helper('logicbroker')->__('Order#'),
	  			'align'     =>'right',
	  			'width'     => 50,
	  			'index'     => 'increment_id',
	  			'sortable'      => false,
	  			'filter' => false
	  	));
	  	$this->addColumn('lb_vendor_name', array(
	  			'header'    => Mage::helper('logicbroker')->__('Supplier'),
	  			'align'     =>'right',
	  			'width'     => 50,
	  			'index'     => 'lb_vendor_name',
	  			'sortable'      => false,
	  			'filter' => false
	  	));

	  	$this->addColumn('vendor_cost', array(
	  			'header'    => Mage::helper('logicbroker')->__('Cost'),
	  			'align'     =>'right',
	  			'width'     => 50,
	  			'index'     => 'vendor_cost',
	  			'sortable'      => false,
	  			'filter' => false
	  	));
	  	$this->addColumn('product_name', array(
	  			'header'    => Mage::helper('logicbroker')->__('Product Name'),
	  			'align'     =>'left',
	  			'width'     => 50,
	  			'index'     => 'product_name',
	  			'sortable'      => false,
	  			'filter' => false
	  	));
	  	
	  	$this->addColumn('sku', array(
	  			'header'    => Mage::helper('logicbroker')->__('Product Sku'),
	  			'align'     =>'left',
	  			'width'     => 50,
	  			'index'     => 'sku',
	  			'sortable'      => false,
	  			'filter' => false,
	  			'renderer' => 'Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Skuaction'
	  	));
	  	
	  	$this->addColumn('lb_vendor_sku', array(
	  			'header'    => Mage::helper('logicbroker')->__('Supplier Sku'),
	  			'align'     =>'left',
	  			'width'     => 50,
	  			'index'     => 'lb_vendor_sku',
	  			'sortable'      => false,
	  			'filter' => false,
	  			'renderer' => 'Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Skuaction'
	  	));
	  	
	  	$this->addColumn('updated_at', array(
	  			'header'    => Mage::helper('logicbroker')->__('Last Update'),
	  			'align'     =>'left',
	  			'width'     => 50,
	  			'index'     => 'updated_at',
	  			'type'     => 'datetime',
	  			'sortable'      => false,
	  			'filter' => false
	  	));
	  	
	  	$this->addColumn('created_at', array(
	  			'header'    => Mage::helper('logicbroker')->__('Order Date'),
	  			'align'     =>'left',
	  			'width'     => 80,
	  			'index'     => 'created_at',
	  			'type'     => 'datetime',
	  			'sortable'      => false,
	  			'filter' => false
	  	));
	  	
	  	$this->addColumn('lb_item_status', array(
	  			'header'    => Mage::helper('logicbroker')->__('Item Status'),
	  			'align'     =>'left',
	  			'width'     => 50,
	  			'index'     => 'lb_item_status',
	  			'sortable'      => false,
	  			'filter' => false
	  	));
		return parent::_prepareColumns();
  }
  
  public function getRowUrl($row)
  {
  	return; 
  }
}
