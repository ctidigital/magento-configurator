<?php
require_once 'abstract.php';

class Cti_Configurator_Shell extends Mage_Shell_Abstract {

    protected $_components = array();

    public function __construct() {
        parent::__construct();

        if($this->getArg('run-components')) {
            $this->_components = array_merge(
                $this->_components,
                array_map(
                    'trim',
                    explode(',', $this->getArg('run-components'))
                )
            );
        }
    }

    public function run() {

        $helper = Mage::helper('cti_configurator');

        if ($this->getArg('list-components')) {

            foreach ($helper->getComponents() as $i=>$component) {
                echo $i.') '.$component.PHP_EOL;
            }

        } else if ($this->getArg('run-components')) {

            // Loop through components
            foreach ($this->_components as $component) {
                $helper->processComponent($component,true);
            }

        } else {

            // Process the rest of the components
            $helper->processComponents(true);
        }

    }

    // Usage instructions
    public function usageHelp() {
        return <<<USAGE
Usage:  php -f configurator.php -- [options]

  --list-components                       List components available to be used

  --run-components <component_aliases>    Run specific components, comma separated

  help                   This help

USAGE;
    }
}
$shell = new Cti_Configurator_Shell();
$shell->run();