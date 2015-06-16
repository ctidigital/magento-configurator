<?php
class Cti_Configurator_Helper_Components_Tax extends Cti_Configurator_Helper_Components_Abstract
{

    protected $_componentName = 'tax';
    protected $_defaultRate = array(
        'tax_region_id' => 0,
        'tax_postcode'  => '*',
        'zip_is_range'  => null,
        'zip_from'      => null,
        'zip_to'        => null
    );

    public function __construct()
    {

        $this->_filePath1 = Mage::getBaseDir() . DS . 'app' . DS . 'etc' . DS . 'components' . DS . 'tax.yaml';

    }

    protected function _processFile($globalFile, $localFile = null)
    {
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

    protected function _processComponent($data)
    {

        // Loop through the customer classes
        if (isset($data['customer_classes'])) {
            foreach ($data['customer_classes'] as $customerClass) {
                $this->_addTaxClass($customerClass,Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER);
            }
        }

        // Loop through the product classes
        if (isset($data['product_classes'])) {
            foreach ($data['product_classes'] as $productClass) {
                $this->_addTaxClass($productClass,Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT);
            }
        }

        // Loop through the tax rates
        if (isset($data['rates'])) {
            foreach ($data['rates'] as $code => $rate) {
                $this->_addRate($code,$rate);
            }
        }

        // Loop through the tax rules
        if (isset($data['rules'])) {
            foreach ($data['rules'] as $rule) {
                $this->_addRule($rule);
            }
        }
    }

    private function _addTaxClass($name, $type) {
        $taxClasses = Mage::getResourceModel('tax/class_collection')
            ->addFieldToFilter('class_type',$type)
            ->addFieldToFilter('class_name',$name);

        if ($taxClasses->count() == 0) {
            try {
                Mage::getModel('tax/class')
                    ->setClassType($type)
                    ->setClassName($name)
                    ->save();

                $this->log($this->__('Added new %s tax class %s',strtolower($type),$name));
            } catch (Exception $e) {
                $this->log($e->getMessage());
            }
        }
    }

    private function _addRate($code,$data) {
        $taxRate = Mage::getModel('tax/calculation_rate')->load($code,'code');
        if (!$taxRate->getId()) {
            $taxRate = Mage::getModel('tax/calculation_rate')->setCode($code);
        }
        $toSave = false;
        $data = array_merge_recursive($data,$this->_defaultRate);
        foreach ($data as $key=>$value) {
            if ($taxRate->getData($key) == $value) {
                continue;
            }
            if ($key == "tax_country_id" && $value == "Norway") {
                $value = "NO";
            }
            $taxRate->setData($key,$value);
            $this->log($this->__('Setting tax rate %s attribute %s to %s',$code,$key,$value));
            $toSave = true;
        }
        if ($toSave) {
            try {
                $taxRate->save();
            } catch (Exception $e) {
                $this->log($e->getMessage());
            }
        }
    }

    private function _addRule($data) {

        $taxRules = Mage::getResourceModel('tax/calculation_rule_collection')
            ->addFieldToFilter('code',$data['code']);
        if ($taxRules->count()) {
            $taxRule = $taxRules->getFirstItem();
        } else {
            $taxRule = Mage::getModel('tax/calculation_rule');
        }

        unset($taxRules);

        $data = array_merge_recursive($data,array('priority'=> 1,'position' => 1));
        $toSave = false;
        foreach ($data as $key=>$value) {
            if ($key == "tax_rate" || $key == "customer_tax_class" || $key == "product_tax_class") {
                continue;
            }
            if ($taxRule->getData($key) == $value) {
                continue;
            }
            $taxRule->setData($key,$value);
            $this->log($this->__('Setting tax rule attribute %s to %s',$key,$value));
            $toSave = true;
        }
        if ($toSave) {
            try {
                $taxRule->save();
            } catch (Exception $e) {
                $this->log($e->getMessage());
            }
        }


        // Link up the tax rules and classes
        try {
            foreach ($data['tax_rate'] as $code) {
                $rateId = Mage::getModel('tax/calculation_rate')->load($code,'code')->getId();
                if (!$rateId) {
                    throw new Exception($this->__('There is no rate for country code %s',$code));
                }
                foreach ($data['customer_tax_class'] as $_customerClass) {
                    $customerClass = $this->_getTaxClass($_customerClass,Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER);
                    if (!$customerClass) {
                        throw new Exception($this->__('There is no customer class found for %s',$_customerClass));
                    }
                    foreach ($data['product_tax_class'] as $_productClass) {
                        $productClass = $this->_getTaxClass($_productClass,Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT);
                        if (!$productClass) {
                            throw new Exception($this->__('There is no product class found for %s',$_productClass));
                        }

                        // Create new Tax Rule
                        try {
                            $taxCalculationCollection = Mage::getResourceModel('tax/calculation_collection')
                                ->addFieldToFilter('tax_calculation_rate_id',$rateId)
                                ->addFieldToFilter('tax_calculation_rule_id',$taxRule->getId())
                                ->addFieldToFilter('customer_tax_class_id',$customerClass->getId())
                                ->addFieldToFilter('product_tax_class_id',$productClass->getId());

                            if (!$taxCalculationCollection->count()) {
                                $taxCalculation = Mage::getModel('tax/calculation')
                                    ->setTaxCalculationRateId($rateId)
                                    ->setTaxCalculationRuleId($taxRule->getId())
                                    ->setCustomerTaxClassId($customerClass->getId())
                                    ->setProductTaxClassId($productClass->getId())
                                    ->save();

                                $this->log($this->__('Created tax rule %s', $taxCalculation->getId()));
                            }
                        } catch (Exception $e) {
                            $this->__($e->getMessage());
                        }

                    }
                }
            }
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }



    }

    /**
     * @param $name
     * @param $type
     *
     * @return Mage_Tax_Model_Class
     */
    private function _getTaxClass($name,$type) {
        $taxClasses = Mage::getResourceModel('tax/class_collection')
            ->addFieldToFilter('class_type',$type)
            ->addFieldToFilter('class_name',$name);
        if ($taxClasses->count()) {
            return $taxClasses->getFirstItem();
        }
        return false;
    }
}