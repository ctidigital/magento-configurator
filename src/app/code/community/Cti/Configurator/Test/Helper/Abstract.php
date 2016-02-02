<?php
abstract class Cti_Configurator_Test_Helper_Abstract extends EcomDev_PHPUnit_Test_Case {

    protected $_moduleAlias = 'cti_configurator';
    protected $_classAlias = 'components_abstract';
    protected $_file1 = null;
    protected $_file2 = null;

    /**
     * Class is of the correct instance
     *
     * @test
     */
    public function extendsAbstractClass() {

        /* @var $helper Cti_Configurator_Helper_Components_Abstract */
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

    /**
     * Does the component have a sample file
     *
     * @test
     */
    public function hasASampleFile() {

        $exists = false;
        $path = Mage::getBaseDir('var') . DS . 'samples' . DS . $this->_file1;

        /* @var $helper Cti_Configurator_Helper_Components_Abstract */
        $helper = Mage::helper($this->_moduleAlias.'/'.$this->_classAlias);

        if ($this->_file1 !== null) {
            $helper->setFilePath1($path);
        }

        if(file_exists($helper->getFilePath1())) {
            $exists = true;
        }

        $this->assertTrue(
            $exists,
            $this->_moduleAlias.'/'.$this->_classAlias.' does not have a sample file. Tried looking here: '.$path
        );

    }

    /**
     * Does component successfully process
     *
     * @test
     */
    public function testProcessing() {
        /* @var $helper Cti_Configurator_Helper_Components_Abstract */
        $helper = Mage::helper($this->_moduleAlias.'/'.$this->_classAlias);
        if ($this->_file1 !== null) {
            $helper->setFilePath1(Mage::getBaseDir('var') . DS . 'samples' . DS . $this->_file1);
        }
        if ($this->_file2 !== null) {
            $helper->setFilePath2(Mage::getBaseDir('var') . DS . 'samples' . DS .  $this->_file2);
        }
        $helper->process();
        $this->assertEventDispatched($helper->getComponentName().'_configurator_process_after');
    }
}