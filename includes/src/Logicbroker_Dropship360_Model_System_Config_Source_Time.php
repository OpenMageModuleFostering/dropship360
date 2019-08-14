<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */ 
class Logicbroker_Dropship360_Model_System_Config_Source_Time
{
    public function toOptionArray()
    {
       /*return array(
            array(
                'value' => 'key1',
                'label' => 'Value 1',
            ),
            array(
                'value' => 'key2',
                'label' => 'Value 2',
            ),
        );*/
    	$j = 0;
	 	for( $i=10;$i<=60;$i++ ) {
	 		if($i >= $j+10 ){
            $hour[$i] = str_pad($i, 2, '0', STR_PAD_LEFT);
            $j = $j+10;
	 		}
            
        }
		return $hour;
		
    }
}