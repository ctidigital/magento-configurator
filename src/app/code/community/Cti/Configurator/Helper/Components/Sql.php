<?php
class Cti_Configurator_Helper_Components_Sql extends Cti_Configurator_Helper_Components_Abstract
{
    protected $_componentName = 'sql';
    protected $_coreConfigModel;

    public function __construct()
    {

        $this->_filePath1 = Mage::getBaseDir() . DS . 'app' . DS . 'etc' . DS . 'components' . DS . 'sql.yaml';
        $this->_filePath2 = Mage::getBaseDir() . DS . 'app' . DS . 'etc' . DS . 'local_components' . DS . 'sql.yaml'; // Could be some symlinked file environment specific

        $this->_coreConfigModel = Mage::getModel('core/config');

    }

    protected function _processFile($globalFile, $localFile = null)
    {

        if (!file_exists($globalFile)) {
            $this->log("No sql component found in: " . $globalFile);
            $this->log("Skipping");
            throw new Mage_Core_Exception("Cannot find global sql YAML file.");
        }

        // Decode the YAML File
        $globalClass = new Zend_Config_Yaml($globalFile,
            NULL,
            array('ignore_constants' => true));
        $globalArray = $globalClass->toArray();

        $localArray = array();
        if (file_exists($localFile)) {
            // Decode the YAML File
            $localClass = new Zend_Config_Yaml($localFile,
                NULL,
                array('ignore_constants' => true));
            $localArray = $localClass->toArray();
        }

        $data = array_merge_recursive($globalArray, $localArray);

        return $data;
    }

    protected function _processComponent($data) {
        if (!isset($data['sql'])) {
            return;
        }

        foreach ($data['sql'] as $file) {
            try {
                $path = Mage::getBaseDir().$file;
                if (!file_exists($path)) {
                    throw new Exception($file.' does not exist for SQL execution');
                }

                $resource = Mage::getSingleton('core/resource');
                $writeConnection = $resource->getConnection('core_write');
                $query = file_get_contents($path);
                $writeConnection->query($query);

                $this->log($this->__("Executed sql script %s",$file));

            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
    }
}