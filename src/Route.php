<?php

declare(strict_types=1);

namespace LakshanJS\RouteKit;

use Closure;
use Exception;

/**
 * Class Route
 *
 * Manages the definition, grouping, and matching of routes.
 *
 * @property ?string $Controller The current controller name.
 * @property ?string $Method The current controller method.
 * @property Request $req The current HTTP request.
 * @property array $permissions Permissions assigned to routes.
 */
class Route
{
    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Named parameter patterns.
     *
     * @var array<string, string>
     */
    protected array $pattern = [
        '/*'            => '/(.*)',
        '/?'            => '/([^\/]+)',
        'int'           => '/([0-9]+)',
        'multiInt'      => '/([0-9,]+)',
        'title'         => '/([a-z_-]+)',
        'key'           => '/([a-z0-9_]+)',
        'multiKey'      => '/([a-z0-9_,]+)',
        'isoCode2'      => '/([a-z]{2})',
        'isoCode3'      => '/([a-z]{3})',
        'multiIsoCode2' => '/([a-z,]{2,})',
        'multiIsoCode3' => '/([a-z,]{3,})'
    ];

    /**
     * Registered routes, keyed by their names.
     *
     * @var array<string, string>
     */
    private array $routes = [];

    /** @var string */
    private string $group = '';

    /** @var string */
    private string $matchedPath = '';

    /** @var bool */
    private bool $matched = false;

    /** @var array<int, string> */
    private array $pramsGroup = [];

    /** @var array<int, string> */
    private array $matchedArgs = [];

    /** @var array<int, string> */
    private array $pattGroup = [];

    /** @var string */
    private string $fullArg = '';

    /** @var bool */
    private bool $isGroup = false;

    /** @var string */
    private string $groupAs = '';

    /** @var string */
    private string $currentGroupAs = '';

    /** @var array<int, mixed> */
    private array $currentGroup = [];

    /** @var array<int, string> */
    private array $prams = [];

    /** @var string */
    private string $currentUri = '';

    /** @var array<int, mixed> */
    private array $routeCallback = [];

    /** @var array<int, string> */
    private array $patt = [];

    /** @var bool */
    private bool $generated = false;

    /** 
     * The current controller name.
     *  
     * @var string|null 
     */
    public ?string $Controller = null;

    /**
     * The current method name.
     *
     * @var string|null
     */
    public ?string $Method = null;

    /**
     * Before route callbacks.
     *
     * @var array<int, array{uri: string, callback: callable}>
     */
    private array $before = [];

    /**
     * After route callbacks.
     *
     * @var array<int, array{uri: string, callback: callable}>
     */
    private array $after = [];

    /**
     * The current request.
     *
     * @var Request
     */
    public Request $req;

    /**
     * Permissions for routes.
     *
     * @var array<string, array>
     */
    public array $permissions = [];

    /**
     * Constructor.
     *
     * Initializes the Route with the given request and defines the URL constant if not already set.
     *
     * @param Request $req The current HTTP request.
     */
    public function __construct(Request $req)
    {
        $this->req = $req;
        if (!defined('URL')) {
            define('URL', $req->url);
        }
    }

    /**
     * Retrieves the singleton instance of the Route.
     *
     * @param Request $req The current HTTP request.
     * @return self The Route instance.
     */
    public static function instance(Request $req): self
    {
        if (static::$instance === null) {
            static::$instance = new static($req);
        }
        return static::$instance;
    }

    /**
     * Sets permission requirements for the current route.
     *
     * @param array $permissions The permissions to store.
     * @return self
     */
    public function permissions(array $permissions): self
    {
        $this->permissions[$this->currentUri] = $permissions;
        return $this;
    }

    /**
     * Gets permissions for a given URI.
     *
     * @param string $uri The URI to look up.
     * @return array|null Returns the permissions array or null if none are found.
     */
    public function getPermissionsForRoute(string $uri): ?array
    {
        $uri = $this->removeDuplSlash($uri);
        return $this->permissions[$uri] ?? null;
    }

