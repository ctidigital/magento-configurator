<?php

if (file_exists('shell/abstract.php')) {
    require_once 'shell/abstract.php';
}
else {
    exit('Please call the Magento configurator from the Magento root dir.');
}

class Cti_Configurator_Shell extends Mage_Shell_Abstract {

    public function run() {
        $helper = Mage::helper('cti_configurator');
        $helper->processComponents(true);
    }
}
$shell = new Cti_Configurator_Shell();
$shell->run();
