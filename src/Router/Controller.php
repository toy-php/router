<?php

namespace Router;

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
     * @return \Closure
     */
    public static function bind($action)
    {
        return function ($request, $response, $app) use ($action) {
            $class = new static($app);
            $method = $action . 'Action';
            return $class->$method($request, $response);
        };
    }
}