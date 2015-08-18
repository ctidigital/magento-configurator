<?php
class Cti_Configurator_Helper_Components_AttributeSets extends Cti_Configurator_Helper_Components_Abstract {

    public function __construct() {
        $this->_componentName = 'attribute_sets';
        $this->_filePath1 = Mage::getBaseDir().DS.'app'.DS.'etc'.DS.'components'.DS.'attribute-sets.yaml';
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

        if (isset($data['attribute_sets'])) {

            foreach ($data['attribute_sets'] as $set) {

                $attributeSet = $this->_getAttributeSet($set);

                if (!$attributeSet) {
                    $attributeSet = $this->_createAttributeSet($set);

                    $this->log($this->__('Created new attribute set "%s"',$attributeSet->getAttributeSetName()));

                    // Set the skeleton attribute set to Default if none is specified
                    $inheritAttributeSetName = 'Default';

                    if (isset($set['inherit'])) {
                        $inheritAttributeSetName = $set['inherit'];
                    }

                    $this->_inheritAttributeSet($attributeSet,$inheritAttributeSetName);

                    $this->log($this->__('Inherited new attributes from "%s"',$inheritAttributeSetName));
                }

                foreach ($set['groups'] as $group) {

                    if (!isset($group['name'])) {
                        throw new Exception($this->__('No group name set for attribute "%s"',$set['name']));
                    }

                    // Get the attribute Group ID
                    $attributeGroupId = $this->_getAttributeGroupId($attributeSet,$group['name']);

                    if (!$attributeGroupId) {
                        // Functionality to create new attribute groups
                        $attributeGroupId = $this->_createAttributeGroup($attributeSet, $group['name'])->getId();
                        $this->log($this->__('Attribute group named "%s" was created for attribute set "%s"',
                            $group['name'],
                            $attributeSet->getAttributeSetName()
                        ));
                    }

                    // @todo Functionality to create new attribute groups

                    foreach ($group['attributes'] as $attributeCode) {

                        $attribute = $this->_getAttribute($attributeCode);

                        if (!$attribute) {
                            $this->log($this->__('Attribute "%s" does not exist',$attributeCode));
                            continue;
                        }

                        // Add the attribute to the attribute set
                        $this->_assignAttributeToAttributeSet($attribute,$attributeSet,$attributeGroupId);
                        $this->log($this->__('Added "%s" attribute to "%s" attribute within "%s" group',
                            $attribute->getName(),
                            $attributeSet->getAttributeSetName(),
                            $group['name']
                        ));
                    }
                }

            }
        }
    }

    /**
     * Gets a particular attribute set otherwise
     * returns false if it doesn't exist.
     *
     * @param $attributeConfig
     * @return Mage_Eav_Model_Entity_Attribute_Set
     */
    private function _getAttributeSet($attributeConfig) {
        $entityTypeId = Mage::getModel('catalog/product')
            ->getResource()
            ->getEntityType()
            ->getId();

        $attributeSet =  Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->addFieldToFilter('entity_type_id',$entityTypeId)
            ->addFieldToFilter('attribute_set_name',$attributeConfig['name'])
            ->getFirstItem();

        if ($attributeSet->getId()) {
            return $attributeSet;
        } else {
            return false;
        }
    }

    /**
     * Creates an attribute set
     *
     * @param array $attributeConfig
     * @return Mage_Eav_Model_Entity_Attribute_Set
     * @see http://magento.stackexchange.com/questions/4905/programmatically-create-new-attribute-set-while-inheriting-from-another
     */
    private function _createAttributeSet($attributeConfig) {

        $entityTypeId = $this->_getEntityTypeId();

        $attributeSet = Mage::getModel('eav/entity_attribute_set')
            ->setEntityTypeId($entityTypeId)
            ->setAttributeSetName($attributeConfig['name']);

        $attributeSet->validate();
        $attributeSet->save();
        return $attributeSet;
    }

    /**
     * Creates an attribute group
     *
     * @param $attributeSet
     * @param $groupName
     * @return Mage_Eav_Model_Entity_Attribute_Group
     */
    private function _createAttributeGroup($attributeSet, $groupName) {

        $attributeGroup = Mage::getModel('eav/entity_attribute_group')
            ->setAttributeSetId($attributeSet->getId())
            ->setAttributeGroupName($groupName);

        $attributeGroup->save();
        return $attributeGroup;
    }

    /**
     * Inherits all the attributes from the particular attribute set.
     *
     * @param Mage_Eav_Model_Entity_Attribute_Set $attributeSet
     * @param $inheritFromSetName
     */
    private function _inheritAttributeSet(Mage_Eav_Model_Entity_Attribute_Set $attributeSet,$inheritFromSetName) {

        $entityTypeId = $this->_getEntityTypeId();

        $inheritFromSetId = Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->addFieldToFilter('entity_type_id',$entityTypeId)
            ->addFieldToFilter('attribute_set_name',$inheritFromSetName)
            ->getFirstItem()
            ->getId();

        // Include some kind of failure check here

        $attributeSet->initFromSkeleton($inheritFromSetId)->save();
    }

    /**
     * Gets a particular group ID within the attribute set
     * To be used with setting an attribute to a particular group
     *
     * @param Mage_Eav_Model_Entity_Attribute_Set $attributeSet
     * @param $attributeGroupName
     * @return mixed
     */
    private function _getAttributeGroupId(Mage_Eav_Model_Entity_Attribute_Set $attributeSet,$attributeGroupName) {
        return Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->addFieldToFilter('attribute_set_id',$attributeSet->getId())
            ->addFieldToFilter('attribute_group_name',$attributeGroupName)
            ->getFirstItem()
            ->getId();
    }

    /**
     * Gets a particular attribute otherwise
     * returns false if it doesn't exist
     *
     * @param $code
     * @return bool|Varien_Object
     */
    private function _getAttribute($code) {

        $entityTypeId = $this->_getEntityTypeId();

        $attribute = Mage::getResourceModel('catalog/eav_mysql4_product_attribute_collection')
            ->addFieldToFilter('attribute_code',$code)
            ->addFieldToFilter('entity_type_id',$entityTypeId)
            ->getFirstItem();

        if ($attribute->getId()) {
            return $attribute;
        } else {
            return false;
        }
    }

    /**
     * Assigns the attribute to the attribute set
     *
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @param Mage_Eav_Model_Entity_Attribute_Set $attributeSet
     * @param int $attributeGroupId
     */
    protected function _assignAttributeToAttributeSet($attribute,$attributeSet,$attributeGroupId) {

        $entityTypeId = $this->_getEntityTypeId();

        $setup = Mage::getModel('eav/entity_setup','core_setup');
        $setup->addAttributeToSet($entityTypeId,$attributeSet->getId(),$attributeGroupId,$attribute->getId());
    }

    private function _getEntityTypeId() {
        return Mage::getModel('catalog/product')
            ->getResource()
            ->getEntityType()
            ->getId();
    }
}
