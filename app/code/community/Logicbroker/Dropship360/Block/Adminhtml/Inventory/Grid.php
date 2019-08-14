<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Block_Adminhtml_Inventory_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
  public function __construct()
  {
      parent::__construct();
      $this->setId('logicbrokergrid');
      $this->setDefaultSort('cost');
      $this->setDefaultDir('ASC');
	  $this->setUseAjax(true);
      $this->setSaveParametersInSession(true);
  }

  protected function _prepareCollection()
  {
     $prodNameAttrId = Mage::getModel('eav/entity_attribute')->loadByCode('4', 'name')->getAttributeId();
      $collection = Mage::getModel('logicbroker/inventory')->getCollection();
      $collection->getSelect()->join(array('lbRanking'=>Mage::getSingleton('core/resource')->getTableName('logicbroker/ranking')),'lbRanking.lb_vendor_code = main_table.lb_vendor_code', array('lb_vendor_name'));
      $collection->getSelect()->joinLeft(array('prod' => Mage::getSingleton('core/resource')->getTableName('catalog/product')),'prod.sku = main_table.product_sku',array('magento_pro_id'=>'entity_id'));
      $collection->getSelect()->joinLeft(array('cpev' => Mage::getSingleton('core/resource')->getTableName('catalog/product').'_varchar'),'cpev.entity_id=prod.entity_id AND cpev.attribute_id='.$prodNameAttrId.'',array('product_name' => 'value'));
      
      $this->setCollection($collection);
      return parent::_prepareCollection();
  }

  protected function _getStore()
  {
  	$storeId = (int) $this->getRequest()->getParam('store', 0);
  	return Mage::app()->getStore($storeId);
  }
  
  protected function _prepareColumns()
  {
      $this->addColumn('lb_vendor_name', array(
          'header'    => Mage::helper('logicbroker')->__('Vendor'),
          'align'     =>'right',
          'width'     => '50px',
      	'filter_index' => 	'lbRanking.lb_vendor_name',
          'index'     => 'lb_vendor_name',
      ));

	  $this->addColumn('stock', array(
          'header'    => Mage::helper('logicbroker')->__('Vendor Inventory'),
          'index'     => 'stock',
           'type' =>    'number'
      ));
	  $store = $this->_getStore();
      	  $this->addColumn('cost', array(
          'header'    => Mage::helper('logicbroker')->__('Cost'),
          'index'     => 'cost',
          'type' => 'price',
      	  'currency_code' => $store->getBaseCurrency()->getCode(),
      ));
 
      	  $this->addColumn('product_name', array(
      	  		'header'    => Mage::helper('logicbroker')->__('Product Name'),
      	  		'align'     =>'left',
      	  		'width'     => '80px',
      	  		'index'     => 'product_name',
      	  		'filter_index'=>'cpev.value', 
      	  		//'renderer' => 'Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Nameaction'
      	  ));
	 $this->addColumn('product_sku', array(
          'header'    => Mage::helper('logicbroker')->__('Product Sku'),
          'align'     =>'left',
          'width'     => '80px',
          'index'     => 'product_sku',
	 	   'renderer' => 'Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Skuaction'
      ));

	 $this->addColumn('lb_vendor_sku', array(
	 		'header'    => Mage::helper('logicbroker')->__('Vendor Sku'),
	 		'align'     =>'left',
	 		'width'     => '80px',
	 		'index'     => 'lb_vendor_sku',
	 		'renderer' => 'Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Skuaction'
	 ));
	  $this->addColumn('updated_at', array(
          'header'    => Mage::helper('logicbroker')->__('Last Sync'),
          'index'     => 'updated_at',
          'width'     => '80px',
          'default'   => '--',    
          'type'     => 'datetime',
	  	  'filter_index'=> 'main_table.updated_at'	
      ));
	  
	  // below code added for Jira ticket 734
	  $this->addColumn('action',
	  		array(
	  				'header'    =>  Mage::helper('logicbroker')->__('Action'),
	  				'width'     => '100',
	  				'type'      => 'action',
	  				'getter'    => 'getMagentoProId',
	  				'actions'   => array(
	  						array(
	  								'caption'   => Mage::helper('logicbroker')->__('Edit'),
	  								'url'       => array('base'=> 'adminhtml/catalog_product/edit/back/edit/tab/product_info_tabs_vendor_tab'),
	  								'field'     => 'id'
	  						)
	  				),
	  				'filter'    => false,
	  				'sortable'  => false,
	  				'index'     => 'stores',
	  				'is_system' => true,
	  		));
	  $this->addExportType('*/*/exportCsv', Mage::helper('logicbroker')->__('CSV'));
		$this->addExportType('*/*/exportXml', Mage::helper('logicbroker')->__('XML'));
	  
      return parent::_prepareColumns();
  }

  public function getRowUrl($row)
  {
      //return $this->getUrl('*/*/edit', array('vendor_id' => $row->getVendorId()));
  }
 public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current'=>true));
    }

}