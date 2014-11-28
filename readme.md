# Magento Configurator

A Magento module initially created by [CTI Digital] to create and maintain database variables using files. This module aims to bring the following benefits to a Magento developer's work flow:

  - Install Magento from scratch with important database based configuration ready.
  - Share and collaborate configuration with other colleagues using your own versioning system.
  - Keep versions of your configurations using your own versioning system.
  - Split your configuration based on the environment you're developing on.

If you're interested about finding out more about the background of the configurator, watch this lightning talk by [Rick Steckles] at Mage Titans in Manchester on [YouTube].

### Version
0.2.0

## Installation via Modman

```sh
$ cd <your magento install>
$ modman clone git@github.com:ctidigital/magento-configurator.git
```

## How To Use
Firstly, we'll need to configure our components. You can find how to configure your component under the heading "Components". As a minimum you will need to create the file `app/etc/components/websites.yaml` with one website.

In your magento directory you can run the configurator using:
```sh
$ cd <your magento install>/shell
$ php configurator.php
```

## Components

### Websites & Stores
This is generally the first component that should be configured when starting a project. This is used to create and maintain your websites, stores and store views and is controlled using a YAML which will require to be located in `app/etc/components/websites.yaml`. The general structure follows:
```
- websites:
  - website_code_1:
      name: Website Name
      - store_groups:
          -
            name: Store Group Name
            root_category: Default Category
            stores:
              - store_code_1:
                  name: Default Store View Name
              - store_code_2:
                  name: Store View Name 2
         -
            name: Store Group Name 2
            root_category: New Root Category
            stores:
              - store_code_3:
                  name: Store View Name 3
  - website_code_2:
      name: Website Name 2
      - store_groups:
          -
            name: Store Group Name 3
            root_category: Default Category
            stores:
              - store_code_4:
                  name: Store View Name 4
```

The YAML follows a tree structure which supports the following rules:
 - Many websites can be created
 - A website can have many store groups
 - A store group branch can have many store views

Sort orders will automatically be created based on the order the YAML is written.

### Core Config Data

After your websites have been correctly set up this is required to set up the core configuration elements for default (global), website level, and store view level. The end nodes require a `path` and a `value` which will be set in your Magento's `core_config_data`. If you don't know what the path should be, you can find it out by looking at the module's system.xml file or save a page with the configuration from within Magento's admin followed by saving the relevant section in System->Configuration and looking it up within your database's `core_config_data` table. The file will require to be in 
`app/etc/components/config.yaml`. You can also find our `config.yaml` file as an example on how to structure the file.

#### Default
```
- global:
    core_config:
      -
        path: design/package/name
        value: base
      -
        path: design/theme/default
        value: default
```

#### Inheritables
Sometimes we need to group configurations for use in many but not all websites or store views so in order to keep our YAML structure neat and tidy there is also support for grouped configurations. For example, some websites might share the same PayPal payment configuration.

```
- grouped:
  - paypal:
      core_config:
        -
          path: payment/pbridge/profilestatus
          value: 0
        -
          path: payment/paypal_express/active
          value: 1
        -
          path: payment/paypal_express_bml/active
          value: 0
```

#### Websites
Should we require website specific configurations this can be defined as so:
```
- websites:
  - base:
      core_config:
        -
          path: general/locale/timezone
          value: Europe/London
```
We can also inherit our group configurations using the inherit key as so:
```
- websites:
  - base:
      inherit:
        - paypal
      core_config:
        -
          path: general/locale/timezone
          value: Europe/London
```
#### Stores
Similarly to websites, we can define store view level configuration which also has inheritance support as so:
```
- stores:
  - default:
      core_config:
        -
          path: general/locale/code
          value: en_GB
```
#### Encrypted Values
Some core magento fields also encrypts our configuration so we can define this as so:
```
        -
          path: paypal/wpp/api_password
          value: 111111111
          encrypted: 1
```

## Development

Want to contribute? Great! We've tried to structure the module to make it extendable so should you wish to contribute then it should be fairly easy. There are a few rules you have to follow though.

### Config.xml
Firstly, within `app/code/community/Cti/Configurator/etc/config.xml` you will find the following xml:

```
...
    <global>
        <configurator_processors>
            <components>
                <website>cti_configurator/components_website</website>
                <config>cti_configurator/components_config</config>
            </components>
        </configurator_processors>
    </global>
...
```
Here you can define any components you wish to contribute. Should you wish to extend to create your own component within your own module, you can do so by adding your own component helper alias within your own module's `config.xml`.

### Component Helper

You'll need to create a helper class within the module, preferably in a Components subfolder and it should extend `Cti_Configurator_Helper_Components_Abstract` which will require you to:
 - Specify the file you wish to process by assigning an absolute path to the variable `$this->_filePath1`
 - (optional) Specify `$this->_filePath2` as a way of splitting your configuration between environments.
 - Create a protected `_processFile()` function to parse your file(s) (and merge) into a format of your choice.
 - Create a protected `_processComponent()` function to process the data you've acquired from the file you've processed.

The abstract function should handle the rest. You can look at our `Helper/Components/Website.php` and `Helper/Components/Config.php` as a guide to how you should structure your component helper.

## Roadmap

 - Create a component for Attribute Sets
 - Create a component for Attributes
 - Create a component for CMS Pages
 - Create a component for CMS Static Blocks
 - Create a component for Categories
 - Create a component for Products
 - Better CLI Logging
 - Write Tests

License
----

MIT


[CTI Digital]:http://www.ctidigital.com/
[YouTube]:https://www.youtube.com/watch?v=u9zHaX8G5_0
[Rick Steckles]:https://twitter.com/rick_steckles