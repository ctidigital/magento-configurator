<?php
class Cti_Configurator_Helper_Components_Config extends Cti_Configurator_Helper_Components_Abstract
{
    protected $_componentName = 'config';
    protected $_coreConfigModel;

    public function __construct() {

        $this->_filePath1 = Mage::getBaseDir().DS.'app'.DS.'etc'.DS.'components'.DS.'config.yaml';
        $this->_filePath2 = null; // Could be some symlinked file environment specific

        $this->_coreConfigModel = Mage::getModel('core/config');

    }

    protected function _processFile($globalFile,$localFile = null) {

        if (!file_exists($globalFile)) {
            $this->log("No configuration component found in: " . $globalFile);
            $this->log("Skipping");
            throw new Mage_Core_Exception("Cannot find global configuration YAML file.");
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

        $data = array_merge_recursive($globalArray,$localArray);

        return $data;
    }

    protected function _processComponent($data) {

        // Loop through global configuration settings first
        if (isset($data['global'])) {
            $this->_saveDefaultConfigItems($data['global']);
        }

        // Initialise inheritable config for website and store level config
        $inheritables = null;
        if (isset($data['grouped'])) {
            $inheritables = $data['grouped'];
        }

        if (isset($data['websites'])) {
            foreach ($data['websites'] as $websiteCode=>$configData) {
                $websiteConfig = $this->_mergeInheritables($configData,$inheritables);
                $website = Mage::getModel('core/website')->load($websiteCode,'code');
                $this->_saveWebsiteConfigItems($websiteConfig,$website);
            }
        }

        if (isset($data['stores'])) {
            foreach ($data['stores'] as $storeCode=>$configData) {
                $storeConfig = $this->_mergeInheritables($configData,$inheritables);
                $store = Mage::getModel('core/store')->load($storeCode,'code');
                $this->_saveStoreConfigItems($storeConfig,$store);
            }
        }

    }

    private function _saveDefaultConfigItems($config) {
        if (isset($config['core_config'])) {
            foreach ($config['core_config'] as $config) {

                // See if the value should be encrypted
                if (isset($config['encrypted']) && $config['encrypted'] == 1) {
                    $config['value'] = Mage::helper('core')->encrypt($config['value']);
                }

                // Save config
                $this->_checkAndSaveConfig(
                    $config['path'],
                    $config['value']
                );

            }
        }
    }

    private function _saveWebsiteConfigItems(array $configs,Mage_Core_Model_Website $website) {
        try {
            if (!$website->getId()) {
                throw new Exception($this->__('Website does not exist'));
            }
            if (!empty($configs)) {
                foreach ($configs as $config) {
                    if (isset($config['encrypted']) && $config['encrypted'] == 1) {
                        $config['value'] = Mage::helper('core')->encrypt($config['value']);
                    }

                    // Save config
                    $this->_checkAndSaveConfig(
                        $config['path'],
                        $config['value'],
                        'websites',
                        $website->getId(),
                        $website->getCode()
                    );

                }
            }
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }
    }

    private function _saveStoreConfigItems(array $configs,Mage_Core_Model_Store $store) {
        try {
            if (!$store->getId()) {
                throw new Exception($this->__('Store does not exist'));
            }
            if (!empty($configs)) {
                foreach ($configs as $config) {
                    if (isset($config['encrypted']) && $config['encrypted'] == 1) {
                        $config['value'] = Mage::helper('core')->encrypt($config['value']);
                    }

                    // Save config
                    $this->_checkAndSaveConfig(
                        $config['path'],
                        $config['value'],
                        'stores',
                        $store->getId(),
                        $store->getCode()
                    );

                }
            }
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }
    }

    private function _mergeInheritables($config,$inheritables) {
        $data = array();
        if (isset($config['core_config'])) {
            $data = $config['core_config'];
        }
        if (isset($config['inherit'])){
            foreach ($config['inherit'] as $key) {
                $data = array_merge_recursive($data,$inheritables[$key]['core_config']);
            }
        }
        return $data;
    }

    private function _checkAndSaveConfig($path,$value,$scope = 'default',$scopeId = 0,$code = null) {


        switch ($scope) {
            case 'websites':
                $valueCheck = (string) Mage::app()->getWebsite($scopeId)->getConfig($path);
                break;
            case 'stores':
                $valueCheck = (string) Mage::getStoreConfig($path,$scopeId);
                break;
            default:
                $valueCheck = (string) Mage::app()->getConfig()->getNode($path,$scope);
                break;
        }


        if ($value != $valueCheck) {
            $this->_coreConfigModel->saveConfig($path,$value,$scope,$scopeId);

            switch ($scope) {
                case 'websites':
                    $this->log($this->__('Saved path for website: %s - %s to %s',$code,$path,$value));
                    break;
                case 'stores':
                    $this->log($this->__('Saved path for store: %s - %s to %s',$code,$path,$value));
                    break;
                default:
                    $this->log($this->__('Saved default path for %s to %s',$path,$value));
                    break;
            }

        }


    }
}