<?php
/**
 * Slim Zend\ServiceManager Container
 *
 * @link      https://github.com/akrabat/rka-slim-zfsm-container
 * @copyright Copyright © 2015 Rob Allen
 * @license   https://github.com/akrabat/rka-slim-zfsm-containerLICENSE (BSD 3-Clause License)
 */
namespace RKA\SmContainer;

use Zend\ServiceManager\ServiceManager;
use Interop\Container\ContainerInterface;
use ArrayAccess;
use Interop\Container\Exception\ContainerException;
use RuntimeException;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\NotFoundException;
use Slim\Handlers\Error;
use Slim\Handlers\NotFound;
use Slim\Handlers\NotAllowed;
use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\CallableResolver;
use Slim\Router;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Interfaces\Http\EnvironmentInterface;
use Slim\Interfaces\RouterInterface;

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
        'cookieLifetime' => '20 minutes',
        'cookiePath' => '/',
        'cookieDomain' => null,
        'cookieSecure' => false,
        'cookieHttpOnly' => false,
        'httpVersion' => '1.1',
        'responseChunkSize' => 4096,
        'outputBuffering' => 'append',
    ];


    /********************************************************************************
     * Constructor sets up default Pimple services
     *******************************************************************************/

    /**
     * Create new container
     *
     * @param array $userSettings Associative array of application settings
     */
    public function __construct(array $userSettings = [])
    {
        parent::__construct();

        $defaultSettings = $this->defaultSettings;

        /**
         * This service MUST return an array or an
         * instance of \ArrayAccess.
         *
         * @param Container $c
         *
         * @return array|\ArrayAccess
         */
        $this->setFactory('settings', function ($c) use ($userSettings, $defaultSettings) {
            return array_merge($defaultSettings, $userSettings);
        });

        /**
         * This service MUST return a shared instance
         * of \Slim\Interfaces\Http\EnvironmentInterface.
         *
         * @param Container $c
         *
         * @return EnvironmentInterface
         */
        $this->setFactory('environment', function ($c) {
            return new Environment($_SERVER);
        });

        /**
         * This service MUST return a NEW instance
         * of \Psr\Http\Message\ServerRequestInterface.
         */
        $this->setFactory('request',
            /**
             * @param Container $c
             *
             * @return ServerRequestInterface
             */
            function ($c) {
                return Request::createFromEnvironment($c['environment']);
            },
            false // not shared
        );

        /**
         * This service MUST return a NEW instance
         * of \Psr\Http\Message\ResponseInterface.
         */
        $this->setFactory('response',
            /**
             * @param Container $c
             *
             * @return ResponseInterface
             */
            function ($c) {
                $headers = new Headers(['Content-Type' => 'text/html']);
                $response = new Response(200, $headers);

                return $response->withProtocolVersion($c['settings']['httpVersion']);
            },
            false // not shared
        );

        /**
         * This service MUST return a SHARED instance
         * of \Slim\Interfaces\RouterInterface.
         *
         * @param Container $c
         *
         * @return RouterInterface
         */
        $this->setFactory('router', function ($c) {
            return new Router();
        });

        /**
         * This service MUST return a callable
         * that accepts three arguments:
         *
         * 1. Instance of \Psr\Http\Message\ServerRequestInterface
         * 2. Instance of \Psr\Http\Message\ResponseInterface
         * 3. Instance of \Exception
         *
         * The callable MUST return an instance of
         * \Psr\Http\Message\ResponseInterface.
         *
         * @param Container $c
         *
         * @return callable
         */
        $this->setFactory('errorHandler', function ($c) {
            return new Error();
        });

        /**
         * This service MUST return a callable
         * that accepts two arguments:
         *
         * 1. Instance of \Psr\Http\Message\ServerRequestInterface
         * 2. Instance of \Psr\Http\Message\ResponseInterface
         *
         * The callable MUST return an instance of
         * \Psr\Http\Message\ResponseInterface.
         *
         * @param Container $c
         *
         * @return callable
         */
        $this->setFactory('notFoundHandler', function ($c) {
            return new NotFound();
        });

        /**
         * This service MUST return a callable
         * that accepts three arguments:
         *
         * 1. Instance of \Psr\Http\Message\ServerRequestInterface
         * 2. Instance of \Psr\Http\Message\ResponseInterface
         * 3. Array of allowed HTTP methods
         *
         * The callable MUST return an instance of
         * \Psr\Http\Message\ResponseInterface.
         *
         * @param Container $c
         *
         * @return callable
         */
        $this->setFactory('notAllowedHandler', function ($c) {
            return new NotAllowed;
        });

        /**
         * This service MUST return a NEW instance of
         * \Slim\Interfaces\CallableResolverInterface
         */
        $this->setFactory('callableResolver',
            /**
             * @param Container $c
             *
             * @return CallableResolverInterface
             */
            function ($c) {
                return new CallableResolver($c);
            },

            false // not shared
        );
    }

    /**
     * implement Pimple's register
     *
     */
    public function register($provider, array $values = array())
    {
        $provider->register($this);

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        return $this;
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
                return $this->setFactory($id, $value);
            }
            return $this->setService($id, $value);
        }

        if (is_string($value) && class_exists($value)) {
            return $this->setInvokableClass($id, $value);
        }

        return $this->setService($id, $value);
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
        return $this->unregisterService($id);
    }
}
