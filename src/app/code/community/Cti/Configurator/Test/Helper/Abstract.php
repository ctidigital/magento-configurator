<?php
abstract class Cti_Configurator_Test_Helper_Abstract extends EcomDev_PHPUnit_Test_Case {

    protected $_moduleAlias = 'cti_configurator';
    protected $_classAlias = 'components_abstract';

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
}