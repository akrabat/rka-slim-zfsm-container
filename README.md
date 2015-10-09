# Use Zend\ServiceManager with Slim 3

This component enables you to replace the default Pimple container in Slim 3
with Zend\ServiceManager.

## Usage

* `composer require akrabat/rka-slim-zfsm-container`
* update `index.php`:

    ```php
    $settings = [
        'settings' => [
            // user settings go here
        ],
        'factories' => [
            // new SM factories go here
        ],
        // you can use any other SM key here too.
    ];
    $container = new RKA\ZsmSlimContainer\Container($settings);
    $app = new \Slim\App($container);
    ```


There is also an [example application][1].

### Overriding Slim's defaults

To override the default Slim handlers (e.g. errorHandler, notFound, etc), you
can add them to the relevant section of `$settings` before instantiating the
container:

```php
$settings = [
    'factories' => [
        'errorHandler' => function ($container) {
            // do your own thing here
        },
    ],
];
$container = new RKA\ZsmSlimContainer\Container($settings);
$app = new \Slim\App($container);
```

Alternatively you can also override afterwards if you want to:

```php
$container->setAllowOverride(true);
$container['errorHandler'] = function($container) {
	// set-up error handler
};
```


[1]: https://github.com/akrabat/rka-slim-zfsm-container/blob/master/example-app/index.php
