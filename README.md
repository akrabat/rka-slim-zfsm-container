# RKA\ZsmSlimContainer

This component enables you to replace the default Pimple container in Slim 3
with Zend\ServiceManager.

## Usage

* `composer require akrabat/rka-slim-zfsm-container`
* update `index.php`:

        $settings = [];
        $container = new RKA\ZsmSlimContainer\Container($settings);
        $app = new \Slim\App($container);

To override the default Slim handlers (e.g. errorHandler, notFound, etc), you have to allow 
override on the container like so:

        $container->setAllowOverride(true);
        $container['errorHandler'] = function($container) {
        	//set-up error handler
        };

There is also an [example application][1].

[1]: https://github.com/akrabat/rka-slim-zfsm-container/blob/master/example-app/index.php