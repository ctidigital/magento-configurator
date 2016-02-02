<?php
abstract class Cti_Configurator_Test_Helper_Abstract extends EcomDev_PHPUnit_Test_Case {

    protected $_moduleAlias = 'cti_configurator';
    protected $_classAlias = 'components_abstract';
    protected $_file1 = null;
    protected $_file2 = null;

    /**
     * Test to see if class is the correct instance
     *
     * @test
     */
    public function extendsAbstractClass() {

        $helper = Mage::helper($this->_moduleAlias.'/'.$this->_classAlias);
        $isInstance = false;

        if ($helper instanceof Cti_Configurator_Helper_Components_Abstract) {
            $isInstance = true;
        }

        $this->assertTrue(
            $isInstance,
            $this->_moduleAlias.'/'.$this->_classAlias.' is not an instance of Cti_Configurator_Test_Helper_Abstract'
        );
    }

    public function testProcessing() {
        $helper = Mage::helper($this->_moduleAlias.'/'.$this->_classAlias);
        if ($this->_file1 !== null) {
            $helper->setFilePath1(Mage::getBaseDir() . 'var' . DS . 'samples' . $this->_file1);
        }
        if ($this->_file2 !== null) {
            $helper->setFilePath2(Mage::getBaseDir() . 'var' . DS . 'samples' . $this->_file2);
        }
        $helper->process();
    }
}