<?php
class Cti_Configurator_Helper_Components_Attributes extends Cti_Configurator_Helper_Components_Abstract {

    public function __construct() {
        $this->_componentName = 'attributes';
        $this->_filePath1 = Mage::getBaseDir().DS.'app'.DS.'etc'.DS.'components'.DS.'attributes.yaml';
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

        if (isset($data['attributes'])) {

            foreach ($data['attributes'] as $code=>$attributeData) {

                $this->_createOrUpdateAttribute($code,$attributeData);
            }
        }
    }

    private function _createOrUpdateAttribute($code,$data) {
        $attribute = $this->_getAttribute($code);
        $canSave = false;
        if (!$attribute) {
            $this->log($this->__('Creating Attribute %s',$code));
            $attribute = Mage::getModel('catalog/resource_eav_attribute');
            $attribute->setData('attribute_code',$code);
            $canSave = true;
            $data = array_replace_recursive($this->_getAttributeDefaultSettings(),$data);
            $data['backend_type'] = $attribute->getBackendTypeByInput($data['frontend_input']);
            $data['source_model'] = Mage::helper('catalog/product')->getAttributeSourceModelByInputType($data['frontend_input']);
            $data['backend_model'] = Mage::helper('catalog/product')->getAttributeBackendModelByInputType($data['frontend_input']);
        }

        foreach ($data as $key=>$value) {
            // Skip store labels to be processed after
            if ($key == 'store_labels') {
                continue;
            }

            // YAML will pass product types as an array which needs to be imploded
            if ($key == "product_types") {
                $key = "apply_to";
                $value = implode(",",$value);
            }

            // YAML will pass options as an array which will require to be added differently
            if ($key == "options") {
                continue;
            }

            // Skip setting search_weight if not enterprise
            if ($key == "search_weight" && Mage::getEdition() != Mage::EDITION_ENTERPRISE) {
                continue;
            }

            if ($attribute->getId() && $attribute->getData($key) == $value) {
                continue;
            }

            $attribute->setData($key,$value);
            $this->log($this->__('Setting attribute "%s" %s with %s',$code,$key,$value));
            $canSave = true;
        };
        unset($key);
        unset($value);
        if (!$attribute->getEntityTypeId()) {
            $attribute->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
            $attribute->setIsUserDefined(1);
        }
        try {
            if ($canSave) {
                $attribute->save();
                $this->log($this->__('Saved Attribute %s',$code));
            }
            if ($data['options']) {
                $this->_maintainAttributeOptions($attribute,$data['options']);
            }
            if ($data['store_labels']) {
                $this->_maintainAttributeStoreLabels($attribute, $data['store_labels']);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     * Gets a particular attribute otherwise
     * returns false if it doesn't exist
     *
     * @param $code
     * @return bool|Varien_Object
     */
    private function _getAttribute($code) {
        $entityTypeId = Mage::getModel('catalog/product')
            ->getResource()
            ->getEntityType()
            ->getId();

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
     * Assigns options to an attribute
     *
     * The attribute should be a select/multiselect
     *
     * @param $attribute
     * @param $options
     */
    private function _maintainAttributeOptions($attribute,$options) {

        $currentOptions = $attribute->getSource()->getAllOptions();

        $currentOptionFormat = array();
        foreach ($currentOptions as $option) {
            if ($option['label'] != '') {
                $currentOptionFormat[] = $option['label'];
            }
        }

        $attributeId = $attribute->getId();
        $optionsToAdd = array_diff($options,$currentOptionFormat);
        $optionsToRemove = array_diff($currentOptionFormat,$options);
        $eav_entity_setup = new Mage_Eav_Model_Entity_Setup('core_setup');

        // Create new attributes
        foreach ($optionsToAdd as $option) {

            unset($new_option);

            // Check if the option already exists
            if ($attribute->getSource()->getOptionId($option)) {
                $this->log($this->__("Exsting attribute option %s for %s",$option,$attribute->getAttributeCode()));
            }
            $new_option['attribute_id'] = $attributeId;
            $new_option['value']['_custom_'.$option][0] = $option;
            $eav_entity_setup->addAttributeOption($new_option);

            /*
            $attribute->setData(
                'option',
                array(
                    'value'=>array(
                        'option'=>array(
                            $option
                        )
                    )
                )
            );
            $attribute->save();
            */

            $this->log($this->__("Created attribute option %s for %s",$option,$attribute->getAttributeCode()));
        }

        // Remove old attributes
        foreach ($optionsToRemove as $option) {
            $optionId = $attribute->getSource()->getOptionId($option);
            $toDelete['delete'][$optionId] = true;
            $toDelete['value'][$optionId] = true;
            $setup = new Mage_Eav_Model_Entity_Setup('core_setup');
            $setup->addAttributeOption($toDelete);
            $this->log($this->__("Deleted attribute option %s for %s",$option,$attribute->getAttributeCode()));
        }
    }


    /**
     * Assign store labels to an attribute
     *
     * @param $attribute
     * @param $labels
     */
    private function _maintainAttributeStoreLabels($attribute, $labels)
    {
        //get existing labels
        $storeLabels = Mage::getModel('eav/entity_attribute')->getResource()
            ->getStoreLabelsByAttributeId($attribute->getId());

        //build up array of storeId - Label
        foreach ($labels as $storeCode => $label) {
            try{
                $store = Mage::app()->getStore($storeCode);
                $storeId = $store->getId();
                $storeLabels[$storeId] = $label;
            } catch (Exception $e){
                $this->log($this->__('Skipping label for store "' . $storeCode . '" - Check the store is setup '));
            }
        }

        //save the new store labels
        $attribute->setStoreLabels($storeLabels)->save();

    }

    /**
     * @return array
     */
    private function _getAttributeDefaultSettings() {
        return array(
            'is_global'                 => 0,
            'is_visible'                => 1,
            'is_searchable'             => 0,
            'is_filterable'             => 0,
            'is_comparable'             => 0,
            'is_visible_on_front'       => 0,
            'is_html_allowed_on_front'  => 0,
            'is_used_for_price_rules'   => 0,
            'is_filterable_in_search'   => 0,
            'used_in_product_listing'   => 0,
            'used_for_sort_by'          => 0,
            'is_configurable'           => 0,
            'product_types'             => NULL,
            'is_visible_in_advanced_search' => 0,
            'position'                  => 0,
            'is_wysiwyg_enabled'        => 0,
            'is_used_for_promo_rules'   => 0,
            'default_value_text'        => '',
            'default_value_yesno'       => 0,
            'default_value_date'        => '',
            'default_value_textarea'    => '',
            'is_unique'                 => 0,
            'is_required'               => 0,
            'frontend_input'            => 'boolean',
            'backend_type'              => 'static',
            'search_weight'             => 1 // EE only
        );
    }
}