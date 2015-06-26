<?php
class Cti_Configurator_Helper_Components_StaticBlocks extends Cti_Configurator_Helper_Components_Abstract {

    public function __construct()
    {
        $this->_componentName = 'staticblocks';
        $this->_filePath1 = Mage::getBaseDir() . DS . 'app' . DS . 'etc' . DS . 'components' . DS . 'static-blocks.yaml';
    }

    protected function _processFile($globalFile, $localFile = null)
    {

        if (!file_exists($globalFile)) {
            throw new Mage_Core_Exception("Cannot find static blocks configuration YAML file.");
        }

        // Decode the YAML File
        $globalClass = new Zend_Config_Yaml($globalFile,
            NULL,
            array('ignore_constants' => true));

        $globalArray = $globalClass->toArray();


        return $globalArray;
    }

    protected function _processComponent($data) {

        // Check if there is a block node in the yaml
        if (isset($data['blocks'])) {

            // Loop through its children
            foreach ($data['blocks'] as $identifier => $blockData) {

                try {

                    // Ensure identifier is set
                    if (is_numeric($identifier)) {
                        throw new Exception("Please refer to sample content for see how the block identifier is set.");
                    }

                    // Try load existing block
                    if (!isset($blockData['contents'])) {
                        throw new Exception("Could not find the block contents");
                    }

                    // If the all attribute is set then this will be a global block.
                    if (isset($blockData['contents']['all'])) {
                        $block = Mage::getResourceModel('cms/block_collection')
                            ->addStoreFilter(0)
                            ->addFieldToFilter('identifier',$identifier)
                            ->getFirstItem();

                        $storeBlockData = array_merge_recursive($blockData,$blockData['contents']['all']);

                        $this->_processBlock($identifier,$block,$storeBlockData);

                    } elseif (is_array($blockData['contents'])) {

                        // Loop through the different versions of the content
                        foreach ($blockData['contents'] as $i=>$content) {

                            // Check if we have an index
                            if (!is_numeric($i)) {
                                throw new Exception("A textual index for the content should not be defined. Use 'all' or none");
                            }

                            // Check if we have store view(s) set
                            if (!isset($content['stores']) || !is_array($content['stores'])) {
                                throw new Exception("Store views are not correctly set on $identifier");
                            }

                            $blocks = Mage::getResourceModel('cms/block_collection')
                                ->addFieldToFilter('identifier',$identifier);

                            if ($blocks->count()) {
                                $blocksArray = array_values($blocks->getItems());
                                if (isset($blocksArray[$i])) {
                                    $block = $blocksArray[$i];
                                } else {
                                    $block = Mage::getModel('cms/block');
                                }
                            } else {
                                $block = Mage::getModel('cms/block');
                            }

                            $storeBlockData = array_merge_recursive($blockData,$content);

                            $this->_processBlock($identifier,$block,$storeBlockData);

                            unset($storeIds);
                        }
                    }

                } catch (Exception $e) {

                    $this->log($e->getMessage());
                }
            }
        }
    }

    private function _processBlock($identifier,Mage_Cms_Model_Block $block,$data) {
        $canSave = false;

        // Load block model
        if (!$block->getId()) {
            $block = Mage::getModel('cms/block');
            $block->setIdentifier($identifier);
        }

        unset($data['contents']);

        if (isset($data['stores'])) {

            // Loop through the store view names to get its corresponding ID
            $storeIds = array();
            foreach ($data['stores'] as $storeViewName) {
                $store = Mage::getModel('core/store')->load($storeViewName,'code');
                if (!$store->getId()) {
                    throw new Exception("Store View Name: $storeViewName does not exist for $identifier");
                }
                $storeIds[] = $store->getId();
                unset($store);
            }
            unset($storeViewName);

            // @todo check what stores it is associated to already
            $oldStoreIds = Mage::getModel('cms/block')->load($block->getId())->getStoreId();

            sort($storeIds);
            if ($oldStoreIds != $storeIds) {
                $canSave = true;
                $block->setStores($storeIds);
            }

            unset($oldStoreIds);
            unset($storeIds);
            unset($data['stores']);
        } else {

            $block->setStores(array(0));
        }

        // Loop through block attributes
        foreach ($data as $key=>$value) {

            // content file attribute would require to read it from a file
            if ($key == "content_file") {

                // If a value/path is set then get its contents
                if ($value != "") {

                    // locate file path
                    $filePath = Mage::getBaseDir() . DS . 'app' . DS . 'etc' . DS . 'components' . DS . 'html' . DS . $value;

                    // Check if the file exists
                    if (file_exists($filePath)) {

                        // Get the contents of the file and save it as the value
                        $value = file_get_contents($filePath);

                        unset($filePath);

                        $key = 'content';
                    } else {
                        throw new Exception("No file found in $filePath");
                    }

                } else {
                    continue;
                }
            }

            // If the value is already equal to the value in the database, skip it
            if ($block->getData($key) == $value) {
                continue;
            }

            $canSave = true;
            $block->setData($key,$value);
            $this->log("Setting block attribute $key to $value for $identifier");

        }

        if ($canSave) {
            $block->save();
            $this->log("Saved block $identifier");
        }
    }
}