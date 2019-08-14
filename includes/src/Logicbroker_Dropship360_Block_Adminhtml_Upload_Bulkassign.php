<?php
/**
 * Logicbroker
 *
 * @category    Community
 * @package     Logicbroker_Dropship360
 */

class Logicbroker_Dropship360_Block_Adminhtml_Upload_Bulkassign extends Mage_Adminhtml_Block_Abstract
{
    /**
     * Flag for batch model
     * @var boolean
     */
    protected $_batchModelPrepared = false;
    /**
     * Batch model instance
     * @var Mage_Dataflow_Model_Batch
     */
    protected $_batchModel = null;
    /**
     * Preparing batch model (initialization)
     * @return Mage_Adminhtml_Block_System_Convert_Profile_Run
     */
    
    protected function _construct()
    {
    	parent::_construct();
    	Mage::getSingleton('adminhtml/session')->setTerminateExecution(false);
    	
  
    }
    protected function _prepareBatchModel()
    {
        if ($this->_batchModelPrepared) {
            return $this;
        }
        $this->setShowFinished(true);
        $batchModel = Mage::getModel('logicbroker/uploadvendor')->prepareBulkassignmentCollection($this->getBulkVendorCode());
        if (count($batchModel) > 0) {
            
                $numberOfRecords = 100;
                $this->setShowFinished(false);
                $importIds = $batchModel;
                $this->setBatchItemsCount(count($batchModel));
                $this->setBatchConfig(
                    array(
                        'styles' => array(
                            'error' => array(
                                'icon' => Mage::getDesign()->getSkinUrl('images/error_msg_icon.gif'),
                                'bg'   => '#FDD'
                            ),
                            'message' => array(
                                'icon' => Mage::getDesign()->getSkinUrl('images/fam_bullet_success.gif'),
                                'bg'   => '#DDF'
                            ),
                            'loader'  => Mage::getDesign()->getSkinUrl('images/ajax-loader.gif')
                        ),
                        'template' => '<li style="#{style}" id="#{id}">'
                                    . '<img id="#{id}_img" src="#{image}" class="v-middle" style="margin-right:5px"/>'
                                    . '<span id="#{id}_status" class="text">#{text}</span>'
                                    . '</li>',
                        'text'     => $this->__('Processed <strong>%s%% %s/%d</strong> records', '#{percent}', '#{updated}', $this->getBatchItemsCount()),
                        'successText'  => $this->__('Imported <strong>%s</strong> records', '#{updated}')
                    )
                );
                $jsonIds = array_chunk($importIds, $numberOfRecords);
                $importData = array();
                foreach ($jsonIds as $part => $ids) {
                    $importData[] = array(
                    	'vendor_code' => $this->getBulkVendorCode(),	
                        'rows[]'     => $ids
                    );
                }
                $this->setImportData($importData);
            
        }
        $this->_batchModelPrepared = true;
        return count($batchModel);
    }
    /**
     * Return a batch model instance
     * @return Mage_Dataflow_Model_Batch
     */
    protected function _getBatchModel()
    {
        return $this->_batchModel;
    }
    /**
     * Return a batch model config JSON
     * @return string
     */
    public function getBatchConfigJson()
    {
        return Mage::helper('core')->jsonEncode(
            $this->getBatchConfig()
        );
    }
    /**
     * Encoding to JSON
     * @param string $source
     * @return string JSON
     */
    public function jsonEncode($source)
    {
        return Mage::helper('core')->jsonEncode($source);
    }
    /**
     * Get a profile
     * @return object
     */
    public function getBulkVendorCode()
    {
        return Mage::registry('bulk_vendor_Code');
    }
    /**
     * Generating form key
     * @return string
     */
    public function getFormKey()
    {
        return Mage::getSingleton('core/session')->getFormKey();
    }
    /**
     * Return batch model and initialize it if need
     * @return Mage_Dataflow_Model_Batch
     */
    public function getBatchModel()
    {
        return $this->_prepareBatchModel();
    }
    /**
     * Generating exceptions data
     * @return array
     */
    public function getExceptions()
    {
        if (!is_null(parent::getExceptions()))
            return parent::getExceptions();
        $exceptions = array();
        $this->getProfile()->run();
        foreach ($this->getProfile()->getExceptions() as $e) {
                switch ($e->getLevel()) {
                    case Varien_Convert_Exception::FATAL:
                        $img = 'error_msg_icon.gif';
                        $liStyle = 'background-color:#FBB; ';
                        break;
                    case Varien_Convert_Exception::ERROR:
                        $img = 'error_msg_icon.gif';
                        $liStyle = 'background-color:#FDD; ';
                        break;
                    case Varien_Convert_Exception::WARNING:
                        $img = 'fam_bullet_error.gif';
                        $liStyle = 'background-color:#FFD; ';
                        break;
                    case Varien_Convert_Exception::NOTICE:
                        $img = 'fam_bullet_success.gif';
                        $liStyle = 'background-color:#DDF; ';
                        break;
                }
                $exceptions[] = array(
                    "style"     => $liStyle,
                    "src"       => Mage::getDesign()->getSkinUrl('images/'.$img),
                    "message"   => $e->getMessage(),
                    "position" => $e->getPosition()
                );
        }
        parent::setExceptions($exceptions);
        return $exceptions;
    }
    
   
    
    protected function isManualUploadRunning(){
    	 
    	$result = false;
    	if(Mage::helper('logicbroker')->isProcessRunning('manual_upload')){
    		$result = true;
    	}
    	return $result;
    }
}
