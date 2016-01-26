<?php
class Cti_Configurator_Helper_Components_Products extends Cti_Configurator_Helper_Components_Abstract
{

    protected $_standardAttributes = array(
        'attribute_set_id',
        'type_id',
        'website_ids',
        'visibility',
        'stock_data',
        'stock_item',
        'status',
        'country_of_manufacture',
        'tax_class_id',
        'custom_design',
        'page_layout',
        'msrp_enabled',
        'msrp_display_actual_price_type',
        'is_recurring',
        'gift_message_available');

    protected $_componentName = 'products';

    protected $_images = array();

    public function __construct() {
        $this->_filePath1 = Mage::getBaseDir() . DS . 'app' . DS . 'etc' . DS . 'components' . DS . 'products.yaml';
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
        if (isset($data['products'])) {

            foreach ($data['products'] as $i=>$data) {

                $this->_addUpdateProduct($i,$data);
            }
        }
    }

    private function _addUpdateProduct($sku,$data,$child = false) {

        try {
            $productId = Mage::getModel('catalog/product')->getIdBySku($sku);

            $product = Mage::getModel('catalog/product');
            if ($productId) {
                $product->load($productId);
            } else {
                $product->setSku($sku);
            }

            $data = array_replace_recursive($this->_getProductDefaultSettings(),$data);
            $product->setMediaGallery(array('images'=>array (), 'values'=>array ()));

            foreach ($data as $key=>$value) {

                $attributeLog = true;

                // Setting the ID of the attribute set correctly
                if ($key == "attribute_set") {
                    $attribute = $this->_getAttributeSet($value);

                    if (!$attribute) {
                        throw new Exception($this->__('Attribute Set %s for %s does not exists',$value,$sku));
                    }
                    $key = "attribute_set_id";
                    $value = $attribute->getId();
                }

                // Setting for website
                if ($key == "websites") {
                    $key = 'website_ids';
                    $value = $this->_getWebsiteIds($value);
                }

                // QTY
                if ($key == "qty") {
                    $key = 'stock_data';
                    $value = array(
                        'use_config_manage_stock' => 1,
                        'is_in_stock' => 1,
                        'qty' => $data['qty']
                    );


                    $stockItem = Mage::getModel('cataloginventory/stock_item');
                    $stockItem->assignProduct($product);
                    $stockItem->setData('is_in_stock', 1);
                    $stockItem->setData('qty', $data['qty']);
                    $product->setStockItem($stockItem);
                }

                if ($key == "configurable_attributes") {
                    continue;
                }

                if ($key == "simple_products") {
                    continue;
                }

                if ($key == "weight" && $product->getTypeId() == "configurable") {
                    continue;
                }

                if ($key == "images") {
                    $this->_downloadAndPrepareImages($sku,$value);
                    continue;
                }

                // Get the attribute
                $attribute = $this->_getAttribute($key);


                // Check if the attribute exists
                if (!$attribute && !$this->_isStandardAttributeOption($key)) {
                    $this->log($this->__('Attribute %s does not exist',$key,$child));
                    continue;
                    //throw new Exception($this->__('Attribute %s does not exist',$key));
                }

                // If the value has to be obtained from a file - fetch it
                if (!is_array($value) && (strpos($value,'file:') !== false)) {
                    $filepath = substr($value,strlen('file: '));
                    $filepath = Mage::getBaseDir('etc') . '/components/'.$filepath;
                    if (file_exists($filepath)) {
                        $value = file_get_contents($filepath);
                        $attributeLog = false;
                    }
                }

                // If it is a multiselect value, get the relevant IDs to save as the value
                if (!$this->_isStandardAttributeOption($key) && $attribute->getFrontendInput() == "multiselect") {
                    $values = array();
                    foreach ($value as $optionValue) {
                        $optionId = $this->_getAttributeOptionIdByValue($attribute,$optionValue);
                        if (!$optionId) {
                            throw new Exception($this->__('Cannot find option value % for attribute %s',$optionValue,$attribute));
                        }
                        $values[] = $optionId;
                    }
                    unset($optionValue);
                    unset($optionId);
                    $value = $values;
                    unset($values);
                }

                // if it is a standard select value, get the option ID to save as the value
                if (!$this->_isStandardAttributeOption($key) && $attribute->getFrontendInput() == "select") {
                    $optionId = $this->_getAttributeOptionIdByValue($attribute,$value);
                    if (!$optionId) {
                        throw new Exception($this->__('Cannot find option value % for attribute %s',$value,$attribute));
                    }
                    $value = $optionId;
                    unset($optionId);
                }


                // If the attribute is a media image attribute
                if (!$this->_isStandardAttributeOption($key) && $attribute->getFrontendInput() == "media_image") {
                    if (isset($this->_images[$value]) && file_exists($this->_images[$value])) {
                        $product->addImageToMediaGallery($this->_images[$value], array($key), false, false);
                    } else {
                        $this->log($this->__('No image set in cache'));
                    }
                    continue;

                }

                // Save the value if not equal to the existing value
                if ($product->getData($key) != $value) {
                    $product->setData($key, $value);
                    if (is_array($value)) {
                        $value = implode(',',$value);
                    }
                    if ($attributeLog) {
                        $this->log($this->__('Product (%s) - Attribute %s -> %s', $sku, $key, $value), $child);
                    }
                }
                unset($attribute);
            }
            unset($key);
            unset($value);
            unset($attributeLog);

            // Add product image if specified and the file exists
//            $imageLocation = Mage::getBaseDir('etc') . '/components/images/';
//
//            if ((isset($data['imagefile'])) && (file_exists($imageLocation . $data['imagefile']))) {
//                $product->setMediaGallery(array('images'=>array (), 'values'=>array ()));
//                $product->addImageToMediaGallery($imageLocation . $data['imagefile'], array('image', 'small_image', 'thumbnail'), false, false);
//                $this->log($this->__('Product (%s) - Attribute %s -> %s', $sku, 'image', $data['imagefile']),$child);
//            }

            unset($imageLocation);

            // Configurable product specific configuration
            if ($data['type_id'] == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {

                // Ensure we have configurable attributes
                if (!isset($data['configurable_attributes'])) {
                    throw new Exception($this->__('There are no configurable attributes for %s',$sku));
                }

                // Loop through the attributes
                $configurableAttributes = array();
                foreach ($data['configurable_attributes'] as $configurableAttr) {
                    $configurableAttributes[] =$this->_getAttribute($configurableAttr)->getId();
                }

                // Associate configurable attributes to the product
                if ($product->getTypeInstance()->getUsedProductAttributeIds() != $configurableAttributes) {
                    $product->getTypeInstance()->setUsedProductAttributeIds($configurableAttributes);
                    $configurableAttributesData = $product->getTypeInstance()->getConfigurableAttributesAsArray();


                    $product->setCanSaveConfigurableAttributes(true);
                    $product->setConfigurableAttributesData($configurableAttributesData);
                }


                // Associate products
                $configurableProductsData = array();
                foreach ($data['simple_products'] as $key=>$sProductConfig) {

                    // Need to create/update the simple product first
                    $sData = $data;
                    $sData['type_id'] = 'simple';
                    $sData['name'] = $data['name'].' - '.$sProductConfig['label'];
                    $sData['visibility'] = Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE;
                    foreach ($sProductConfig['attributes'] as $attributeKey=>$attributeValue) {
                        $sData[$attributeKey] = $attributeValue;
                    }
                    $sProduct = $this->_addUpdateProduct($sku.'-'.$key,$sData,true);


                    // Associate the product
                    foreach ($sProductConfig['attributes'] as $attributeKey=>$attributeValue) {
                        $attribute = $this->_getAttribute($attributeKey);
                        $configurableProductsData[$sProduct->getId()][] = array(
                            'label'         => $sProductConfig['label'],
                            'attribute'     => $attribute->getId(),
                            'value'         => $this->_getAttributeOptionIdByValue($attribute, $attributeValue),
                            'is_percent'    => $sProductConfig['is_percent'],
                            'pricing_value' => $sProductConfig['pricing_value']
                        );
                    }
                    unset($sProductConfig['attributes']);
                }

                $product->setConfigurableProductsData($configurableProductsData);
            }

            $this->log($this->__('Preparing to save %s',$sku),$child);

            $product->setCreatedAt(strtotime('now'));
            $product->save();
            $this->_images = array();
            return $product;
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

    }


    /**
     * @param array $websites
     * @return array
     */
    private function _getWebsiteIds($websites) {
        $ids = array();
        foreach ($websites as $website) {
            $ids[] = Mage::getResourceModel('core/website_collection')
                ->addFieldToFilter('code',$website)
                ->getFirstItem()
                ->getId();
        }
        return $ids;
    }

    /**
     * Gets a particular attribute set
     * returns false if it doesn't exist
     *
     * @param $code
     * @return bool|Varien_Object
     */
    private function _getAttributeSet($code) {

        $entityTypeId = $this->_getEntityTypeId();

        $attributeSet = Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->addFieldToFilter('entity_type_id',$entityTypeId)
            ->addFieldToFilter('attribute_set_name',$code)
            ->getFirstItem();


        if ($attributeSet->getId()) {
            return $attributeSet;
        } else {
            return false;
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

    private function _getEntityTypeId() {
        return Mage::getModel('catalog/product')
            ->getResource()
            ->getEntityType()
            ->getId();
    }

    private function _isStandardAttributeOption($key) {
        return in_array($key,$this->_standardAttributes);
    }

    private function _getAttributeOptionIdByValue(Mage_Catalog_Model_Resource_Eav_Attribute $attribute,$value) {
        $options = $attribute->getSource()->getAllOptions(false);
        foreach ($options as $option) {
            if ($option['label'] == $value) {
                return $option['value'];
            }
        }
        return false;
    }

    private function _downloadAndPrepareImages($sku,$images) {

        $tmpProductsFolder = Mage::getBaseDir('media').DIRECTORY_SEPARATOR.'product-images'.DIRECTORY_SEPARATOR;

        if (!is_array($images)) {
            throw new Exception($this->__("An array of images should be supplied"));
        }

        if (!file_exists($tmpProductsFolder)) {

            // If the node does not have a name
            if (!is_numeric($tmpProductsFolder)) {

                // Then it is a directory so create it
                mkdir($tmpProductsFolder, 0755, true);
                $this->log("Created new media directory $tmpProductsFolder");
            }
        }

        foreach ($images as $i=>$imagePath) {

            $pathinfo = pathinfo($imagePath);
            $extension = $pathinfo['extension'];
            $newPath = $tmpProductsFolder.$sku.'_'.$i.'.'.$extension;

            if (!file_exists($newPath)) {

                // Get the contents of the file
                $this->log("Downloading image file from ".$imagePath);

                $fileContents = file_get_contents($imagePath);

                // Save it in the new path
                file_put_contents($newPath, $fileContents);
                unset($fileContents);
                $this->log("Created new file $newPath");
            }
            $this->_images[$i] = $newPath;
        }
    }

    private function _getProductDefaultSettings() {
        return array(
            'type_id'           => 'simple',
            'has_options'       => 0,
            'required_options'  => 0,
            'meta_title'        => '',
            'meta_description'  => '',
            'custom_design'     => null,
            'page_layout'       => null,
            'msrp_enabled'      => 2,
            'msrp_display_actual_price_type' => 2,
            'gift_message_available' => null,
            'is_recurring'      => 0,
            'visibility'        => 4,
            'tax_class_id'      => 0
        );
    }

}