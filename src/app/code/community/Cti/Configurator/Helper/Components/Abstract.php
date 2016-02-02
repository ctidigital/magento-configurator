<?php
abstract class Cti_Configurator_Helper_Components_Abstract extends Mage_Core_Helper_Abstract {

    protected $_componentName;
    protected $_data;
    protected $_filePath1;
    protected $_filePath2;
    protected $_cliLog;

    public function __construct() {
        $this->_cliLog = false;
    }

    public function setFilePath1($filePath) {
        $this->_filePath1 = $filePath;
        return $this;
    }

    public function setFilePath2($filePath) {
        $this->_filePath2 = $filePath;
        return $this;
    }

    public function getFilePath1() {
        return $this->_filePath1;
    }

    public function getFilePath2() {
        return $this->_filePath2;
    }

    public function getComponentName() {
        return $this->_componentName;
    }

    public function process() {

        try {

            if (is_null($this->_componentName)) {
                throw new Exception("Component name is not defined");
            }

            Mage::dispatchEvent('configurator_process_before',array('object'=>$this));
            Mage::dispatchEvent($this->_componentName.'_configurator_process_before',array('object'=>$this));

            $this->_data = $this->_processFile($this->_filePath1,$this->_filePath2);

            $this->_processComponent($this->_data);

            Mage::dispatchEvent('configurator_process_after',array('object'=>$this));
            Mage::dispatchEvent($this->_componentName.'_configurator_process_after',array('object'=>$this));

        } catch (Exception $e) {

            Mage::logException($e);
        }

    }

    public function enableCliLog() {
        $this->_cliLog = true;
    }

    protected function log($msg,$nest = 0,$logLevel = 0) {
        if ($this->_cliLog) {
            for($i = 0; $i < $nest; $i++) {
                echo " | ";
            }

            echo $msg .PHP_EOL;
        }
    }

    abstract protected function _processFile($file1,$file2 = null);

    abstract protected function _processComponent($data);


}