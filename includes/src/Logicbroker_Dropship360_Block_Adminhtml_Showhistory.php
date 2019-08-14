<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Block_Adminhtml_Showhistory extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_ranking';
    $this->_blockGroup = 'logicbroker';
    $this->_headerText = Mage::helper('logicbroker')->__('Supplier Ranking Log');
    $this->__addBackButton = Mage::helper('logicbroker')->__('Back');
    $this->addButton('back',array(
            'label'     => 'Back',
            'onclick'   => 'setLocation(\'' . $this->getUrl('*/*/') .'\')',
            'class'     => 'back',
        )
    );
    parent::__construct();
    $this->removeButton('add');
    
  }
 
  public function getLogCollection(){ 	
  	$collection = Mage::getModel('logicbroker/rankinglog')->getCollection();
  	$collection->getSelect()->order('created_at desc');
  	$logtable = array();
  	foreach($collection as $value){
  		$logtable[$value->getLabel()] = unserialize($value->getRankingData());
  		$logtable[$value->getLabel()]['created'] = $value->getCreatedAt();
  	}
  	
	return $logtable;
  } 
}
