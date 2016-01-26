<?php
class Cti_Configurator_Helper_Components_Users extends Cti_Configurator_Helper_Components_Abstract {

    protected $_componentName = 'Users';

    public function __construct()
    {

        $this->_filePath1 = Mage::getBaseDir() . DS . 'app' . DS . 'etc' . DS . 'components' . DS . 'users.yaml';

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

        // Loop through the soap users and roles
        if (isset($data['soap'])) {
            if (isset($data['soap']['roles'])) {
                $this->_addSoapRoles($data['soap']['roles']);
            }
            if (isset($data['soap']['users'])) {
                $this->_addSoapUsers($data['soap']['users']);
            }
        }

        // @todo Loop through the rest users and roles
        if (isset($data['rest'])) {
            $this->log('Todo - support for rests api users');
        }

        // @todo Loop through the admin users and roles
        if (isset($data['admin'])) {
            $this->log('Todo - support for admin users');
        }
    }

    private function _addSoapUsers($users) {
        foreach ($users as $username=>$data) {
            try {

                $canSave = false;

                $user = Mage::getModel('api/user')->load($username,'username');

                if (!$user->getId()) {
                    $user = Mage::getModel('api/user');
                    $user->setUsername($username);
                    $canSave = true;
                }

                foreach ($data as $key => $value) {

                    if ($key == "role") {
                        continue;
                    }

                    if ($user->getData($key) == $value) {
                        continue;
                    }

                    $user->setData($key,$value);
                    $canSave = true;
                    $this->log($this->__('Set soap user record for %s key %s to value %s',$username,$key,$value));
                }

                if ($canSave) {
                    $user->save();
                    $this->log('Saved soap user '.$username);
                }

                if (isset($data['role'])) {
                    try {

                        $parentRole = Mage::getResourceModel('api/role_collection')
                            ->addFieldToFilter('role_name',$data['role'])
                            ->getFirstItem();

                        if (!$parentRole->getId()) {
                            throw new Exception('No role name for '.$data['role']);
                        }

                        $userRole = Mage::getResourceModel('api/role_collection')
                            ->addFieldToFilter('role_name',$username)
                            ->getFirstItem();


                        if (!$userRole->getId()) {
                            $userRole = Mage::getModel('api/role')
                                ->setRoleName($username);

                            $this->log($this->__('New user role for %s',$username));
                        }

                        $userRole
                            ->setParentId($parentRole->getId())
                            ->setRoleType('U')
                            ->setTreeLevel(1)
                            ->setUserId($user->getId())
                            ->save();

                    } catch (Exception $e) {
                        $this->log($e->getMessage());
                    }
                }

            } catch (Exception $e) {
                $this->log($e->getMessage());
            }
        }
    }

    private function _addSoapRoles($roles) {
        foreach ($roles as $roleName=>$data) {
            try {

                $canSave = false;

                $role = Mage::getResourceModel('api/role_collection')
                    ->addFieldToFilter('role_name',$roleName)
                    ->getFirstItem();

                if (!$role->getId()){
                    $role = Mage::getModel('api/role');
                    $role->setRoleName($roleName);
                    $canSave = true;
                }

                foreach ($data as $key=>$value) {
                    if ($key == "rules") {
                        continue;
                    }

                    if ($role->getData($key) == $value) {
                        continue;
                    }

                    $role->setData($key,$value);
                    $canSave = true;
                    $this->log($this->__('Set soap role record for %s key %s to value $s',$roleName,$key,$value));
                }

                if ($canSave) {
                    $role->save();
                    $this->log('Saved role '.$roleName);
                }

                $this->_addSoapRules($role,$data['rules']);
            } catch (Exception $e) {
                $this->log($e->getMessage());
            }
        }
    }

    private function _addSoapRules(Mage_Api_Model_Role $role,$rules) {
        foreach ($rules as $data) {
            try {
                $canSave = false;

                $rule = Mage::getResourceModel('api/rules_collection')
                    ->addFieldToFilter('role_id',$role->getId())
                    ->addFieldToFilter('resource_id',$data['resource_id'])
                    ->getFirstItem();

                if (!$rule->getId()) {
                    $rule = Mage::getModel('api/rules');
                    $rule->setRoleId($role->getId());
                    $canSave = true;
                }

                foreach ($data as $key=>$value) {
                    if ($rule->getData($key) == $value) {
                        continue;
                    }
                    $rule->setData($key,$value);
                    $canSave = true;
                    $this->log($this->__('Set soap rule record for role %s key %s to value %s',$role->getRoleName(),$key,$value),1);
                }

                if ($canSave) {
                    $rule->save();
                    $this->log('Saved rule for '.$role->getRoleName().' - '.$data['resource_id'],1);
                }

            } catch (Exception $e) {
                $this->log($e->getMessage());
            }
        }
    }
}