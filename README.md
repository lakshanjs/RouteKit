# RouteKit

RouteKit is a lightweight routing and request handling library for PHP. The
package is inspired by [`nezamy/route`](https://github.com/nezamy/route) and
provides a small but expressive API for building web applications.

## Installation

```bash
composer require lakshanjs/routekit
```

RouteKit requires PHP 8.1 or newer.

## Quick start

Create an `index.php` file and bootstrap the application:

```php
<?php

use LakshanJS\RouteKit\App;
use LakshanJS\RouteKit\Route;
use LakshanJS\RouteKit\Request;

require __DIR__ . '/vendor/autoload.php';

$app = App::instance();
$app->request = Request::instance();
$app->route = Route::instance($app->request);

$route = $app->route;

$route->any('/', function () {
    echo 'Hello World';
});

$route->end();
```

If you are using Apache, place an `.htaccess` file next to `index.php` so all
requests are directed to the entry script.

## Helper functions

Three global helper functions are provided for convenience:

```php
app();            // Retrieve the App singleton or a property on it
url('docs');      // Base URL with an optional path
route('home');    // URL for a named route
```

These helpers are defined in `src/functions.php` and are automatically loaded by
Composer.

## Defining routes

Routes are registered by calling HTTP verb methods on the `Route` instance. Each
method accepts a URI pattern and a callback.

```php
$route->get('/', fn () => echo 'GET home');
$route->post('/contact', fn () => echo 'POST contact');
$route->put('/user/{id}', fn ($id) => echo "Update $id");
$route->delete('/user/{id}', fn ($id) => echo "Delete $id");

// Match any method
$route->any('/status', fn () => echo 'OK');

// Multiple methods at once
$route->get_post('/form', fn () => echo 'GET or POST form');
```

### Multiple URIs

An array of URIs can be supplied to match several paths with one callback:

```php
$route->get(['/', '/home', '/index'], fn () => echo 'Homepage');
```

### Route parameters

Routes may contain parameters that are passed to the callback in the order they
appear. The `/?` placeholder matches a single path segment:

```php
$route->get('/post/?', function ($id) {
    echo "Post ID: $id";
});

$route->get('/post/?/?', function ($id, $title) {
    echo "Post $id with title $title";
});
```

#### Named parameters

Parameters can be named using `{name}` which allows accessing them by variable
name or from the `$this` context inside the callback:

```php
$route->get('/{username}/{page}', function ($username, $page) {
    echo "User $username on $page";
    // or
    echo $this['username'];
});
```

#### Regular expressions

Custom patterns may be supplied after the parameter name. Built‑in shortcuts are
available for common patterns such as `:int`, `:title` and more:

```php
$route->get('/{username}:([0-9a-z_.-]+)/post/{id}:int',
    function ($username, $id) {
        echo "Author $username, post $id";
    }
);
```

#### Optional parameters

Append a `?` to make a parameter optional:

```php
$route->get('/post/{title}?:title/{date}?', function ($title, $date) {
    echo $title ? "<h1>$title</h1>" : '<h1>Posts</h1>';
    if ($date) {
        echo "<small>Published $date</small>";
    }
});
```

### Naming routes and generating URLs

Use `as()` to assign a name to a route. The `route()` helper or
`Route::getRoute()` can then generate URLs for that name:

```php
$route->get('/', fn () => echo 'Home')->as('home');
$route->get('/about', fn () => echo 'About')->as('about');

echo route('about');        // outputs full URL to /about
echo $route->getRoute('home'); // returns the path '/'
```

### Groups

Routes can be grouped under a common prefix. Groups may be nested and can also
specify a name prefix via the `as` option:

```php
$route->group('/admin', function () {
    $this->get('/', fn () => echo 'Admin dashboard');
    $this->group('/users', function () {
        $this->get('/', fn () => echo 'User list');
    });
});
```

### Controllers and resources

RouteKit can automatically build routes from controller classes.

```php
$route->controller('/account', App\Controller\AccountController::class);
$route->resource('/posts', App\Controller\PostController::class);
```

`controller()` registers a route for each public method using the HTTP verb
prefix (e.g. `getIndex`, `postUpdate`). `resource()` creates standard CRUD routes
(`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`).

### Middleware

Middleware can run before or after matched routes. Use `use()` to register a
global middleware, or `before()` / `after()` for specific URIs:

```php
$route->use(fn () => echo "Before every route");

$route->before('/admin/*', fn () => echo 'Checking admin access...');
$route->after('/admin/*', fn () => echo 'Admin route completed');
```

### Permissions

You may attach arbitrary permission data to a route. The information is stored on
the `Request` object for the matched route:

```php
$route->get('/dashboard', function () {
    // ...
})->permissions(['admin']);

$route->end();

// Later inside a callback
$perms = app('request')->permissions; // ['admin']
```

## Request helper

The `Request` singleton exposes information about the current HTTP request:

```php
$req = app('request');
$req->path;       // Normalized request path
$req->url;        // Base URL
$req->curl;       // Full current URL
$req->method;     // HTTP method
$req->headers;    // Array of headers
$req->query;      // $_GET parameters
$req->body;       // Parsed request body
$req->files;      // Uploaded files
$req->cookies;    // $_COOKIE values
$req->ajax;       // Whether the request was made via AJAX

// Utility methods
$req->ip();       // Client IP address
$req->browser();  // Browser name
$req->platform(); // Operating system
$req->isMobile(); // True if user agent is a mobile device
```

## The App container

`App` acts as a simple container. You may register values, services or
closures directly on the instance:

```php
app()->version = '1.0';
echo app('version'); // 1.0

app()->adder = function ($a, $b) {
    return $a + $b;
};
echo app()->adder(2, 3); // 5
```

## License

RouteKit is open-sourced software licensed under the [MIT license](LICENSE).

