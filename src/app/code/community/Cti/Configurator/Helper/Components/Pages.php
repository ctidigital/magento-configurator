<?php
class Cti_Configurator_Helper_Components_Pages extends Cti_Configurator_Helper_Components_Abstract
{

    public function __construct()
    {
        $this->_componentName = 'pages';
        $this->_filePath1 = Mage::getBaseDir() . DS . 'app' . DS . 'etc' . DS . 'components' . DS . 'pages.yaml';
    }

    protected function _processFile($globalFile, $localFile = null)
    {

        if (!file_exists($globalFile)) {
            throw new Mage_Core_Exception("Cannot find pages configuration YAML file.");
        }


        // Decode the YAML File
        $globalClass = new Zend_Config_Yaml($globalFile,
            NULL,
            array('ignore_constants' => true));

        $globalArray = $globalClass->toArray();


        return $globalArray;
    }

    protected function _processComponent($data)
    {
        // Check if there is a pages node in the yaml
        if (isset($data['pages'])) {

            // Loop through its children
            foreach ($data['pages'] as $identifier => $pageData) {

                try {

                    // Ensure identifier is set
                    if (is_numeric($identifier)) {
                        throw new Exception("Please refer to sample content for see how the page identifier is set.");
                    }

                    // Try load existing page
                    if (!isset($pageData['contents'])) {
                        throw new Exception("Could not find the page contents");
                    }

                    // If the all attribute is set then this will be a global page.
                    if (isset($pageData['contents']['all'])) {
                        $page = Mage::getResourceModel('cms/page_collection')
                            ->addStoreFilter(0)
                            ->addFieldToFilter('identifier',$identifier)
                            ->getFirstItem();

                        $storePageData = array_merge_recursive($pageData,$pageData['contents']['all']);

                        $this->_createUpdatePage($identifier,$page,$storePageData);

                    } elseif (is_array($pageData['contents'])) {

                        // Loop through the different versions of the content
                        foreach ($pageData['contents'] as $i=>$content) {

                            // Check if we have an index
                            if (!is_numeric($i)) {
                                throw new Exception("A textual index for the content should not be defined. Use 'all' or none");
                            }

                            // Check if we have store view(s) set
                            if (!isset($content['stores']) || !is_array($content['stores'])) {
                                throw new Exception("Store views are not correctly set on $identifier");
                            }

                            $pages = Mage::getResourceModel('cms/page_collection')
                                ->addFieldToFilter('identifier',$identifier);

                            if ($pages->count()) {
                                $pagesArray = array_values($pages->getItems());
                                if (isset($pagesArray[$i])) {
                                    $page = $pagesArray[$i];
                                } else {
                                    $page = Mage::getModel('cms/page');
                                }
                            } else {
                                $page = Mage::getModel('cms/page');
                            }

                            $storePageData = array_merge_recursive($pageData,$content);

                            $this->_createUpdatePage($identifier,$page,$storePageData);

                            unset($storeIds);
                        }
                    }

                } catch (Exception $e) {

                    $this->log($e->getMessage());
                }
            }
        }
    }

    /**
     * @param Mage_Cms_Model_Page $page
     * @param $data
     * @throws Exception
     */
    private function _createUpdatePage($identifier,Mage_Cms_Model_Page $page,$data) {

        $canSave = false;

        // Load page model
        if (!$page->getId()) {
            $page = Mage::getModel('cms/page');
            $page->setIdentifier($identifier);
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
            $oldStoreIds = Mage::getModel('cms/page')->load($page->getId())->getStoreId();

            sort($storeIds);
            if ($oldStoreIds != $storeIds) {
                $canSave = true;
                $page->setStores($storeIds);
            }

            unset($oldStoreIds);
            unset($storeIds);
            unset($data['stores']);
        } else {

            $page->setStores(array(0));
        }

        // Loop through page attributes
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
            if ($page->getData($key) == $value) {
                continue;
            }

            $canSave = true;
            $page->setData($key,$value);
            $this->log("Setting page attribute $key to $value for $identifier");

        }

        if ($canSave) {
            $page->save();
            $this->log("Saved page $identifier");
        }
    }

}