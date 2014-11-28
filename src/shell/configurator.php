<?php

// when the file is deployed via modman it is symlinked to the shell/ folder
// php will look in the realdir folder for the abstract.php wich fails

$dir_root = dirname($_SERVER['SCRIPT_FILENAME']) . DIRECTORY_SEPARATOR;

require_once $dir_root . 'abstract.php';

class Cti_Configurator_Shell extends Mage_Shell_Abstract {

    public function run() {
        $helper = Mage::helper('cti_configurator');
        $helper->processComponents(true);
    }
}
$shell = new Cti_Configurator_Shell();
$shell->run();
