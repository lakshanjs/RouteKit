<?php

declare(strict_types=1);

namespace LakshanJS\RouteKit;

/**
 * Class App
 *
 * The main application class for RouteKit. Implements the singleton pattern,
 * provides an autoloader for class files, and supports dynamic method calls
 * and dynamic property assignment.
 *
 * @property mixed $request A placeholder for storing request-specific data.
 */
#[\AllowDynamicProperties]
class App
{
    /**
     * The singleton instance of the App.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Request property placeholder.
     *
     * This property can be used to store request-specific information or objects.
     *
     * @var mixed
     */
    public mixed $request;

    /**
     * Constructor.
     *
     * Automatically registers the autoloader upon instantiation.
     */
    public function __construct()
    {
        $this->autoload();
    }

    /**
     * Retrieves the singleton instance of the App.
     *
     * This method ensures that only one instance of the App class exists.
     *
     * @return static The singleton instance.
     */
    public static function instance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Registers the autoloader for class files.
     *
     * If the BASE_PATH constant is not defined, it sets it to the current directory.
     * Then, it registers an anonymous function with spl_autoload_register to handle
     * the loading of class files based on various naming conventions.
     *
     * @return void
     */
    public function autoload(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR);
        }

        spl_autoload_register(function (string $className): bool {
            // Convert namespace separators to directory separators.
            $normalizedClass = str_replace('\\', DIRECTORY_SEPARATOR, $className);
            $classNameOnly   = basename($normalizedClass);
            $namespace       = substr($normalizedClass, 0, -strlen($classNameOnly));

            // List of possible file paths based on naming conventions.
            $paths = [
                BASE_PATH . $normalizedClass . '.php',
                BASE_PATH . strtolower($namespace) . $classNameOnly . '.php',
                BASE_PATH . strtolower($normalizedClass) . '.php',
                BASE_PATH . $namespace . lcfirst($classNameOnly) . '.php',
                BASE_PATH . strtolower($namespace) . lcfirst($classNameOnly) . '.php',
            ];

            // Check each candidate path and include the file if it exists.
            foreach ($paths as $file) {
                if (is_file($file)) {
                    include_once $file;
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * Handles dynamic calls to methods that are not explicitly defined.
     *
     * If a property exists with the given method name and is callable, it will
     * be executed with the provided arguments.
     *
     * @param string $method The method name being invoked.
     * @param array  $args   An array of arguments passed to the method.
     *
     * @return mixed The result of the callable, or null if not callable.
     */
    public function __call(string $method, array $args): mixed
    {
        if (isset($this->{$method}) && is_callable($this->{$method})) {
            return call_user_func_array($this->{$method}, $args);
        }
        return null;
    }

    /**
     * Dynamically sets a property on the App instance.
     *
     * If the value provided is a Closure, it is automatically bound to the current
     * instance before assignment.
     *
     * @param string $property The name of the property.
     * @param mixed  $value    The value to assign to the property.
     *
     * @return void
     */
    public function __set(string $property, mixed $value): void
    {
        $this->{$property} = $value instanceof \Closure ? $value->bindTo($this) : $value;
    }
}