    /**
     * Registers a route with a callback.
     *
     * @param array $method The HTTP methods allowed.
     * @param string|array $uri The URI pattern(s).
     * @param callable|string|array $callback The callback to invoke when the route matches.
     * @param array $options Additional options (e.g. 'ajaxOnly', 'continue').
     * @return self
     */
    public function route(array $method, string|array $uri, callable|string|array $callback, array $options = []): self
    {
        // If multiple URIs are provided, register each.
        if (is_array($uri)) {
            foreach ($uri as $u) {
                $this->route($method, $u, $callback, $options);
            }
            return $this;
        }

        // Merge default options.
        $options = array_merge(['ajaxOnly' => false, 'continue' => false], $options);

        // Normalize the URI if not root.
        if ($uri !== '/') {
            $uri = $this->removeDuplSlash($uri) . '/';
        }

        // Replace named parameters with regex patterns.
        $pattern = $this->namedParameters($uri);
        $this->currentUri = $pattern;

        if ($options['ajaxOnly'] === false || ($options['ajaxOnly'] && $this->req->ajax)) {
            if ($this->matched === false) {
                // Prepare the final regex pattern.
                $pattern = $this->prepare(
                    str_replace(
                        ['/?', '/*'],
                        [$this->pattern['/?'], $this->pattern['/*']],
                        $this->removeDuplSlash($this->group . $pattern)
                    )
                );

                // Check method and match the route.
                $methodMatch = count($method) > 0 ? in_array($this->req->method, $method) : true;
                if ($methodMatch && $this->matched($pattern)) {
                    if ($this->isGroup) {
                        $this->prams = array_merge($this->pramsGroup, $this->prams);
                    }
                    $this->req->args = $this->bindArgs($this->prams, $this->matchedArgs);
                    $this->matchedPath = $this->currentUri;
                    $this->routeCallback[] = $callback;

                    if ($options['continue']) {
                        $this->matched = false;
                    }
                }
            }
        }
        $this->_as($this->removeParameters($this->trimSlash($uri)));
        return $this;
    }

    /**
     * Groups a set of routes under a common prefix and options.
     *
     * @param string|array $group The group URI or an array of groups.
     * @param callable $callback A callback that defines the grouped routes.
     * @param array $options Group options, including 'as' and 'namespace'.
     * @return self
     */
    public function group(string|array $group, callable $callback, array $options = []): self
    {
        $options = array_merge([
            'as' => $group,
            'namespace' => $group
        ], $options);

        if (is_array($group)) {
            foreach ($group as $k => $p) {
                $this->group($p, $callback, [
                    'as' => is_array($options['as']) ? $options['as'][$k] : $options['as'],
                    'namespace' => is_array($options['namespace']) ? $options['namespace'][$k] : $options['namespace']
                ]);
            }
            return $this;
        }

        $this->setGroupAs((string)$options['as']);
        $group = $this->removeDuplSlash($group . '/');
        $group = $this->namedParameters($group, true);

        $this->matched($this->prepare($group, false), false);

        $this->currentGroup = [$group];
        $this->group .= $group;

        // Bind the callback to this Route instance.
        $callback = Closure::bind(Closure::fromCallable($callback), $this, self::class);
        call_user_func_array($callback, $this->bindArgs($this->pramsGroup, $this->matchedArgs));

        // Reset group-specific properties.
        $this->isGroup = false;
        $this->pramsGroup = [];
        $this->pattGroup = [];
        $this->group = substr($this->group, 0, -strlen($group));
        $this->setGroupAs(substr($this->getGroupAs(), 0, -(strlen((string)$options['as']) + 2)), true);

        return $this;
    }

