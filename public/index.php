<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Request;
use App\Core\Session;

Session::start();

/** @var \App\Core\Router $router */
$router = require dirname(__DIR__) . '/src/routes.php';
$router->dispatch(Request::method(), Request::path());
