<?php
class Cti_Configurator_Helper_Components_Attributes extends Cti_Configurator_Helper_Components_Abstract {

    const DEFAULT_MARKER = 'default';
    const NUMBER_MARKER = '###';

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
        }
        $data = array_replace_recursive($this->_getAttributeDefaultSettings(),$data);

        foreach ($data as $key=>$value) {

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
        $attribute->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
        $attribute->setIsUserDefined(1);

        try {
            if ($canSave) {
                $attribute->save();
                $this->log($this->__('Saved Attribute %s',$code));
            }
            if ($data['options']) {
                $this->_maintainAttributeOptions($attribute,$data['options']);
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
    private function _maintainAttributeOptions($attribute, $options)
    {
        $currentOptions = array_filter($attribute->getSource()->getAllOptions(), function ($option) { return $option["label"] !== ""; });

        // Make label => id map of currentOptions
        $currentOptions = array_combine(
            array_map(function ($keys) { return $keys['label']; }, $currentOptions),
            array_map(function ($keys) { return $keys['value']; }, $currentOptions)
        );

        // Look for default marker
        $default = null;
        $options = array_map(function ($option) use (&$default, $currentOptions) {
            if ($this->isDefault($option)) {
                $default = $this->getOptionId($option, $currentOptions);
            }
            return $this->trimDefault($option);
        }, $options);

        // Ordered by position in YAML
        $optionsPosition = array_flip($options);

        $toCreate = array_diff($options, array_keys($currentOptions));
        $toDelete = array_diff(array_keys($currentOptions), $options);

        // Placeholders
        $value  = [];
        $order  = [];
        $delete = [];

        // All properties (value, order, delete) are set no matter the operation
        $allOptions = array_unique(array_merge($options, array_keys($currentOptions)));
        foreach ($allOptions as $option) {
            $option_id = null;
            if (in_array($option, $toCreate)) {
                $option_id = is_numeric($option) ? self::NUMBER_MARKER . $option : $option;
            } else {
                $option_id = $currentOptions[$option];
            }

            $value[$option_id]  = [ 0 => $option ];
            $order[$option_id]  = "" . isset($optionsPosition[$option]) ? $optionsPosition[$option] : "0";
            $delete[$option_id] = "";

            if (in_array($option, $toDelete, true)) {
                $delete[$option_id] = "1"; // value of "1" means delete
            }
        }

        // Data-structure derrived from saveAction in Mage_Adminhtml_Catalog_Product_AttributeController
        $data = [
            "option" => [
                "value"  => $value,
                "order"  => $order,
                "delete" => $delete
            ],
            "default" => [ $default ]
        ];

        $attribute->addData($data);
        $attribute->save();
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
            'search_weight'             => 1 // EE only
        );
    }

    private function isDefault($option)
    {
        return strpos($option, "|" . self::DEFAULT_MARKER) !== false;
    }
    private function trimDefault($option)
    {
        return $this->isDefault($option) ? substr($option, 0, strpos($option, "|" . self::DEFAULT_MARKER)) : $option;
    }
    private function getOptionId($option, $currentOptions = [])
    {
        $option = $this->trimDefault($option);
        return isset($currentOptions[$option]) ? $currentOptions[$option] : $option;
    }
}