    /**
     * Registers a resourceful route set for a controller.
     *
     * Generates routes for standard CRUD operations.
     *
     * @param string $uri The base URI for the resource.
     * @param string $controller The controller class name.
     * @param array $options Options such as 'ajaxOnly', 'idPattern', and 'multiIdPattern'.
     * @return self
     * @throws Exception If the controller class does not exist.
     */
    public function resource(string $uri, string $controller, array $options = []): self
    {
        $options = array_merge([
            'ajaxOnly'      => false,
            'idPattern'     => ':int',
            'multiIdPattern'=> ':multiInt'
        ], $options);

        if (class_exists($controller)) {
            $this->generated = false;
            $as = $this->trimc($uri);
            $as = ($this->getGroupAs() . '.') . $as;

            $withID = $uri . '/{id}' . $options['idPattern'];
            $deleteMulti = $uri . '/{id}' . $options['multiIdPattern'];

            $this->route(['GET'], $uri, [$controller, 'index'], $options)->_as($as);
            $this->route(['GET'], $uri . '/get', [$controller, 'get'], $options)->_as($as . '.get');
            $this->route(['GET'], $uri . '/create', [$controller, 'create'], $options)->_as($as . '.create');
            $this->route(['POST'], $uri, [$controller, 'store'], $options)->_as($as . '.store');
            $this->route(['GET'], $withID, [$controller, 'show'], $options)->_as($as . '.show');
            $this->route(['GET'], $withID . '/edit', [$controller, 'edit'], $options)->_as($as . '.edit');
            $this->route(['PUT', 'PATCH'], $withID, [$controller, 'update'], $options)->_as($as . '.update');
            $this->route(['DELETE'], $deleteMulti, [$controller, 'destroy'], $options)->_as($as . '.destroy');

            $this->route([], $uri . '/*', function (Request $req, $res) {
                http_response_code(404);
                $res->json(['error' => 'resource 404']);
            });
        } else {
            throw new Exception("Not found Controller {$controller} – try with namespace");
        }

        return $this;
    }

    /**
     * Splits a camelCase string into an array.
     *
     * @param string $str The camelCase string.
     * @return array<int, string> An array of the split parts.
     */
    public static function camelCase(string $str): array
    {
        return preg_split('/(?<=\\w)(?=[A-Z])/', $str) ?: [];
    }

    /**
     * Automatically registers routes for each public method in a controller.
     *
     * @param string $uri The base URI.
     * @param string $controller The controller class name.
     * @param array $options Additional options.
     * @return self
     * @throws Exception If the controller class does not exist.
     */
    public function controller(string $uri, string $controller, array $options = []): self
    {
        if (class_exists($controller)) {
            $methods = get_class_methods($controller);
            foreach ($methods as $v) {
                $split    = self::camelCase($v);
                $request  = strtoupper(array_shift($split));
                $fullUri  = $uri . '/' . implode('-', $split);

                if (isset($split[0]) && $split[0] === 'Index') {
                    $fullUri = $uri . '/';
                }

                $as      = $this->trimc(strtolower($fullUri));
                $as      = ($this->getGroupAs() . '.') . $as;
                $fullUri = [$fullUri . '/*', $fullUri];
                $call    = [$controller, $v];

                if (isset($split[0]) && $split[0] === 'Index') {
                    $fullUri = $uri;
                }
                $methodsArr = explode('_', $request);
                $this->route([$request], $fullUri, $call, $options)->_as($as);
            }
        } else {
            throw new Exception("Not found Controller {$controller} – try with namespace");
        }
        return $this;
    }

    /**
     * Binds route parameters with matched arguments.
     *
     * @param array<int, string> $pram Parameter names.
     * @param array<int, string> $args Matched argument values.
     * @return array<string, mixed> The combined associative array.
     */
    protected function bindArgs(array $pram, array $args): array
    {
        if (count($pram) === count($args)) {
            $newArgs = array_combine($pram, $args);
        } else {
            $newArgs = [];
            foreach ($pram as $p) {
                $newArgs[$p] = array_shift($args);
            }
            if (isset($args[0]) && count($args) === 1) {
                foreach (explode('/', '/' . $args[0]) as $arg) {
                    $newArgs[] = $arg;
                }
                $this->fullArg = $newArgs[0] = $args[0];
            }
            if (count($args)) {
                $newArgs = array_merge($newArgs, $args);
            }
        }
        return $newArgs;
    }

