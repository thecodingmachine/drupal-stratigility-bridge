<?php


namespace Drupal\stratigility_bridge;

use Interop\Http\Middleware\ServerMiddlewareInterface;

/**
 * This wrapper class is needed because of https://www.drupal.org/node/2831831
 */
class MiddlewarePipe extends \Zend\Stratigility\MiddlewarePipe
{
    public function pipeMiddleware(ServerMiddlewareInterface $middleware) {
        return $this->pipe($middleware);
    }
}