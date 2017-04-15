<?php

namespace Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Router
{

    protected $suffix = '';
    protected $routs = [];
    protected $preRouts = [];

    public function __construct(array $config)
    {
        $this->routs = isset($config['routs']) ? $config['routs'] : [];
        $this->preRouts = isset($config['preRouts']) ? $config['preRouts'] : [];
        $this->suffix = isset($config['suffix']) ? $config['suffix'] : '(.html|\/)*?';
    }

    /**
     * Установка атрибутов запроса
     * @param ServerRequestInterface $request
     * @param array $attributes
     * @return ServerRequestInterface
     */
    protected function addAttributes(ServerRequestInterface $request, array $attributes)
    {
        foreach ($attributes as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }
        return $request;
    }

    /**
     * Парсинг маршрутов
     * @param $routs
     * @param string $group
     * @param array $result
     * @return array
     */
    protected function parseRouts($routs, $group = '', &$result = [])
    {
        foreach ($routs as $pattern => $handler) {
            if (is_array($handler) and !is_callable($handler)) {
                $this->parseRouts(
                    $handler,
                    $group . (is_string($pattern) ? $pattern : ''),
                    $result);
            } elseif (is_callable($handler)) {
                $pattern_array = explode('/', $pattern);
                $method = array_shift($pattern_array);
                $pattern_chunk = '/' . ltrim(implode('/', $pattern_array), '/');
                $result[$method . $group . $pattern_chunk] = $handler;
            }
        }
        return $result;
    }

    /**
     * Запуск пред-маршрута
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param \ArrayAccess $dependencyContainer
     * @return ResponseInterface
     * @throws Exception
     */
    protected function runPreRoute(
        ServerRequestInterface $request,
        ResponseInterface $response,
        \ArrayAccess $dependencyContainer)
    {
        $queryString = $request->getMethod() . $request->getUri()->getPath();
        $shortQueryString = $request->getUri()->getPath();
        $routs = $this->parseRouts($this->preRouts);
        foreach ($routs as $pattern => $handler) {
            if (preg_match(
                    '#^' . rtrim($pattern, '/') . '(.*?)$#is',
                    $queryString
                ) or
                preg_match(
                    '#^' . rtrim($pattern, '/') . '(.*?)$#is',
                    $shortQueryString
                )
            ) {
                $response = $handler($request, $response, $dependencyContainer);
                if (!$response instanceof ResponseInterface) {
                    throw new Exception('Пред-маршрут не возвращает необходимый интерфейс');
                }
                return $response;
            }
        }
        return $response;
    }

    /**
     * Запуск маршрутизатора
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param \ArrayAccess $dependencyContainer
     * @return ResponseInterface
     * @throws Exception
     */
    protected function run(
        ServerRequestInterface $request,
        ResponseInterface $response,
        \ArrayAccess $dependencyContainer)
    {
        $queryString = $request->getMethod() . $request->getUri()->getPath();
        $routs = $this->parseRouts($this->routs);
        foreach ($routs as $pattern => $handler) {
            if (preg_match(
                    '#^' . rtrim($pattern, '/') . $this->suffix . '$#is',
                    $queryString,
                    $matches
                )
            ) {
                array_shift($matches);
                $request = $this->addAttributes($request, $matches);
                $response = $this->runPreRoute($request, $response, $dependencyContainer);
                $response = $handler($request, $response, $dependencyContainer);
                if (!$response instanceof ResponseInterface) {
                    throw new Exception('Маршрут не возвращает необходимый интерфейс');
                }
                return $response;
            }
        }
        return $response->withStatus(404);
    }

}