    /**
     * Replaces named parameters in the URI with regex patterns.
     *
     * @param string $uri The URI containing parameters.
     * @param bool $isGroup Whether the URI is part of a group.
     * @return string The URI with regex patterns.
     */
    protected function namedParameters(string $uri, bool $isGroup = false): string
    {
        $this->patt = [];
        $this->prams = [];

        return preg_replace_callback('/\/\{([a-z0-9-]+)\}\??(:\(?[^\/]+\)?)?/i', function ($m) use ($isGroup) {
            if (isset($m[2])) {
                $rep = substr($m[2], 1);
                $patt = $this->pattern[$rep] ?? '/' . $rep;
            } else {
                $patt = $this->pattern['/?'];
            }
            // Append '?' if the parameter is optional.
            if (strpos($m[0], '?') !== false) {
                $patt = str_replace('/(', '(/', $patt) . '?';
            }
            if ($isGroup) {
                $this->isGroup = true;
                $this->pramsGroup[] = $m[1];
                $this->pattGroup[] = $patt;
            } else {
                $this->prams[] = $m[1];
                $this->patt[] = $patt;
            }
            return $patt;
        }, trim($uri));
    }

    /**
     * Prepares a regex pattern for matching.
     *
     * @param string $patt The raw pattern.
     * @param bool $strict Whether to require a full string match.
     * @return string The final regex.
     */
    protected function prepare(string $patt, bool $strict = true): string
    {
        if (substr($patt, 0, 3) === '/(/') {
            $patt = substr($patt, 1);
        }
        return '~^' . $patt . ($strict ? '$' : '') . '~i';
    }

    /**
     * Checks if the current request path matches a given pattern.
     *
     * @param string $patt The regex pattern.
     * @param bool $call Whether to mark the route as matched.
     * @return bool True if matched, false otherwise.
     */
    protected function matched(string $patt, bool $call = true): bool
    {
        if (preg_match($patt, $this->req->path, $m)) {
            if ($call) {
                $this->matched = true;
            }
            array_shift($m);
            $this->matchedArgs = array_map([$this, 'trimSlash'], $m);
            return true;
        }
        return false;
    }

    /**
     * Removes duplicate slashes from a URI.
     *
     * @param string $uri The input URI.
     * @return string The normalized URI.
     */
    protected function removeDuplSlash(string $uri): string
    {
        return preg_replace('/\/+/', '/', '/' . $uri);
    }

    /**
     * Trims slashes from the beginning and end of a URI.
     *
     * @param string $uri The input URI.
     * @return string The trimmed URI.
     */
    protected function trimSlash(string $uri): string
    {
        return trim($uri, '/');
    }

    /**
     * A helper method to trim a URI (alias to trimSlash).
     *
     * @param string $str The input string.
     * @return string The trimmed string.
     */
    protected function trimc(string $str): string
    {
        return trim($str, '/');
    }

    /**
     * Adds custom patterns to the named parameters list.
     *
     * @param array<string, string> $patt Key-value pairs of pattern names and regex.
     * @return void
     */
    public function addPattern(array $patt): void
    {
        $this->pattern = array_merge($this->pattern, $patt);
    }

    /**
     * Sets a name for the current route.
     *
     * @param string $name The route name.
     * @return self
     * @throws Exception If the route name is already registered (commented out in original).
     */
    public function _as(string $name): self
    {
        if ($name === '') {
            return $this;
        }
        $name = rtrim($this->getGroupAs() . str_replace('/', '.', strtolower($name)), '.');

        // Merge group parameters with route parameters.
        $patt = $this->patt;
        $pram = $this->prams;
        if ($this->isGroup) {
            $patt = array_merge($this->pattGroup, $patt);
            if (count($patt) > count($pram)) {
                $pram = array_merge($this->pramsGroup, $pram);
            }
        }

        // Replace patterns with named parameters (e.g. :param).
        if (count($pram)) {
            foreach ($pram as $k => $v) {
                $pram[$k] = '/:' . $v;
            }
        }

        $replaced = $this->group . $this->currentUri;
        foreach ($patt as $k => $v) {
            $pos = strpos($replaced, $v);
            if ($pos !== false) {
                $replaced = substr_replace($replaced, $pram[$k], $pos, strlen($v));
            }
        }
        $this->routes[$name] = ltrim($this->removeDuplSlash(strtolower($replaced)), '/');
        return $this;
    }

