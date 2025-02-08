<?php

declare(strict_types=1);

use LakshanJS\RouteKit\App;

if (!function_exists('app')) {
    /**
     * Retrieve the singleton instance of the application or a specific property from it.
     *
     * If a property name is provided, this function returns the value of that property
     * from the App instance. Otherwise, it returns the App instance itself.
     *
     * @param string|null $property The name of the property to retrieve (optional).
     *
     * @return mixed The App instance or the value of the requested property.
     */
    function app(?string $property = null): mixed
    {
        $appInstance = App::instance();

        if ($property !== null) {
            return $appInstance->{$property};
        }

        return $appInstance;
    }
}

if (!function_exists('url')) {
    /**
     * Generate a complete URL by appending the given path to the base URL.
     *
     * The base URL is defined by the constant URL. If a path is provided, it will be
     * trimmed of any extra whitespace and trailing slashes.
     *
     * @param string|null $path The path to append to the base URL (optional).
     *
     * @return string The complete URL.
     */
    function url(?string $path = null): string
    {
        if ($path !== null) {
            $path = rtrim(trim($path), '/');
        }

        return URL . $path;
    }
}

if (!function_exists('route')) {
    /**
     * Generate a URL for a named route.
     *
     * This function retrieves the route path from the application's "route" property
     * by calling its getRoute() method, and then generates a complete URL using the
     * url() function.
     *
     * @param string $name The name of the route.
     * @param array  $args Optional arguments to pass to the route generator.
     *
     * @return string The complete URL for the named route.
     */
    function route(string $name, array $args = []): string
    {
        return url(app('route')->getRoute($name, $args));
    }
}
