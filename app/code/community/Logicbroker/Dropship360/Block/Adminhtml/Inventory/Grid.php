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
      $this->setDefaultSort('updated_at');
      $this->setDefaultDir('DESC');
	  $this->setUseAjax(true);
      $this->setSaveParametersInSession(true);
  }

  protected function _prepareCollection()
  {
      $entityTypeId = Mage::getModel ( 'eav/config' )->getEntityType ( 'catalog_product' )->getEntityTypeId (); 
  	  $prodNameAttrId = Mage::getModel('eav/entity_attribute')->loadByCode($entityTypeId, 'name')->getAttributeId();
      $collection = Mage::getModel('dropship360/inventory')->getCollection();
      $collection->getSelect()->join(array('lbRanking'=>Mage::getSingleton('core/resource')->getTableName('dropship360/ranking')),'lbRanking.lb_vendor_code = main_table.lb_vendor_code', array('lb_vendor_name'));
      $collection->getSelect()->joinLeft(array('prod' => Mage::getSingleton('core/resource')->getTableName('catalog/product')),'prod.sku = main_table.product_sku',array('magento_pro_id'=>'entity_id'));
      $collection->getSelect()->joinLeft(array('cpev' => Mage::getSingleton('core/resource')->getTableName('catalog/product').'_varchar'),'cpev.store_id = 0 AND cpev.entity_id=prod.entity_id AND cpev.attribute_id='.$prodNameAttrId.'',array('product_name' => 'value'));
      $collection->getSelect()->where('prod.entity_id IS NOT NULL');
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
          'header'    => Mage::helper('dropship360')->__('Supplier'),
          'align'     =>'right',
          'width'     => '50px',
      	'filter_index' => 	'lbRanking.lb_vendor_name',
          'index'     => 'lb_vendor_name',
      ));

	  $this->addColumn('stock', array(
          'header'    => Mage::helper('dropship360')->__('Supplier Inventory'),
          'index'     => 'stock',
           'type' =>    'number'
      ));
	  $store = $this->_getStore();
      	  $this->addColumn('cost', array(
          'header'    => Mage::helper('dropship360')->__('Cost'),
          'index'     => 'cost',
          'type' => 'price',
      	  'currency_code' => $store->getBaseCurrency()->getCode(),
      ));
 
      	  $this->addColumn('product_name', array(
      	  		'header'    => Mage::helper('dropship360')->__('Product Name'),
      	  		'align'     =>'left',
      	  		'width'     => '80px',
      	  		'index'     => 'product_name',
      	  		'filter_index'=>'cpev.value', 
      	  ));
	 $this->addColumn('product_sku', array(
          'header'    => Mage::helper('dropship360')->__('Product Sku'),
          'align'     =>'left',
          'width'     => '80px',
          'index'     => 'product_sku',
	 	   'renderer' => 'Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Skuaction'
      ));

	 $this->addColumn('lb_vendor_sku', array(
	 		'header'    => Mage::helper('dropship360')->__('Supplier Sku'),
	 		'align'     =>'left',
	 		'width'     => '80px',
	 		'index'     => 'lb_vendor_sku',
	 		'renderer' => 'Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Skuaction'
	 ));
	  $this->addColumn('updated_at', array(
          'header'    => Mage::helper('dropship360')->__('Last Sync'),
          'index'     => 'updated_at',
          'width'     => '80px',
          'default'   => '--',    
          'type'     => 'datetime',
	  	  'filter_index'=> 'main_table.updated_at'	
      ));
	  
	  // below code added for Jira ticket 734
	  $this->addColumn('action',
	  		array(
	  				'header'    =>  Mage::helper('dropship360')->__('Action'),
	  				'width'     => '100',
	  				'type'      => 'action',
	  				'getter'    => 'getMagentoProId',
	  				'actions'   => array(
	  						array(
	  								'caption'   => Mage::helper('dropship360')->__('Edit'),
	  								'url'       => array('base'=> 'adminhtml/catalog_product/edit/back/edit/tab/product_info_tabs_vendor_tab'),
	  								'field'     => 'id'
	  						)
	  				),
	  				'filter'    => false,
	  				'sortable'  => false,
	  				'index'     => 'stores',
	  				'is_system' => true,
	  		));
	  $this->addExportType('*/*/exportCsv', Mage::helper('dropship360')->__('CSV'));
		$this->addExportType('*/*/exportXml', Mage::helper('dropship360')->__('XML'));
	  
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