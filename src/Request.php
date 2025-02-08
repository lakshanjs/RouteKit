<?php

declare(strict_types=1);

namespace LakshanJS\RouteKit;

/**
 * Class Request
 *
 * Handles HTTP request details and provides methods to retrieve client information.
 *
 * @property array  $server    Server variables.
 * @property string $protocol   HTTP protocol (http/https).
 * @property string $hostname   Hostname without port.
 * @property array  $query      Query parameters from GET.
 * @property string $url        Base URL.
 * @property string $servername Server name.
 * @property array  $body       Request body parameters.
 * @property array  $headers    HTTP request headers.
 * @property string $path       Processed request path.
 * @property bool   $secure     Whether the connection is secure.
 * @property bool   $ajax       Whether the request is an AJAX call.
 * @property array  $args       Additional arguments.
 * @property string $method     HTTP method (GET, POST, etc.).
 * @property string|null $port  Server port.
 * @property string $fullurl    Full URL including query parameters.
 * @property string $curl       Combined URL and path.
 * @property string $extension  File extension of the request path.
 * @property array  $files      Uploaded files.
 * @property array  $cookies    Request cookies.
 */
#[\AllowDynamicProperties]
class Request
{
    /**
     * The singleton instance of the Request.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    public array $server;
    public string $protocol;
    public string $hostname;
    public array $query;
    public string $url;
    public string $servername;
    public array $body;
    public array $headers;
    public string $path;
    public bool $secure;
    public bool $ajax;
    public array $args;
    public string $method;
    public ?string $port;
    public string $fullurl;
    public string $curl;
    public string $extension;
    public array $files;
    public array $cookies;

    /**
     * Request constructor.
     *
     * Initializes request-related properties based on PHP superglobals.
     */
    public function __construct()
    {
        $this->server = $_SERVER;

        // Parse the request URI to determine the path.
        $uri    = parse_url($this->server['REQUEST_URI'], PHP_URL_PATH);
        $script = $_SERVER['SCRIPT_NAME'];
        $parent = dirname($script);

        // Adjust the path based on the script location.
        if (stripos($uri, $script) !== false) {
            $this->path = substr($uri, strlen($script));
        } elseif (stripos($uri, $parent) !== false) {
            $this->path = substr($uri, strlen($parent));
        } else {
            $this->path = $uri;
        }
        $this->path = preg_replace('/\/+/', '/', '/' . trim(urldecode($this->path), '/') . '/');

        // Remove the port (if any) from HTTP_HOST.
        $this->hostname = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
        $this->servername = empty($_SERVER['SERVER_NAME']) ? $this->hostname : $_SERVER['SERVER_NAME'];

        // Determine if the connection is secure.
        $this->secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $this->port = isset($_SERVER['SERVER_PORT']) ? (string) $_SERVER['SERVER_PORT'] : null;
        $this->protocol = $this->secure ? 'https' : 'http';
        $this->url = strtolower($this->protocol . '://' . $this->servername);

        // Build the full URL by removing the path from the REQUEST_URI.
        $this->fullurl = strtolower($this->protocol . '://' . $this->servername)
            . str_replace($this->path, '', $this->server['REQUEST_URI']);

        // Adjust URL for localhost.
        if ($this->servername === 'localhost') {
            $this->url = strtolower(
                $this->protocol . '://' . $this->servername . "/"
                . str_replace($this->path, '', $this->server['REQUEST_URI'])
            );
        }

        // Combine the base URL with the path.
        $this->curl = rtrim($this->url, '/') . $this->path;
        $this->extension = (string) pathinfo($this->path, PATHINFO_EXTENSION);

        // Extract HTTP headers from server variables.
        $this->headers = (function (): array {
            $result = [];
            foreach ($_SERVER as $key => $value) {
                // Check if the key starts with "http_"
                if (stripos($key, 'http_') === 0) {
                    $result[strtolower(substr($key, 5))] = $value;
                }
            }
            return $result;
        })();

        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->query = $_GET;
        $this->args  = [];

        // Sanitize query parameters.
        foreach ($this->query as $key => $value) {
            $this->query[$key] = preg_replace('/\/+/', '/', str_replace(['..', './'], ['', '/'], $value));
        }

        // Read input based on content type.
        if (isset($this->headers['content_type']) && $this->headers['content_type'] === 'application/x-www-form-urlencoded') {
            parse_str(file_get_contents('php://input'), $input);
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
        }

        $this->body = is_array($input) ? $input : [];
        $this->body = array_merge($this->body, $_POST);
        $this->files = $_FILES ?? [];
        $this->cookies = $_COOKIE;
        $xRequestedWith = $this->headers['x_requested_with'] ?? false;
        $this->ajax = ($xRequestedWith === 'XMLHttpRequest');
    }

