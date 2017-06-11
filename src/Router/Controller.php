<?php

namespace Router;

use Middleware\Middleware;

class Controller
{

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Назначение экшна для роутера
     * @param $action
     * @return Middleware
     */
    public static function bind($action)
    {
        return (new Middleware(function ($request, $response, $app) use ($action) {
            $controller = new static($app);
            $method = $action . 'Action';
            return $controller->$method($request, $response);
        }))->withBehavior(function ($callable, $next) {
            return function () use ($callable, $next) {
                $arguments = func_get_args();
                $arguments[] = $next;
                return $callable(...$arguments);
            };
        });
    }
}