    /**
     * Sets or updates the group name for route naming.
     *
     * @param string $as The group name.
     * @param bool $replace Whether to replace the current group name.
     * @return self
     */
    public function setGroupAs(string $as, bool $replace = false): self
    {
        $as = str_replace('/', '.', $this->trimSlash(strtolower($as)));
        $as = $this->removeParameters($as);
        $this->currentGroupAs = $as;
        if ($this->groupAs === '' || $as === '' || $replace) {
            $this->groupAs = $as;
        } else {
            $this->groupAs .= '.' . $as;
        }
        return $this;
    }

    /**
     * Retrieves the current group name for routes.
     *
     * @return string The group name (with trailing dot if not empty).
     */
    public function getGroupAs(): string
    {
        return ($this->groupAs === '') ? $this->groupAs : $this->groupAs . '.';
    }

    /**
     * Removes any parameter markers from a name.
     *
     * @param string $name The input name.
     * @return string The cleaned name.
     */
    protected function removeParameters(string $name): string
    {
        if (preg_match('/[{}?:()*]+/', $name)) {
            $name = '';
        }
        return $name;
    }

    /**
     * Retrieves the URI for a named route, replacing parameters with given values.
     *
     * @param string $name The route name.
     * @param array $args Values for any named parameters.
     * @return string|null The generated URI, or null if not found.
     */
    public function getRoute(string $name, array $args = []): ?string
    {
        $name = strtolower($name);
        if (isset($this->routes[$name])) {
            $route = $this->routes[$name];
            foreach ($args as $k => $v) {
                $route = str_replace(':' . $k, (string)$v, $route);
            }
            return $route;
        }
        return null;
    }

    /**
     * Returns all registered routes.
     *
     * @return array<string, string>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Registers a callback for all routes (before/after events).
     *
     * @param callable|string|array $callback The callback.
     * @param string $event The event type ('before' or other for after).
     * @return self
     */
    public function _use(callable|string|array $callback, string $event = 'before'): self
    {
        return (strtolower($event) === 'before')
            ? $this->before('/*', $callback)
            : $this->after('/*', $callback);
    }

    /**
     * Registers a callback to run before a route.
     *
     * @param string $uri The URI pattern.
     * @param callable $callback The callback.
     * @return self
     */
    public function before(string $uri, callable $callback): self
    {
        $this->before[] = [
            'uri'      => $uri,
            'callback' => $callback
        ];
        return $this;
    }

    /**
     * Registers a callback to run after a route.
     *
     * @param string $uri The URI pattern.
     * @param callable $callback The callback.
     * @return self
     */
    public function after(string $uri, callable $callback): self
    {
        $this->after[] = [
            'uri'      => $uri,
            'callback' => $callback
        ];
        return $this;
    }

    /**
     * Emits (runs) callbacks for a set of events.
     *
     * @param array<int, array{uri: string, callback: callable}> $events The event callbacks.
     * @return void
     */
    protected function emit(array $events): void
    {
        $continue = true;
        foreach ($events as $cb) {
            if ($continue !== false) {
                $uri = $cb['uri'];
                $except = false;
                if (strpos($uri, '/*!') !== false) {
                    $uri = substr($uri, 3);
                    $except = true;
                }
                $list = array_map('trim', explode('|', strtolower($uri)));
                foreach ($list as $item) {
                    $item = $this->removeDuplSlash($item);
                    if ($except) {
                        if ($this->matched($this->prepare($item, false), false) === false) {
                            $continue = $this->callback($cb['callback'], $this->req->args);
                            break;
                        }
                    } elseif ($list[0] === '/*' || $this->matched($this->prepare($item, false), false) !== false) {
                        $continue = $this->callback($cb['callback'], $this->req->args);
                        break;
                    }
                }
            }
        }
    }

