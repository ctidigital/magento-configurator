<?php
class Cti_Configurator_Helper_Components_Categories extends Cti_Configurator_Helper_Components_Abstract
{

    protected $_componentName = 'categories';

    public function __construct()
    {

        $this->_filePath1 = Mage::getBaseDir() . DS . 'app' . DS . 'etc' . DS . 'components' . DS . 'categories.yaml';

    }

    protected function _processFile($globalFile,$localFile = null) {
        if (!file_exists($globalFile)) {
            throw new Mage_Core_Exception("Cannot find global configuration YAML file.");
        }

        // Decode the YAML File
        $globalClass = new Zend_Config_Yaml($globalFile,
            NULL,
            array('ignore_constants' => true));
        $globalArray = $globalClass->toArray();

        return $globalArray;
    }

    protected function _processComponent($data) {

        if (isset($data['categories'])) {

            foreach ($data['categories'] as $i=>$catData) {

                $this->_addCategory($catData);
            }
        }
    }

    /**
     * Function to add category
     *
     * @param $data
     * @param Mage_Catalog_Model_Category $parent
     * @throws Mage_Core_Exception
     */
    protected function _addCategory($data,Mage_Catalog_Model_Category $parent = null) {

        // If parent category is null then just set it as the store groups root category
        if ($parent == null && isset($data['store_group'])) {
            $storeGroup = Mage::getResourceModel('core/store_group_collection')
                ->addFieldToFilter('name',$data['store_group'])
                ->getFirstItem();
            if ($storeGroup->getId()) {
                $parentId = $storeGroup->getRootCategoryId();
            } else {
                throw new Mage_Core_Exception('No store group exists');
            }
        } elseif ($parent != null) {
            $parentId = $parent->getId();
        } else {
            throw new Mage_Core_Exception('No root category id assignable.');
        }

        /* @var $category Mage_Catalog_Model_Category */

        // Check if the category with that name exists and has the same parent
        $category = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToFilter('name',$data['name'])
            ->addFieldToFilter('parent_id',$parentId)
            ->addAttributeToSelect('*')
            ->getFirstItem();

        $canSave = false;

        // If it does exist, update the description
        if (!$category->getId()) {

            $category = Mage::getModel('catalog/category')
                ->setName($data['name'])
                ->setParentId($parentId)
                ->setIsActive(1)
                ->setDisplayMode("PRODUCTS")
                ->setPath(Mage::getModel('catalog/category')->load($parentId)->getPath());

            $canSave = true;
        }

        foreach ($data as $key=>$value) {
            if ($key == "categories") {
                continue;
            }
            if ($key == "products") {
                continue;
            }
            if ($key == "store_group") {
                continue;
            }
            if ($category->getData($key) == $value) {
                continue;
            }
            $category->setData($key,$value);
            $canSave = true;
        }

        // Add products
        if (isset($data['products'])) {
            $products = array();
            $i = 10;
            foreach ($data['products'] as $sku) {
                $id = Mage::getModel('catalog/product')->getIdBySku($sku);
                $products[$id] = $i;
                $i = $i + 10;
                unset($id);
            }
            unset($i);
            $category->setPostedProducts($products);
        }

        if ($canSave) {
            $category->save();
            $this->log($this->__('Category %s saved', $category->getName()),($category->getLevel()-2));
        }

        // Recursive category creation
        if (isset($data['categories'])) {
            foreach ($data['categories'] as $categoryConfig) {
                $this->_addCategory($categoryConfig,$category);
            }
        }

    }
}