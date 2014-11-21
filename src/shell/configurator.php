<?php

require_once 'abstract.php';

class Cti_Configurator_Shell extends Mage_Shell_Abstract {

    public function run() {
        $helper = Mage::helper('cti_configurator');
        $helper->processComponents(true);
    }
}
$shell = new Cti_Configurator_Shell();
$shell->run();