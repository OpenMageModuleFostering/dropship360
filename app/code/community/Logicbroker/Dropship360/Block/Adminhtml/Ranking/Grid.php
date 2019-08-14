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
      $collection = Mage::getModel('dropship360/ranking')->getCollection();
      $this->setCollection($collection);
      return parent::_prepareCollection();
  }

  protected function _prepareColumns()
  {
      
      $this->addColumn('lb_vendor_code', array(
          'header'    => Mage::helper('dropship360')->__('Supplier Code'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'lb_vendor_code',
      )); 
       $this->addColumn('ranking', array(
          'header'    => Mage::helper('dropship360')->__('Position'),
          'align'     =>'right',
          'width'     => '50px',
          'type'  => 'input',
          'index'     => 'ranking',
          'inline_css'     => 'vendor_ranking required-entry validate-digits validate-greater-than-zero',
      ));
	$this->addColumn('action',
            array(
                'header'    =>  Mage::helper('dropship360')->__('Action'),
                'width'     => '100',
                'type'      => 'textaction',
                'getter'    => 'getId',
                'actions'   => array(
                    array(
                        'caption'   => Mage::helper('dropship360')->__('edit'),
                        'url'       => array('base'=> '*/*/edit'),
                        'field'     => 'id'
                    ),
                    array(
                        'caption'   => Mage::helper('dropship360')->__('delete'),
                        'url'       => array('base'=> '*/*/delete'),
                        'field'     => 'id',
                        'confirm' =>'Are you sure to delete the Supplier?'
                        
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'stores',
                'is_system' => true,
                'renderer' => 'Logicbroker_Dropship360_Block_Adminhtml_Widget_Grid_Column_Textaction'
        ));  
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