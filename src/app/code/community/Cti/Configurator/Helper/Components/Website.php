<?php
class Cti_Configurator_Helper_Components_Website extends Cti_Configurator_Helper_Components_Abstract {

    protected $_componentName = 'website';

    public function __construct() {

        $this->_filePath1 = Mage::getBaseDir().DS.'app'.DS.'etc'.DS.'components'.DS.'websites.yaml';

    }

    protected function _processFile($globalFile,$localFile = null) {

        if (!file_exists($globalFile)) {
            $this->log("No website configuration \nCreate the file " . $globalFile);
            return;
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

        if (isset($data['websites'])) {

            $websiteCount = 1;

            // Process websites
            foreach ($data['websites'] as $code=>$config) {
                $website = $this->__addUpdateWebsite($config,$code,$websiteCount);

                foreach($config['store_groups'] as $sgConfig) {

                    if (isset($sgConfig['root_category'])) {
                        $sgConfig['root_category_id'] = $this->_getOrCreateCategoryByName($sgConfig['root_category'])->getId();
                    }

                    $storeGroup = $this->__addUpdateStoreGroup($sgConfig,$website);

                    $storeCount = 1;
                    foreach ($sgConfig['stores'] as $code=>$sConfig) {

                        // Increment Sort Order
                        $sConfig['sort_order'] = $storeCount;
                        $sConfig['code'] = $code;
                        $this->__addUpdateStore($sConfig,$storeGroup);
                        $storeCount++;
                    }
                }

                $websiteCount++;
            }
        }

    }

    protected function _getOrCreateCategoryByName($name) {
        $categories = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToFilter('name',$name)
            ->addFieldToFilter('level',1);

        if (!$categories->count()) {

            $category = Mage::getModel('catalog/category')
                ->setStoreId(0)
                ->setName($name)
                ->setPath(1)
                ->setDisplayMode("PRODUCTS")
                ->setIsActive(1)
                ->save();
        } else {

            $category = $categories->getFirstItem();
        }

        return $category;
    }


    /**
     * @param array $config
     * @param string $code
     * @param int $sortOrder
     * @return Mage_Core_Model_Website
     */
    private function __addUpdateWebsite($config,$code,$sortOrder){

        // See if the website exists otherwise create the website
        $website = Mage::getModel('core/website')->load($code,'code');
        if ($website->getId()) {
            if ($website->getName() != $config['name'] || $website->getSortOrder() != $sortOrder) {
                $website->setName($config['name'])
                    ->setSortOrder($sortOrder)
                    ->save();
                $this->log("Updated website ".$website->getCode());
            }
        } else {
            $website = Mage::getModel('core/website');
            $website->setCode($code)
                ->setName($config['name'])
                ->setSortOrder($sortOrder)
                ->save();

            $this->log("Created website ".$website->getCode()."  with name ".$website->getName());
        }
        return $website;
    }

    /**
     * @param array $sgConfig
     * @param Mage_Core_Model_Website $website
     * @return Mage_Core_Model_Store_Group
     */
    private function __addUpdateStoreGroup($sgConfig,$website) {

        // See if the store group exists otherwise create a store group
        $storeGroup = Mage::getModel('core/store_group')->load($sgConfig['name'],'name');
        if ($storeGroup->getId()) {

            if ($storeGroup->getWebsiteId() != $website->getId()
                || $storeGroup->getRootCategoryId() != $sgConfig['root_category_id']) {

                $storeGroup->setWebsiteId($website->getId())
                    ->setRootCategoryId($sgConfig['root_category_id'])
                    ->save();
                $this->log("Updated store group " . $storeGroup->getName());
            }
        } else {
            $storeGroup = Mage::getModel('core/store_group');
            $storeGroup->setWebsiteId($website->getId())
                ->setName($sgConfig['name'])
                ->setRootCategoryId($sgConfig['root_category_id'])
                ->save();

            $this->log("Created store group ".$storeGroup->getName());
        }
        return $storeGroup;
    }

    /**
     * @param   array $sConfig
     * @param   Mage_Core_Model_Store_Group $storeGroup
     * @return  Mage_Core_Model_Store
     */
    private function __addUpdateStore($sConfig,$storeGroup) {

        // See if the store exists otherwise create a store
        $store = Mage::getModel('core/store')->load($sConfig['code'],'code');
        if ($store->getId()) {

            if ($store->getWebsiteId() != $storeGroup->getWebsiteId()
            || $store->getGroupId() != $storeGroup->getId()
            || $store->getName() != $sConfig['name']
            || $store->getIsActive() != (isset($sConfig['active'])? $sConfig['active']:1)
            || $store->getSortOrder() != $sConfig['sort_order']) {

                $store->setWebsiteId($storeGroup->getWebsiteId())
                    ->setGroupId($storeGroup->getId())
                    ->setName($sConfig['name'])
                    ->setIsActive(isset($sConfig['active']) ? $sConfig['active'] : 1)
                    ->setSortOrder($sConfig['sort_order'])
                    ->save();

                $this->log("Updated store " . $store->getCode());
            }
        } else {
            $store = Mage::getModel('core/store');
            $store->setCode($sConfig['code'])
                ->setWebsiteId($storeGroup->getWebsiteId())
                ->setGroupId($storeGroup->getId())
                ->setName($sConfig['name'])
                ->setIsActive(isset($sConfig['active'])? $sConfig['active']:1)
                ->setSortOrder($sConfig['sort_order'])
                ->save();

            $this->log("Created store ".$store->getCode()."  with name ".$store->getName());
        }
        return $store;
    }
}