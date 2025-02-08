# RouteKit

RouteKit is a lightweight and flexible PHP routing package that allows you to handle URL routing effortlessly in your applications.

RouteKit is based on nezamy/route:1.2.2, enabling you to quickly and easily build RESTful web applications.

## Installation

```terminal
$ composer require lakshanjs/routekit
```

Route requires PHP 8.2 or newer.

## Usage

Create an `index.php` file with the following contents:

```php
<?php

define('BASE_PATH', __DIR__ . DS, TRUE);

require BASE_PATH.'vendor/autoload.php';

use LakshanJS\RouteKit\App;
use LakshanJS\RouteKit\Route;
use LakshanJS\RouteKit\Request;

$app = App::instance();
$app->request = Request::instance();
$app->route = Route::instance($app->request);

$route = $app->route;

$route->any('/', function() {
    echo 'Hello World';
});

$route->end();
```

If using Apache, make sure the `.htaccess` file exists beside `index.php`.

## How it works

Routing is done by matching a URL pattern with a callback function.

```php
$route->any('/', function(){
    echo 'Hello World';
});

$route->any('/about', function(){
    echo 'About';
});
```

### Example Outputs

```
http://yoursite.com/ -> Hello World
http://yoursite.com/about -> About
```

## Callback Functions

The callback can be any object that is callable. You can use a regular function:

```php
function pages(){
    echo 'Page Content';
}
$route->get('/', 'pages');
```

Or a class method:

```php
class home {
    function pages(){
        echo 'Home page Content';
    }
}
$route->get('/', ['home', 'pages']);
// OR
$home = new home;
$route->get('/', [$home, 'pages']);
// OR
$route->get('/', 'home@pages');
```

## Method Routing

```php
$route->any('/', function(){
    // Any method requests
});

$route->get('/', function(){
    // Only GET requests
});

$route->post('/', function(){
    // Only POST requests
});

$route->put('/', function(){
    // Only PUT requests
});

$route->patch('/', function(){
    // Only PATCH requests
});

$route->delete('/', function(){
    // Only DELETE requests
});

// Multiple methods
$route->get_post('/', function(){
    // Only GET and POST request
});
```

## Multiple Routing (All in one)

```php
$route->get(['/', 'index', 'home'], function(){
    // Will match 3 pages in one
});
```

## Parameters

This example will match any page name:

```php
$route->get('/?', function($page){
    echo "you are in $page";
});
```

Match anything after `post/`:

```php
$route->get('/post/?', function($id){
    echo "post id $id";
});
```

More than one parameter:

```php
$route->get('/post/?/?', function($id, $title){
    echo "post id $id and title $title";
});
```

## Named Parameters

```php
$route->get('/{username}/{page}', function($username, $page){
    echo "Username $username and Page $page <br>";
    // OR
    echo "Username {$this['username']} and Page {$this['page']}";
});
```

## Regular Expressions

```php
$route->get('/{username}:([0-9a-z_.-]+)/post/{id}:([0-9]+)',
function($username, $id){
    echo "author $username post id $id";
});
```

## Optional Parameters

```php
$route->get('/post/{title}?:title/{date}?', function($title, $date){
    if($title){
        echo "<h1>$title</h1>";
    }else{
        echo "<h1>Posts List</h1>";
    }
    if($date){
        echo "<small>Published $date</small>";
    }
});
```

## Groups

```php
$route->group('/admin', function(){
    $this->get('/', function(){
        echo 'welcome to admin panel';
    });
    $this->get('/settings', function(){
        echo 'list of settings';
    });
    $this->group('/users', function(){
        $this->get('/', function(){
            echo 'list of users';
        });
        $this->get('/add', function(){
            echo 'add new user';
        });
    });
    $this->any('/*', function(){
        pre("Page ( {$this->app->request->path} ) Not Found", 6);
    });
});
```

## Middleware

```php
$route->use(function(){
    pre('Do something before all routes', 3);
});

$route->before('/', function(){
    pre('Do something before all routes', 4);
});

$route->after('/admin|home', function(){
    pre('Do something after admin and home only', 6);
});
```

## Controllers and Resources

```php
$route->controller('/controller', 'App\Controller\testController');
$route->resource('/resource', 'App\Controller\testResource');
```

## Route Name

```php
$route->any('/', function(){
    echo route('about');
})->as('home');

$route->any('/about', function(){
    echo route('home');
})->as('about');
```

## Shortcut Functions

```php
app();  // app instance
route();// shortcut for Route::getRoute()
url();  // get domain url
```

## Registering

```php
// Variables
app()->x = 'something';
echo app()->x; // something
// OR
echo app('x'); // something

// Functions
app()->calc = function($a, $b){
    echo $a + $b;
}
echo app()->calc(5, 4); //9

// Classes
class myClass {

}
app()->myClass = new myClass;
pre( app('myClass') );
```

## Request

```php
app('request')->server; //$_SERVER
app('request')->path; // uri path
app('request')->hostname;
app('request')->servername;
app('request')->port;
app('request')->protocol; // http or https
app('request')->url; // domain url
app('request')->curl; // current url
app('request')->extension; // get url extension
app('request')->headers; // all http headers
app('request')->method; // Request method
app('request')->query; // $_GET
app('request')->body; // $_POST and php://input
app('request')->args; // all route args
app('request')->files; // $_FILES
app('request')->cookies; // $_COOKIE
app('request')->ajax; // check if request is sent by ajax or not
app('request')->ip(); // get client IP
app('request')->browser(); // get client browser
app('request')->platform(); // get client platform
app('request')->isMobile(); // check if client opened from mobile or tablet

// You can append vars functions classes to request class
app('request')->addsomething = function(){
    return 'something';
};
```

## Autoload

```php
<?php
namespace App;
class homeController
{
    public function index()
    {
        # code...
    }
}
```

```php
// Call the class with a namespace
$route->get('/', 'App\homeController@index');
```