    /**
     * Retrieves the singleton instance of the Request.
     *
     * @return static The Request instance.
     */
    public static function instance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Returns the client's IP address.
     *
     * Checks various server variables to determine the client's IP.
     *
     * @return string The client's IP address, or 'unknown' if not valid.
     */
    public function ip(): string
    {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = getenv('REMOTE_ADDR') ?: 'unknown';
        }

        // If multiple IP addresses are returned, use the first one.
        if (strpos($ip, ',') !== false) {
            $ip = explode(',', $ip)[0];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }

    /**
     * Returns the client's browser name.
     *
     * @return string The browser name, or 'unknown' if not detected.
     */
    public function browser(): string
    {
        $userAgent = $this->server['HTTP_USER_AGENT'] ?? '';
        if (stripos($userAgent, 'Opera') !== false || stripos($userAgent, 'OPR/') !== false) {
            return 'Opera';
        } elseif (stripos($userAgent, 'Edge') !== false) {
            return 'Edge';
        } elseif (stripos($userAgent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (stripos($userAgent, 'Safari') !== false) {
            return 'Safari';
        } elseif (stripos($userAgent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (stripos($userAgent, 'MSIE') !== false || stripos($userAgent, 'Trident/7') !== false) {
            return 'Internet Explorer';
        }
        return 'unknown';
    }

    /**
     * Returns the client's platform (operating system).
     *
     * @return string The platform name, or 'unknown' if not detected.
     */
    public function platform(): string
    {
        $userAgent = $this->server['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/linux/i', $userAgent)) {
            return 'linux';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            return 'mac';
        } elseif (preg_match('/windows|win32/i', $userAgent)) {
            return 'windows';
        }
        return 'unknown';
    }

    /**
     * Determines if the request comes from a mobile device.
     *
     * Checks the User Agent string for patterns commonly associated with mobile devices.
     *
     * @return bool True if the request is from a mobile device, false otherwise.
     */
    public function isMobile(): bool
    {
        $mobileUserAgents = [
            '/iphone/i'    => 'iPhone',
            '/ipod/i'      => 'iPod',
            '/ipad/i'      => 'iPad',
            '/android/i'   => 'Android',
            '/blackberry/i'=> 'BlackBerry',
            '/webos/i'     => 'Mobile',
        ];

        $userAgent = $this->server['HTTP_USER_AGENT'] ?? '';
        foreach ($mobileUserAgents as $pattern => $device) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handles dynamic method calls.
     *
     * If a property with the specified name exists and is callable, it invokes it.
     *
     * @param string $method The method name.
     * @param array  $args   The arguments for the method call.
     *
     * @return mixed The result of the dynamic method call, or null if not callable.
     */
    public function __call(string $method, array $args): mixed
    {
        if (isset($this->{$method}) && is_callable($this->{$method})) {
            return call_user_func_array($this->{$method}, $args);
        }
        return null;
    }

    /**
     * Sets dynamic properties.
     *
     * If the provided value is a Closure, it binds it to the current instance.
     *
     * @param string $property The property name.
     * @param mixed  $value    The value to set.
     *
     * @return void
     */
    public function __set(string $property, mixed $value): void
    {
        $this->{$property} = $value instanceof \Closure ? $value->bindTo($this) : $value;
    }
}
