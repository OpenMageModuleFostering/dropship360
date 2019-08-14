<?php
class Logicbroker_Dropship360_Block_Adminhtml_Reports_Activitymonitor_Email extends Mage_Adminhtml_Block_Widget_Grid
{
	 /**
     * Block constructor, prepare grid params
     *
     * @param array $arguments
     */
    public function __construct()
    {
        parent::__construct();
		$this->setPagerVisibility(false);
		$this->setFilterVisibility(false);
		$this->setSortable(false);
        $this->setUseAjax(true);
		$form = 'email_fieldset';
        $this->setRowClickCallback("$form.chooserGridRowClick.bind($form)");
        $this->setCheckboxCheckCallback("$form.chooserGridCheckboxCheck.bind($form)");
        $this->setRowInitCallback("$form.chooserGridRowInit.bind($form)");

        if ($this->getRequest()->getParam('collapse')) {
            $this->setIsCollapsed(true);
        }
    }
	
	protected function _prepareLayout()
	{
		$this->unsetChild('reset_filter_button');
		$this->unsetChild('search_button');
	}	


    /**
     * Prepare rules collection
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('admin/user')->getCollection();
		$roleTable = Mage::getSingleton('core/resource')->getTableName('admin/role');
		$collection->getSelect()->join(array('role'=> $roleTable),
        'main_table.user_id=role.user_id', array('role_name'));
		$this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepare columns for rules grid
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareColumns()
    {
		$this->addColumn('user_id', array(
			'type'     => 'checkbox',
			'field_name' => 'user_id',
		    'values'    => 'email',
            'index'     => 'email',
            'use_index' => true,
		));
		$this->addColumn('role_name', array(
            'header'    => Mage::helper('logicbroker')->__('Role'),
            'align'     => 'right',
            'width'     => '50px',
            'index'     => 'role_name',
        ));
		
       $this->addColumn('firstname', array(
            'header'    => Mage::helper('logicbroker')->__('Name'),
            'align'     => 'right',
            'width'     => '50px',
            'index'     => 'firstname',
        ));
		
		$this->addColumn('email', array(
            'header'    => Mage::helper('logicbroker')->__('Email Address'),
            'align'     => 'right',
            'width'     => '50px',
            'index'     => 'email',
        ));

        return parent::_prepareColumns();
    }

	
}