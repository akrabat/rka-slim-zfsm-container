# slim-zfsm-container

This component enables you to replace the default Pimple container in Slim 3
with Zend\ServiceManager.

## Usage

* `composer require akrabat/rka-slim-zfsm-container`
* update `index.php`:

        $settings = [];
        $container = new RKA\SmContainer\Container($settings);
        $app = new \Slim\App($container);
