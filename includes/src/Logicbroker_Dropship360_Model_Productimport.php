<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */
class Logicbroker_Dropship360_Model_Productimport {
	protected $conn;
	protected $prePopulatedAttribute = array ();
	protected $catalogTable;
	protected $productCategory;
	protected $allowedAttribute = array('name','short_description','description','weight','price','msrp','manufacturer','action','lb_upc','lb_manufacturer_product_number');
	protected $preDefineAttribute = array (
			'status' => 2,
			'visibility' => 1,
			'tax_class_id' => 0 
	);
	protected $cachedPreDefineAttribute = array ();
	
	/* Initialise connection and predefine attribute which will not available in REST request */
	public function _init() 
	{
		$this->catalogTable = Mage::getSingleton("core/resource")->getTableName('catalog/product');
		$this->productCategory = Mage::getSingleton("core/resource")->getTableName('catalog/category_product');
		foreach ( $this->preDefineAttribute as $attrname => $attrvalue ) {
			$attributeInfo = $this->getAttributeInfo ( $attrname, $attrvalue );
			$this->cachedPreDefineAttribute [$attrname] = array (
					'id' => $attributeInfo ['attribute_id'],
					'option_value' => $attrvalue,
					'table_name' => $attributeInfo ['table_name'], 
					'type' => $attributeInfo ['type']
			);
		}
		
		$this->conn = $this->getDatabaseConnection ();
		return $this;
	}
	public function getDatabaseConnection() 
	{
		return Mage::getSingleton ( 'core/resource' )->getConnection ( 'core_write' );
	}
	public function getEntityTypeIdByCode() 
	{
		return Mage::getModel ( 'eav/config' )->getEntityType ( 'catalog_product' )->getEntityTypeId ();
	}
	public function getAttributeCodeById($entity_type, $attribute_code) 
	{
		return Mage::getModel ( 'eav/entity_attribute' )->loadByCode ( $entity_type, $attribute_code )->getAttributeId ();
	}
	public function getAttributeSetCodeById() 
	{
		return Mage::getModel ( 'catalog/product' )->getDefaultAttributeSetId ();
	}
	
	
	
	
	/* Function will prepare the attribute information @return associative array */
	public function getAttributeInfo($name, $value) 
	{
		$attributeInfo = array ();
		$attr = Mage::getModel('eav/config')->getAttribute($this->getEntityTypeIdByCode(),$name);
		$attributeInfo ['attribute_id'] = $attr->getAttributeId ();
		$attributeInfo ['table_name'] = $attr->getBackendTable ();
		$attributeInfo ['type'] = $attr->getBackendType ();
		if ($attr->getFrontendInput () == 'select') {
			if ($attr->usesSource () && !in_array($name,array('status','visibility','tax_class_id'))) {
				
				if($attr->getSource ()->getOptionId ( $value ))
				{
					$attributeInfo ['option_value'] = $attr->getSource ()->getOptionId ( $value );
				}
				else{ 
					
					$attributeInfo ['option_value'] = $this->createAttributeOption($attr->getAttributeId (),$value);
				} 
			} else{
				$attributeInfo ['option_value'] = $value;
			}
		}else{
				$attributeInfo ['option_value'] = $value;
			}
		return $attributeInfo;
	}
	
	
	protected function createAttributeOption($attributeId,$attributeValue)
	{	
		$optionTable        = Mage::getSingleton ( 'core/resource' )->getTableName('eav/attribute_option');
		$optionValueTable   = Mage::getSingleton ( 'core/resource' )->getTableName('eav/attribute_option_value');
		$data = array(
				'attribute_id' => $attributeId,
				'sort_order'   => 0,
		);
		$this->conn->insert($optionTable, $data);
		$intOptionId = $this->conn->lastInsertId($optionTable);
		$data = array(
				'option_id' => $intOptionId,
				'store_id'  => 0,
				'value'     => $attributeValue,
		);
		$this->conn->insert($optionValueTable, $data);
		return $intOptionId;		
	}
	
	
	/* Function will insert data using Multi-insert in catalog attribute tables  */
	public function insertAttributeData($lastId, $data, $sku)
	{
		$insvalues = '';
		$i = 1;
		
		foreach ( $data as $key => $val ) {
			$countVal = count ( $val );
			if ($key == 'int') {
				foreach ( $val as $value ) {
					
					if ($i < $countVal)
						$comma = ',';
					else
						$comma = '';
					$insvalues .= "(" . $value ['entity_type_id'] . ',' . $value ['attribute_id'] . ',' . $value ['store_id'] . ',' . $value ['entity_id'] . ',' . $value ['value'] . ")" . $comma;
					$i++;
				}
				$t = $this->catalogTable . '_int';
				$i = 1;
				$this->conn->query ( "INSERT INTO $t (entity_type_id,attribute_id,store_id,entity_id,value) VALUES " . $insvalues );
				$insvalues = '';
			}
			
			if ($key == 'varchar') {
				foreach ( $val as $value ) {
					
					if ($i < $countVal)
						$comma = ',';
					else
						$comma = '';
					
					$insvalues .= "(" . $value ['entity_type_id'] . ',' . $value ['attribute_id'] . ',' . $value ['store_id'] . ',' . $value ['entity_id'] . ",'" . $value ['value'] . "')" . $comma;
					$i++;
				}
				$t = $this->catalogTable . '_varchar';
				$i = 1;
				$this->conn->query ( "INSERT INTO $t (entity_type_id,attribute_id,store_id,entity_id,value) VALUES " . $insvalues );
				$insvalues = '';
			}
			if ($key == 'datetime') {
				foreach ( $val as $value ) {
					
					if ($i < $countVal)
						$comma = ',';
					else
						$comma = '';
					
					$insvalues .= "(" . $value ['entity_type_id'] . ',' . $value ['attribute_id'] . ',' . $value ['store_id'] . ',' . $value ['entity_id'] . ",'" . $value ['value'] . "')" . $comma;
					$i++;
				}
				$t = $this->catalogTable . '_datetime';
				$i = 1;
				$this->conn->query ( "INSERT INTO $t (entity_type_id,attribute_id,store_id,entity_id,value) VALUES " . $insvalues );
				$insvalues = '';
			}
			
			if ($key == 'decimal') {
				foreach ( $val as $value ) {
					
					if ($i < $countVal)
						$comma = ',';
					else
						$comma = '';
					
					$insvalues .= "(" . $value ['entity_type_id'] . ',' . $value ['attribute_id'] . ',' . $value ['store_id'] . ',' . $value ['entity_id'] . ",'" . $value ['value'] . "')" . $comma;
					
					$i++;
				}
				$t = $this->catalogTable . '_decimal';
				$i = 1;
				$this->conn->query ( "INSERT INTO $t (entity_type_id,attribute_id,store_id,entity_id,value) VALUES " . $insvalues );
				$insvalues = '';
			}
			if ($key == 'text') {
				foreach ( $val as $value ) {
					if ($i < $countVal)
						$comma = ',';
					else
						$comma = '';
					
					$insvalues .= "(" . $value ['entity_type_id'] . ',' . $value ['attribute_id'] . ',' . $value ['store_id'] . ',' . $value ['entity_id'] . ",'" . $value ['value'] . "')" . $comma;
					
					$i++;
				}
				$t = $this->catalogTable . '_text';
				$i = 1;
				$this->conn->query ( "INSERT INTO $t (entity_type_id,attribute_id,store_id,entity_id,value) VALUES " . $insvalues );
				$insvalues = '';
			}
		}	
	}
	
