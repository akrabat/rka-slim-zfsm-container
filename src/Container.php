<?php
/**
 * An extension to Zend\ServiceManager to integrate it with Slim 3
 *
 * @link      https://github.com/akrabat/rka-slim-zfsm-container
 * @copyright Copyright © 2015 Rob Allen
 * @license   https://github.com/akrabat/rka-slim-zfsm-containerLICENSE (BSD 3-Clause License)
 */
namespace RKA\ZsmSlimContainer;

use ArrayAccess;
use Interop\Container\ContainerInterface;
use RuntimeException;
use Slim\CallableResolver;
use Slim\Handlers\Error;
use Slim\Handlers\NotAllowed;
use Slim\Handlers\NotFound;
use Slim\Handlers\Strategies\RequestResponse;
use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Router;
use Zend\ServiceManager\ServiceManager;

/**
 * Extend Zend\ServiceManager for use with Slim
 *
 * Slim\App expects a container that implements Interop\Container\ContainerInterface
 * with these service keys configured and ready for use:
 *
 *  - settings: an array or instance of \ArrayAccess
 *  - environment: an instance of \Slim\Interfaces\Http\EnvironmentInterface
 *  - request: an instance of \Psr\Http\Message\ServerRequestInterface
 *  - response: an instance of \Psr\Http\Message\ResponseInterface
 *  - router: an instance of \Slim\Interfaces\RouterInterface
 *  - foundHandler: an instance of \Slim\Interfaces\InvocationStrategyInterface
 *  - errorHandler: a callable with the signature: function($request, $response, $exception)
 *  - notFoundHandler: a callable with the signature: function($request, $response)
 *  - notAllowedHandler: a callable with the signature: function($request, $response, $allowedHttpMethods)
 *  - callableResolver: an instance of \Slim\Interfaces\CallableResolverInterface
 */
final class Container extends ServiceManager implements ContainerInterface, ArrayAccess
{
    /**
     * Default settings
     *
     * @var array
     */
    private $defaultSettings = [
        'httpVersion' => '1.1',
        'responseChunkSize' => 4096,
        'outputBuffering' => 'append',
        'determineRouteBeforeAppMiddleware' => false,
        'displayErrorDetails' => false,
    ];


    /********************************************************************************
     * Constructor sets up default services
     *******************************************************************************/

    /**
     * Create new container
     *
     * @param array $settings Associative array of settings. User settings are in a 'settings' sub-array
     */
    public function __construct($settings = [])
    {
        $userSettings = [];
        if (isset($settings['settings'])) {
            $userSettings = $settings['settings'];
            unset($settings['settings']);
        }

        // Add settings factory that also collects the default settings
        $defaultSettings = $this->defaultSettings;
        $settings['factories']['settings'] = function ($c) use ($userSettings, $defaultSettings) {
            return array_merge($defaultSettings, $userSettings);
        };

        // Add default services if they aren't added already

        if (!isset($settings['environment'])) {
            $settings['factories']['environment'] = function ($c) {
                return new Environment($_SERVER);
            };
        }

        if (!isset($settings['request'])) {
            $settings['factories']['request'] = function ($c) {
                return Request::createFromEnvironment($c['environment']);
            };
        }

        if (!isset($settings['response'])) {
            $settings['factories']['response'] = function ($c) {
                $headers = new Headers(['Content-Type' => 'text/html']);
                $response = new Response(200, $headers);

                return $response->withProtocolVersion($c['settings']['httpVersion']);
            };
        }

        if (!isset($settings['router'])) {
            $settings['factories']['router'] = function ($c) {
                return new Router();
            };
        }

        if (!isset($settings['callableResolver'])) {
            $settings['factories']['callableResolver'] = function ($c) {
                return new CallableResolver($c);
            };
        }

        if (!isset($settings['foundHandler'])) {
            $settings['invokables']['foundHandler'] = RequestResponse::class;
        }

        if (!isset($settings['errorHandler'])) {
            $settings['factories']['errorHandler'] = function ($c) {
                return new Error($c->get('settings')['displayErrorDetails']);
            };
        }

        if (!isset($settings['notFoundHandler'])) {
            $settings['invokables']['notFoundHandler'] = NotFound::class;
        }

        if (!isset($settings['notAllowedHandler'])) {
            $settings['invokables']['notAllowedHandler'] = NotAllowed::class;
        }

        parent::__construct($settings);
    }

    /**
     * Sets a parameter or an object.
     *
     * Objects must be defined as Closures.
     *
     * Allowing any PHP callable leads to difficult to debug problems
     * as function names (strings) are callable (creating a function with
     * the same name as an existing parameter would break your container).
     *
     * @param  string            $id    The unique identifier for the parameter or object
     * @param  mixed             $value The value of the parameter or a closure to define an object
     * @throws \RuntimeException Prevent override of a frozen service
     */
    public function offsetSet($id, $value)
    {
        if (is_object($value)) {
            if ($value instanceof \Closure) {
                $this->setFactory($id, $value);
            }
            $this->setService($id, $value);
        }

        if (is_string($value) && class_exists($value)) {
            $this->setInvokableClass($id, $value);
        }

        $this->setService($id, $value);
    }

    /**
     * Gets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return object|array
     *
     * @throws \Zend\ServiceManager\Exception\ServiceNotFoundException
     */
    public function offsetGet($id)
    {
        return $this->get($id);
    }

    /**
     * Checks if a parameter or an object is set.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return bool
     */
    public function offsetExists($id)
    {
        return $this->has($id);
    }

    /**
     * Unsets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     */
    public function offsetUnset($id)
    {
        $this->setService($id, null);
    }

    /********************************************************************************
     * Magic methods for convenience
     *******************************************************************************/

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __isset($name)
    {
        return $this->has($name);
    }
}
