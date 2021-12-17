# Magento® 2 Reloadify Integration

The Reloadify extension makes it effortless to connect your Magento® 2 store with the Reloadify platform.

## Installation

Before you start up the installation process, we recommend that you make a backup of your webshop files, as well as the database.

There are 2 different methods to install the Magento® 2 extension.

1.  Install by using Composer
2.  Install by using the Magento® Marketplace

#### 1) Installation using Composer

1 . Connect to your server running Magento® 2 using SSH.  
2 . Locate your Magento® 2 project root.  
3 . Install the Magento® 2 extension through composer and wait till it's completed:

`composer require magmodules/magento2-reloadify`  
   
4\. After that run the Magento® upgrade and clean cache:  
`php bin/magento setup:upgrade`  
`php bin/magento cache:flush`  
  
5 .  If Magento® is running in production mode you also need to redeploy the static content:  
`php bin/magento setup:static-content:deploy`  
  
6 .  After the installation: Go to your Magento® admin portal and open: `Stores > Configuration > Reloadify > General` to start setting up your connection.

#### 2) Installation using the Magento® Marketplace

The module will be available on the Magento® Marketplace later this month.

## Compatibility

The module has a minimum requirement of Magento 2.3 and is tested on Magento version 2.3.x & 2.4.x.
