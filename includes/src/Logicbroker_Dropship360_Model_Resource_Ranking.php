<?php

/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Model_Resource_Ranking extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init("dropship360/ranking", "id");
    }
}