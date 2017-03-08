<?php


namespace Drupal\stratigility_bridge;

use Interop\Http\ServerMiddleware\MiddlewareInterface;

/**
 * This wrapper class is needed because of https://www.drupal.org/node/2831831
 */
class MiddlewarePipe extends \Zend\Stratigility\MiddlewarePipe
{
    public function pipeMiddleware(MiddlewareInterface $middleware) {
        return $this->pipe($middleware);
    }
}