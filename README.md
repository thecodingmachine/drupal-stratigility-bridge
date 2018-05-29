# thecodingmachine/drupal-stratigility-bridge

Bridges between [Drupal 8](https://www.drupal.org/8) and PSR-15 middleware modules through the use of [Zend Framework's Stratigility](https://zendframework.github.io/zend-stratigility/).


[![Latest Stable Version](https://poser.pugx.org/thecodingmachine/drupal-stratigility-bridge/v/stable)](https://packagist.org/packages/thecodingmachine/drupal-stratigility-bridge)
[![Total Downloads](https://poser.pugx.org/thecodingmachine/drupal-stratigility-bridge/downloads)](https://packagist.org/packages/thecodingmachine/drupal-stratigility-bridge)
[![Latest Unstable Version](https://poser.pugx.org/thecodingmachine/drupal-stratigility-bridge/v/unstable)](https://packagist.org/packages/thecodingmachine/drupal-stratigility-bridge)
[![License](https://poser.pugx.org/thecodingmachine/drupal-stratigility-bridge/license)](https://packagist.org/packages/thecodingmachine/drupal-stratigility-bridge)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/thecodingmachine/drupal-stratigility-bridge/badges/quality-score.png?b=0.4)](https://scrutinizer-ci.com/g/thecodingmachine/drupal-stratigility-bridge/?branch=0.4)

> This bridge is currently based on psr15.

## Installation

This project is a Drupal 8 module.

The recommended way to install drupal-stratigility-bridge is through [Composer](http://getcomposer.org/):

```sh
composer require thecodingmachine/drupal-stratigility-bridge
```

## Usage

This module will fill the Drupal container with a new `stratigility_pipe` entry (it is a `Zend\Stratigility\MiddlewarePipe`).

You can extend this entry in your own module to register a new middleware.

In Drupal services, you can simply add the `http_interop_middleware` to your middleware service. This will automatically register the middleware in Stratigility's pipe.

So your `MYMODULE.services.yml` will certainly look like this:

```yml
services:
  my_middleware:
    class:      Acme\MyMiddleware
    tags:
      - { name: http_interop_middleware }
```

## Using Drupal render arrays in PSR-15 middlewares

This module comes with a service that let's you [render Drupal "arrays"](https://www.drupal.org/docs/8/api/render-api/render-arrays).

To do so, simply inject the `drupal_array_render_caller` service in your controller and call the `getResponse` method.

Below is a sample middleware that returns a "Hello world!" Drupal page when the "/foo" page is hit:

```php
class HelloWorldMiddleware implements MiddlewareInterface
{
    /**
     * @var DrupalArrayRenderCaller
     */
    private $arrayRenderCaller;

    public function __construct(DrupalArrayRenderCaller $arrayRenderCaller)
    {
        $this->arrayRenderCaller = $arrayRenderCaller;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        if (trim($request->getUri()->getPath(), '/') === 'foo') {
            // Let's render a drupal page
            return $this->arrayRenderCaller->getResponse(array(
                '#type' => 'markup',
                '#title' => "My title",
                '#markup' => t('Hello world')
            ));
        } else {
            return $delegate->process($request);
        }
    }
}
```

## How it works

This module listens to the `KernelEvents::REQUEST` event that is triggered at each request by the Drupal kernel.

The Symfony request is converted into a PSR-7 request and then is sent to Stratigility's middleware pipe.

If a middleware returns a PSR-7 response, this response is sent back to the user.
If all middleware are calling the "next" middleware, the final middleware is a dummy middleware that returns a "418 I'm a teapot" response.
This response is interpreted by the module as a "I don't care" response, and the rendering is passed to Drupal that will continue its rendering. 

## Limitations

Stratigility middlewares provided are not "really" middlewares as they cannot modify the request or the response from Drupal.
They run "before" Drupal, in a separate middleware stack. This is still very useful if you want to add your own router in front of Drupal!

Currently, the PSR-7 request is never converted back to a Symfony request. This means that any modification done on the PSR-7 request by a middleware will be ignored by Drupal.
Due to the way Drupal is built, it is also impossible to catch the Drupal response in a PSR-15 middleware to modify it.