	protected function assignCategory($id,$lastId)
	{
		$arrCategory = array(
				'category_id'=> $id,
				'product_id'=>	$lastId,
				'position'=> 0
		);
		$this->conn->insert($this->productCategory,$arrCategory);
	}
	
	/* Function will insert the predefine array in the catalog attribute tables */
	protected function insertPredefineAttribute($lastId, $sku)
	{	
		$preAttribue = array();
		$this->prepareAttributeInsertQuery ( $lastId, $val, $data ['sku'] );
		foreach ( $this->preDefineAttribute as $name => $value ) {
			$type = $this->prePopulatedAttribute ['attribute_detail'] [$sku] [$name] ['type'];
			$preAttribue [$type] [] = $this->prepareAttributeInsertQuery ( $lastId, array (
					'name' => $name,
					'value' => $value 
			), $sku );
		}
		$this->insertAttributeData ( $lastId, $preAttribue, $sku );	
	}
	
	/* function prepare the attribute data array for multi-insert  */
	protected function prepareAttributeInsertQuery($lastId, $data, $sku)
	{
		$attributeId = $this->prePopulatedAttribute ['attribute_detail'] [$sku] [$data ['name']] ['id'];
		$attrValue = $this->prePopulatedAttribute ['attribute_detail'] [$sku] [$data ['name']] ['option_value'];
		$type = $this->prePopulatedAttribute ['attribute_detail'] [$sku] [$data ['name']] ['type'];
		$entityTypeId = $this->prePopulatedAttribute ['entity_type_id'];	
		$attribute = array (
				
				'entity_type_id' => $entityTypeId,
				'attribute_id' => $attributeId,
				'store_id' => 0,
				'entity_id' => $lastId,
				'value' => $attrValue 
		);		
		return $attribute;
	}
	
