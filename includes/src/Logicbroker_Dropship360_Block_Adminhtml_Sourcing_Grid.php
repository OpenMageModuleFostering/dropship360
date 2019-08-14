<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Block_Adminhtml_Sourcing_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
  public function __construct()
  {
      parent::__construct();
      $this->setId('sourcinggrid');
      $this->setDefaultSort('created_at');
      $this->setDefaultDir('DESC');
      $this->setSaveParametersInSession(true);
  }

  protected function _prepareCollection()
  {
    $tableName = Mage::getSingleton("core/resource")->getTableName('sales/order_item');
    
    $collection = Mage::getModel('sales/order_item')->getCollection()->addFieldToSelect(array('qty_ordered','order_id'));
    
  	$collection->getSelect()->columns('if(`main_table`.`row_total` <> 0, `main_table`.`row_total`,(SELECT config.`row_total` FROM '.$tableName.' as simple INNER JOIN '.$tableName.' as config 
ON (simple.parent_item_id = config.item_id) where simple.item_id= `main_table`.`item_id`)) as row_total');
  	
  	$collection->getSelect()->join(array('lbItems'=>Mage::getSingleton('core/resource')->getTableName('logicbroker/orderitems')),
  			'lbItems.item_id = main_table.item_id', array('item_order_id','lb_item_id'=>'id','lb_item_status','magento_sku'=>'sku','lb_vendor_code','lb_vendor_sku','vendor_cost','updated_by','updated_at'));
  	
  	$collection->getSelect()->join(array('salesOrder'=>Mage::getSingleton('core/resource')->getTableName('sales/order')),
  			'salesOrder.entity_id = main_table.order_id', array('increment_id','status','created_at'));
  	
  	$collection->getSelect()->joinleft(array('lbRanking'=>Mage::getSingleton('core/resource')->getTableName('logicbroker/ranking')),
  			'lbRanking.lb_vendor_code = lbItems.lb_vendor_code', array('lb_vendor_name'));
  	$this->setCollection($collection);
      return parent::_prepareCollection();
  }

  protected function _getStore()
  {
  	$storeId = (int) $this->getRequest()->getParam('store', 0);
  	return Mage::app()->getStore($storeId);
  }
  
  protected function prepareOptionValue(){
  	
  	$itemStatus = array_merge(Mage::helper('logicbroker')->getItemStatuses(), array('Sent to Vendor'));
  	foreach($itemStatus as $status )
  	{
  		$options[$status] = Mage::helper('logicbroker')->__($status);
  	}
  	return $options;
  }
  
  protected function _prepareColumns()
  {
      $this->addColumn('increment_id', array(
          'header'    => Mage::helper('logicbroker')->__('Order #'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'increment_id',	
      ));
	  $this->addColumn('created_at', array(
          'header'    => Mage::helper('logicbroker')->__('Order Date'),
          'align'     =>'right',
          'width'     => '50px',
	  		'type' => 'datetime',
	  		'filter_index' => 'salesOrder.created_at',
          'index'     => 'created_at',
      ));
	  $this->addColumn('magento_sku', array(
          'header'    => Mage::helper('logicbroker')->__('Product Sku'),
          'align'     =>'right',
          'width'     => '50px',
	  		"filter_index" => "lbItems.sku",
          'index'     => 'magento_sku',
      ));
	  
	 $this->addColumn('lb_vendor_name', array(
          'header'    => Mage::helper('logicbroker')->__('Supplier'),
          'align'     =>'left',
          'width'     => '80px',
          'index'     => 'lb_vendor_name',
      ));
	  
	  
	  $this->addColumn('lb_vendor_sku', array(
	  		'header'    => Mage::helper('logicbroker')->__('Supplier Sku'),
	  		'align'     =>'left',
	  		'width'     => '80px',
	  		'index'     => 'lb_vendor_sku',
	  ));
	  $store = $this->_getStore();
      $this->addColumn('vendor_cost', array(
          'header'    => Mage::helper('logicbroker')->__('Supplier Cost'),
          'align'     =>'left',
          'width'     => '80px',
          'index'     => 'vendor_cost',
      		'type' => 'price',
      		'currency_code' => $store->getBaseCurrency()->getCode(),
      ));
	  
      $this->addColumn('row_total', array(
          'header'    => Mage::helper('logicbroker')->__('Price'),
          'align'     =>'left',
          'width'     => '80px',
          'index'     => 'row_total',
      		'type' => 'price',
      		'currency_code' => $store->getBaseCurrency()->getCode(),
      ));
	  $this->addColumn('qty_ordered', array(
          'header'    => Mage::helper('logicbroker')->__('Qty'),
          'align'     =>'left',
          'width'     => '80px',
          'index'     => 'qty_ordered',
      ));
	  
	  
	  $this->addColumn('lb_item_status', array(
	  		'header'    => Mage::helper('logicbroker')->__('Drop Ship Status'),
	  		'align'     =>'left',
	  		'width'     => '80px',
	  		'index'     => 'lb_item_status',
	  		'type'  => 'options',
	  		'options' => $this->prepareOptionValue()
	  ));
 
	  $this->addColumn('status', array(
	  		'header'    => Mage::helper('logicbroker')->__('Order Status'),
	  		'align'     =>'left',
	  		'width'     => '80px',
	  		'index'     => 'status',
	  		'type'  => 'options',
	  		'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
	  	  ));

	  $this->addColumn('updated_by', array(
	  		'header'    => Mage::helper('logicbroker')->__('Last Updated By'),
	  		'align'     =>'left',
	  		'width'     => '80px',
	  		'index'     => 'updated_by',
	  		'type'  => 'options',
	  		'options' => array(
            'Pending'  => Mage::helper('catalog')->__('Pending'),
            'User'   => Mage::helper('catalog')->__('User'),
	  		'Cron'   => Mage::helper('catalog')->__('Cron'),
	  		'logicbroker'   => Mage::helper('catalog')->__('logicbroker'),
        	)
	  ));
	  $this->addColumn('updated_at', array(
	  		'header'    => Mage::helper('logicbroker')->__('Updated On'),
	  		'align'     =>'right',
	  		'width'     => '50px',
	  		'type' => 'datetime',
	  		'filter_index' => 'lbItems.updated_at',
	  		'index'     => 'updated_at',
	  		
	  ));
	  
	  $this->addColumn('view',
	  		array(
	  				'header'    => Mage::helper('sales')->__('Action'),
	  				'width'     => '50px',
	  				'type'      => 'action',
	  				'renderer'   => 'logicbroker/adminhtml_sourcing_history_renderer_action',
	  				'filter'    => false,
	  				'sortable'  => false,
	  				'index'     => 'stores',
	  				'is_system' => true,
	  		));
	  
	  $this->addColumn('action',
	  		array(
	  				'header'    =>  Mage::helper('logicbroker')->__('Edit'),
	  				'width'     => '100px',
	  				'type'      => 'textaction',
	  				'filter'    => false,
	  				'sortable'  => false,
	  				'index'     => 'stores',
	  				'is_system' => true,
	  				'renderer' => 'Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Textaction'
	  		));
	   
     
	  $this->addExportType('*/*/exportCsv', Mage::helper('logicbroker')->__('CSV'));
	  $this->addExportType('*/*/exportXml', Mage::helper('logicbroker')->__('XML'));
      return parent::_prepareColumns();
  }

  public function getRowUrl($row)
  {
      //return $this->getUrl('*/*/edit', array('lb_item_id' => $row->getLbItemId()));
  }
 public function getGridUrl()
    {
        //return $this->getUrl('*/*/grid', array('_current'=>true));
    }

}