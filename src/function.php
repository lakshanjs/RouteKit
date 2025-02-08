<?php

//=======[  instance class App ]=====================
function app($c = null) {
    $app = LakshanJS\RouteKit\App::instance();
    if ($c) {
        return $app->$c;
    }
    return $app;
}
//=======[ get domain url ]=========================
function url($path = null) {
	if($path){
		$path = rtrim(trim($path), '/');
	}
    return URL . $path ;
}
//=======[ get route url by name  ]=================
function route($name, array $args = []) {
    return url(app('route')->getRoute($name, $args));
}