	/* Function will collect all the attribute values which will minimize the database call */
	protected function initilatizeAttributeValue($productAttributeArray) 
	{
		$arrAttribute = array ();
		$arrAttribute ['attribute_set_id'] = $this->getAttributeSetCodeById ();
		$arrAttribute ['entity_type_id'] = $this->getEntityTypeIdByCode ();
		$arrAttribute ['attribute_detail'] = $this->prepareAttributeDetail ( $productAttributeArray );
		$this->prePopulatedAttribute = $arrAttribute;
		return $this->prePopulatedAttribute;
	}

	protected function prepareAttributeDetail($attributeData) 
	{
		$attributeDetails = array ();
		$tempvar = array ();
		foreach ( $attributeData as $name ) {
			foreach ( $name ['attributeData'] as $key => $val ) {
				$attributeInfo = $this->getAttributeInfo ( $val ['name'], $val ['value'] );
				$tempvar [$name ['sku']] [$val ['name']] = array (
						'id' => $attributeInfo ['attribute_id'],
						'option_value' => $attributeInfo ['option_value'],
						'table_name' => $attributeInfo ['table_name'],
						'type' => $attributeInfo ['type'] 
				);
			}
			$attributeDetails [$name ['sku']] = array_merge ( $this->cachedPreDefineAttribute, $tempvar [$name ['sku']] );
		}
		return $attributeDetails;
	}
	
	/* Execution will start from here which will process data  */
	public function processData($restArray) 
	{
		$result = array();
		$productAttributeArray = $this->_processRequestArray ( $restArray );
		
		foreach ( $productAttributeArray as $product => $data ) {			
			//validate REST API request 
			$attributeValidation = $this->validateAttibute($data);
			if(!$attributeValidation['is_validate'] || $attributeValidation['data_invalid']){				
				if($attributeValidation['data_invalid'] && $attributeValidation['error'])
				$comma = ',';
				$result[$data ['sku']] = array('Sku Cannot Import With Attribute : '.implode(',',$attributeValidation['error']).$comma.implode(',',$attributeValidation['data_invalid']));
				continue;
			}
			$checkSku = trim($data ['sku']);
			if(empty($checkSku)){
				$result[$data ['sku']] = array('Sku Can Not Be Empty');
				continue;
			}
			
			if(strtolower($data['extra']['action']) != 'a' && !$this->isSkuExsist($data ['sku']) ){
				$result[$data ['sku']] = array('Sku Not Exists Please Check Action Type');
			}
			
			switch (strtolower($data['extra']['action'])) {		
				case 'a':
					if(!$this->isSkuExsist($data ['sku']))
					{
						if (is_array ($productAttributeArray )) {
							$this->initilatizeAttributeValue ( $productAttributeArray );
						} else {
							return false;
						}
						$result[$data ['sku']] = ($this->addProduct ( $data )) ? array('Product Added Successfully'):array('Error In Creating EntityID for '.$data ['sku']); 
					}else 
						$result[$data ['sku']] = array('Sku Already Exists Please Check Action Type');
				break;
				
				case 'u':
					if($this->isSkuExsist($data ['sku']))
						$result[$data ['sku']] = ($this->updateProduct ( $data ['sku'], $data ['attributeData'])) ? array('Product Updated Successfully'):array('Unspecified Error While Updating Product');  
					else 
						$result[$data ['sku']] = array('Sku does not Exists Please Check Action Type');
				break;
				
				case 'd':
					if($this->isSkuExsist($data ['sku']))
						$result[$data ['sku']] = ($this->deleteProduct ( $data ['sku'])) ? array('Product Deleted Successfully'):array('Unspecified Error While Deleting Product');
					else 
						$result[$data ['sku']] = array('Sku does not Exists Please Check Action Type');
				break;
				
				default:
					$result[$data ['sku']] = array('Can Not Import SKU Action Type Not Supported');
			}
		}
		return 	$result;
	}
	
