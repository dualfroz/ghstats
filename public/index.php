<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Ghapi\Config;
use Ghapi\Http\App;
use Ghapi\Http\Request;

$request = Request::fromGlobals();
$app = new App(Config::load(), dirname(__DIR__));
$app->handle($request)->send($request->method);
