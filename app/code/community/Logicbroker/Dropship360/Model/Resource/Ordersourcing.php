<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Resource_Ordersourcing extends Mage_Core_Model_Mysql4_Abstract
{

    protected function _construct()
    {
        $this->_init("dropship360/ordersourcing", "id");
    }
    
 	public function saveConfig($path, $value, $scope = 'default', $scopeId = 0)
    {
    	try {
    		
        $writeAdapter = $this->_getWriteAdapter();
        $select = $writeAdapter->select()
            ->from( $this->getTable('core/config_data'))
            ->where('path = ?', $path)
            ->where('scope = ?', $scope)
            ->where('scope_id = ?', $scopeId);
        $row = $writeAdapter->fetchRow($select);

        $newData = array(
            'scope'     => $scope,
            'scope_id'  => $scopeId,
            'path'      => $path,
            'value'     => $value
        );
        $writeAdapter->beginTransaction();
        if ($row) {
            $whereCondition = array('config_id =?' => $row['config_id']);
            $writeAdapter->update($this->getTable('core/config_data'), $newData, $whereCondition);
        } else {
            $writeAdapter->insert($this->getTable('core/config_data'), $newData);
        }
        $writeAdapter->commit();
        } catch (Mage_Core_Exception $e) {
        	$writeAdapter->rollBack();
        	throw $e;
        } catch (Exception $e){
        	$adapter->rollBack();
        	Mage::logException($e);
        }
    }
}