	protected function validateAttibute($data)
	{
		$validate = true;
		foreach($data['attributeData'] as $attrDetail)
		{
			$attribute[] = $attrDetail['name'];
			switch (strtolower($attrDetail['name'])) {
				case 'weight':
					if(!is_numeric($attrDetail['value']) || $attrDetail['value'] < 0){
						if($attrDetail['value'] ==""){
							$validate = true;
						}else{
							$data_invalid[] = 'weight';
							$validate = false;
						}
					}
				break;
				case 'price':
					if(!is_numeric($attrDetail['value']) || $attrDetail['value'] < 0){
						
						if($attrDetail['value'] ==""){
							$validate = true;
						}else{
							$data_invalid[] = 'price';
							$validate = false;
						}
						
					} 					
				break;
				case 'msrp':
					if(!is_numeric($attrDetail['value']) || $attrDetail['value'] < 0){
						
						if($attrDetail['value'] ==""){
							$validate = true;
						}else{
							$data_invalid[] = 'msrp';
							$validate = false;
						}
					}
				break;	
			}
		}
		$attribute[] = key($data['extra']);
		$uniqueArray = array_diff ($attribute,$this->allowedAttribute);
		
		if(count($uniqueArray) > 0){
			$validate = false;
		}
		return array('is_validate'=>$validate,'error'=>$uniqueArray, 'data_invalid'=>$data_invalid);
	}
	protected function addProduct($data) 
	{
		$arrMultiInsertQuery = array ();
		$lastId = $this->generateProductId ( $this->catalogTable, $data ['sku'] );
		if ($lastId && $lastId > 0) {
			foreach ( $data ['attributeData'] as $val ) {
				$type = $this->prePopulatedAttribute ['attribute_detail'] [$data ['sku']] [$val ['name']] ['type'];
				$arrMultiInsertQuery [$type] [] = $this->prepareAttributeInsertQuery ( $lastId, $val, $data ['sku'] );
			}
			$this->insertAttributeData ( $lastId, $arrMultiInsertQuery, $data ['sku'] );
			
			$this->insertPredefineAttribute ( $lastId, $data ['sku'] );
			$categoryId = (int) Mage::getStoreConfig('logicbroker_sourcing/product/category');
			if($categoryId)
			$this->assignCategory( $categoryId,$lastId);
			$this->updateProductInventory($lastId);
			
			$isSuccess = true;
			try {
				// Your db manipulations here
				$this->conn->commit ();
			} catch ( Exception $e ) {
				Mage::logException($e);
				$this->conn->rollBack ();
			}
		} else {
			$this->conn->rollBack ();
			$isSuccess = false;
		}
		
		return $isSuccess;
	}
	
	/* Before insert check for SKU exsist @return boolean */
	public function isSkuExsist($sku) 
	{
		$productCollection = Mage::getModel ( 'catalog/product' );
	
		if ($productCollection->getIdBySku ( $sku ))
			return true;
		else
			return false;
	}
	
	public function updateProductInventory($productId)
	{
		
		$tableNameStatus = Mage::getSingleton ( 'core/resource' )->getTableName ( 'cataloginventory/stock_status' );
		$tableNameItem = Mage::getSingleton ( 'core/resource' )->getTableName ( 'cataloginventory/stock_item' );
		$tableNameItemIdx = Mage::getSingleton ( 'core/resource' )->getTableName ( 'cataloginventory/stock_status_indexer_idx' );
		if($productId){
			$insertStatus =  'INSERT INTO '.$tableNameStatus.'(product_id,website_id,stock_id,qty,stock_status) VALUES ('.$productId.',1,1,0,0)';
			$insertItem = 'INSERT INTO '.$tableNameItem.'(product_id,stock_id,qty) VALUES ('.$productId.',1,0)';
			$insertItemIdx =  'INSERT INTO '.$tableNameItemIdx.'(product_id,website_id,stock_id,qty,stock_status) VALUES ('.$productId.',1,1,0,0)';
			$this->conn->query ($insertStatus);
			$this->conn->query ($insertItem);
			$this->conn->query ($insertItemIdx);
		}
	}
	
	/* If SKU exsist than it will simply udpate product data using magento standard way */
	public function updateProduct($sku, $attributes)
	{
		$dataAttribute = array ();
		$sucees = false;
		$productObject = Mage::getModel ( 'catalog/product' );
		$product = $productObject->loadByAttribute ( 'sku', $sku );
		foreach ( $attributes as $name => $value ) {		
			if($value ['name'] == 'manufacturer')
				$dataAttribute[$value ['name']] = $this->checkOptionValueExists($value ['name'], $value ['value']);
			else
				$dataAttribute[$value ['name']] = $value ['value'];
			if($value ['name'] == 'weight' && $value ['value'] == "")
				unset($dataAttribute[$value ['name']]);
			if($value ['name'] == 'price' && $value ['value'] == "")
				unset($dataAttribute[$value ['name']]);
			if($value ['name'] == 'msrp' && $value ['value'] == "")
				unset($dataAttribute[$value ['name']]);			
		}
		$product->addData($dataAttribute);
		try {
			$product->save ();
			$sucees = true;
		} catch ( Exception $e ) {
			Mage::logException($e);
			echo 'Exception occurred ' . $e->getMessage ();
		}
		return $sucees;
	}
	