    /**
     * Ends the route processing by executing matched callbacks.
     *
     * If no route was matched and the method is not OPTIONS, a 404 response is sent.
     *
     * @return void
     */
    public function end(): void
    {
        ob_start();
        if ($this->matched && count($this->routeCallback)) {
            if (count($this->before)) {
                $this->emit($this->before);
            }

            // Store permissions for the current route in the request.
            $currentUri = $this->req->path;
            $permissions = $this->getPermissionsForRoute($currentUri);
            $this->req->permissions = $permissions;

            foreach ($this->routeCallback as $call) {
                $this->callback($call, $this->req->args);
            }
            if (count($this->after)) {
                $this->emit($this->after);
            }
        } elseif ($this->req->method !== 'OPTIONS') {
            http_response_code(404);
            print('<h1>404 Not Found</h1>');
        }
        if (ob_get_length()) {
            ob_end_flush();
        }
        exit;
    }

    /**
     * Invokes the given callback with the provided arguments.
     *
     * Supports various callback types:
     * - A Closure (optionally bound).
     * - A string with the format "Controller@method".
     * - An array callback where the controller is instantiated if needed.
     *
     * @param mixed $callback The callback to invoke.
     * @param array $args The arguments to pass.
     * @return mixed The result of the callback, or false if not callable.
     * @throws Exception If a string callback cannot be resolved.
     */
    public function callback(mixed $callback, array $args = []): mixed
    {
        if (isset($callback)) {
            if (is_callable($callback) && $callback instanceof Closure) {
                // (Optional) You can bind additional data here if needed.
                $o = new \stdClass();
                $o->args = $args;
                $o->app = App::instance();
            } elseif (is_string($callback) && strpos($callback, '@') !== false) {
                $fixcallback = explode('@', $callback, 2);
                $this->Controller = $fixcallback[0];
                $action = $fixcallback[1] ?? 'index';
                $constructed = [new $fixcallback[0], $action];
                if (is_callable($constructed)) {
                    $this->Method = $action;
                    $callback = $constructed;
                } else {
                    throw new Exception("Callable error on {$fixcallback[0]}->{$action}!");
                }
            }

            if (is_array($callback) && !is_object($callback[0])) {
                $callback[0] = new $callback[0];
            }

            if (isset($args[0]) && $args[0] === $this->fullArg) {
                array_shift($args);
            }
            return call_user_func_array($callback, $args);
        }
        return false;
    }

    /**
     * Magic method for dynamic method calls.
     *
     * Allows calling shortcut methods such as:
     * - AS (alias for _as)
     * - USE (alias for _use)
     * - ANY (alias for route with any HTTP method)
     * - Or dynamically calling methods based on HTTP verbs (e.g., get, post).
     *
     * @param string $method The method name.
     * @param array $args The method arguments.
     * @return mixed
     */
    public function __call(string $method, array $args): mixed
    {
        switch (strtoupper($method)) {
            case 'AS':
                return call_user_func_array([$this, '_as'], $args);
            case 'USE':
                return call_user_func_array([$this, '_use'], $args);
            case 'ANY':
                array_unshift($args, []);
                return call_user_func_array([$this, 'route'], $args);
        }
        // Support dynamic methods based on HTTP verbs.
        $methods = explode('_', $method);
        $exists = [];
        foreach ($methods as $v) {
            $v = strtoupper($v);
            if (in_array($v, ['POST', 'GET', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) {
                $exists[] = $v;
            }
        }
        if (count($exists)) {
            array_unshift($args, $exists);
            return call_user_func_array([$this, 'route'], $args);
        }
        return (is_string($method) && isset($this->{$method}) && is_callable($this->{$method}))
            ? call_user_func_array($this->{$method}, $args)
            : null;
    }

    /**
     * Magic method for setting dynamic properties.
     *
     * If the provided value is a Closure, it is automatically bound to this instance.
     *
     * @param string $k The property name.
     * @param mixed $v The value to set.
     * @return void
     */
    public function __set(string $k, mixed $v): void
    {
        $this->{$k} = $v instanceof Closure ? $v->bindTo($this) : $v;
    }
}
