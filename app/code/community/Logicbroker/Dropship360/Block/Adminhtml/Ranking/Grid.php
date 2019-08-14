<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Block_Adminhtml_Ranking_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
  public function __construct()
  {
      parent::__construct();
      $this->setId('logicbrokergrid');
      $this->setDefaultSort('ranking');
      $this->setDefaultDir('ASC');
	  $this->setUseAjax(true);
      $this->setSaveParametersInSession(true);
  }

  protected function _prepareCollection()
  {
      $collection = Mage::getModel('logicbroker/ranking')->getCollection();
      $this->setCollection($collection);
      return parent::_prepareCollection();
  }

  protected function _prepareColumns()
  {
      
      $this->addColumn('lb_vendor_code', array(
          'header'    => Mage::helper('logicbroker')->__('Vendor Code'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'lb_vendor_code',
      ));
      
//       $this->addColumn('change_ranking', array(
//             'header'    => Mage::helper('sales')->__('Select'),
//             'header_css_class' => 'a-center',
//             'type'      => 'checkbox',
//             'name'      => 'change_ranking',
//             'values'    => 'vendor_id',
//             'align'     => 'center',
//             'index'     => 'vendor_id',
//             'sortable'  => false,
            
//         ));
      
       $this->addColumn('ranking', array(
          'header'    => Mage::helper('logicbroker')->__('Position'),
          'align'     =>'right',
          'width'     => '50px',
          'type'  => 'input',
          'index'     => 'ranking',
          'inline_css'     => 'vendor_ranking required-entry validate-digits validate-greater-than-zero',
          //'options' => Mage::getModel('logicbroker/supplier')->getCompanyids(),
      ));
//        $collection = Mage::getModel('logicbroker/ranking')->getCollection();
//        $this->addColumn('rank', array(
//        		'header'    => Mage::helper('logicbroker')->__('Rank'),
//        		'align'     =>'right',
//        		'width'     => '50px',
//        		'index'     => $collection->count(),
//        		//'renderer' => 'Logicbroker_Dropship360_Block_Adminhtml_Ranking_Renderer_Rank'
       		
//        ));
       
// 	  $this->addColumn('ranking_expire', array(
//           'header'    => Mage::helper('logicbroker')->__('Ranking Expire'),
//           'align'     =>'right',
//           'width'     => '50px',
// 	  	  'type' => 'date',
//           'index'     => 'ranking_expire',
//       ));
	

	$this->addColumn('action',
            array(
                'header'    =>  Mage::helper('logicbroker')->__('Action'),
                'width'     => '100',
                'type'      => 'textaction',
                'getter'    => 'getId',
                'actions'   => array(
                    array(
                        'caption'   => Mage::helper('logicbroker')->__('edit'),
                        'url'       => array('base'=> '*/*/edit'),
                        'field'     => 'id'
                    ),
                    array(
                        'caption'   => Mage::helper('logicbroker')->__('delete'),
                        'url'       => array('base'=> '*/*/delete'),
                        'field'     => 'id',
                        'confirm' =>'Are you sure to delete vendor?'
                        
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'stores',
                'is_system' => true,
                'renderer' => 'Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Textaction'
        ));
	
      /*  $this->addColumn('id', array(
            'header' => Mage::helper('logicbroker')->__('id'),
            'index' => 'id',
            'column_css_class'=>'no-display vendorid',//this sets a css class to the column row item
            'header_css_class'=>'no-display vendorid',//this sets a css class to the column header
    )); */ 
      
        //$this->addExportType('*/*/exportCsv', Mage::helper('logicbroker')->__('CSV'));
		//$this->addExportType('*/*/exportXml', Mage::helper('logicbroker')->__('XML'));
	  
      return parent::_prepareColumns();
  }
  
  
  

  public function getRowUrl($row)
  {
      return $this->getUrl('*/*/edit', array('id' => $row->getId()));
  }
 public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current'=>true));
    }

}