	protected function checkOptionValueExists($attribute,$value)
	{		
		$optValue = '';	
		if(!array_key_exists($attribute,$this->preDefineAttribute)){
		$attr = Mage::getSingleton('eav/config')->getAttribute($this->getEntityTypeIdByCode(),$attribute);
		if ($attr->getFrontendInput () == 'select')
			$optValue = ($attr->getSource()->getOptionId($value)) ? $attr->getSource()->getOptionId($value) : $this->createAttributeOption($attr->getAttributeId (),$value);
		}
		return $optValue;
	}
	
	public function deleteProduct($sku)
	{
		$sucees = false;
		$productObject = Mage::getModel ( 'catalog/product' );
		$product = $productObject->loadByAttribute ( 'sku', $sku );
		
		try {
			$product->delete ();
			$sucees = true;
		} catch ( Exception $e ) {
			Mage::logException($e);
			echo 'Exception occurred ' . $e->getMessage ();
		}
		return $sucees;
	}
	/* function insert data in catalog_prodcut_entity @return entity_id */
	public function generateProductId($tableName, $data) 
	{
		$entityTypeId = $this->prePopulatedAttribute ['entity_type_id'];
		$attributeSetId = $this->prePopulatedAttribute ['attribute_set_id'];
		$defaultProductArr = array (
	
				'entity_id' => '',
				'entity_type_id' => $entityTypeId,
				'attribute_set_id' => $attributeSetId,
				'type_id' => 'simple',
				'sku' => $data,
				'has_options' => 0,
				'required_options' => 0,
				'created_at' => now (),
				'updated_at' => now ()
		);
		$this->conn->beginTransaction ();
		$this->conn->insert ( $tableName, $defaultProductArr );
		return $this->conn->lastInsertId ();
	}

	
	protected function _processRequestArray($restResquest)
	{
		$arrData = array ();		
		if (! array_key_exists ( 'sku', $restResquest ['productdata'] )) {
			$arrData = $this->processMultiRequest ( $restResquest ['productdata'] );
		} else {
			$arrData = $this->processSingleRequest ( $restResquest ['productdata'] );
		}
		return $arrData;
	}
	
	protected function processMultiRequest($restRequest)
	{
		$arrMultiData = array ();
		foreach ( $restRequest as $keys => $data ) {
			$arrMultiData [$keys] ['sku'] = $data ['sku'];
			foreach ( $data ['attributes'] as $key => $value ) {
				if(!array_key_exists($key,$this->preDefineAttribute) ){					
					switch (strtolower($key)) {
						case 'quantity':
						$arrMultiData [$keys] ['extra'] [trim(strtolower($key))] = trim($value);
						break;
						case 'action':
							$arrMultiData [$keys] ['extra'] [trim(strtolower($key))] = trim($value); 
							
						break;
						default:
							$arrMultiData [$keys] ['attributeData'] [] = array (
									'name' => trim(strtolower($key)),
									'value' => trim($value)
							);
						break;
					}				
				}
			}
		}
		return $arrMultiData;
	}
	
	protected function processSingleRequest($restRequest) {
		$arrSingleData = array ();
		$test = array ();
		foreach ( $restRequest as $key => $data ) {			
			if (is_array ( $data )) {
				foreach ( $data as $name => $value ) {
					if(!array_key_exists($name,$this->preDefineAttribute) ){						
						switch (strtolower($name)) {
							case 'quantity':
								$arrSingleData ['extra'] [] = array (
							'name' => trim(strtolower($name)),
							'value' => trim($value) 
							);
							break;
							case 'action':
								$arrSingleData ['extra'] [] = array (
							'name' => trim(strtolower($name)),
							'value' => trim($value) 
							);
							break;
							default:
								$arrSingleData ['attributeData'] [] = array (
							'name' => trim(strtolower($name)),
							'value' => trim($value) 
							);
							break;
						}						
					}
				}
			} else {
				$arrSingleData [$key] = $data;
			}
		}
		return $test [] = array (
				$arrSingleData 
		);
	}
}
