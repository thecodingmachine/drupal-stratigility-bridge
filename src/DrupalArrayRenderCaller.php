<?php
namespace Drupal\stratigility_bridge;

use Drupal\Core\Render\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Route;
use Zend\Diactoros\Response\TextResponse;

/**
 * Class in charge of calling the array renderer API from within PSR-15 middlewares.
 *
 * If the renderArray method is called, this will completely bypass the PSR-7 response sent and use Drupal render pipeline instead.
 */
class DrupalArrayRenderCaller
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;
    /**
     * @var RequestStack
     */
    private $requestStack;
    /**
     * @var HttpKernelInterface
     */
    private $httpKernel;
    /**
     * @var HtmlResponseStack
     */
    private $responseStack;

    /**
     * @param EventDispatcherInterface $dispatcher
     * @param RequestStack $requestStack
     * @param HttpKernelInterface $httpKernel
     * @param HtmlResponseStack $responseStack
     */
    public function __construct(EventDispatcherInterface $dispatcher, RequestStack $requestStack, HttpKernelInterface $httpKernel, HtmlResponseStack $responseStack)
    {
        $this->dispatcher = $dispatcher;
        $this->requestStack = $requestStack;
        $this->httpKernel = $httpKernel;
        $this->responseStack = $responseStack;
    }

    /**
     * Renders an array. Returns a response object.
     *
     * Note: the response object is a "stub" object. It does not really contain the response. As such, you cannot act on it to modify the status or whatever part of the object.
     *
     * @param array $drupalRenderArray
     * @return ResponseInterface
     */
    public function getResponse(array $drupalRenderArray) : ResponseInterface
    {
        $symfonyRequest = $this->requestStack->getCurrentRequest();

        $symfonyRequest->attributes->set(RouteObjectInterface::ROUTE_NAME, '<current>');
        $symfonyRequest->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route($symfonyRequest->getUri()));

        /*$response = [
            '#type' => 'page',
            '#markup' => '<h1>Hello world</h1>'
        ];*/


        /*$response = array(
            '#type' => 'markup',
            '#title' => "poum",
            '#markup' => t('Hello world')
        );
        */

        $event = new GetResponseForControllerResultEvent($this->httpKernel, $symfonyRequest, HttpKernelInterface::MASTER_REQUEST, $drupalRenderArray);
        $this->dispatcher->dispatch(KernelEvents::VIEW, $event);

        if ($event->hasResponse()) {
            $response = $event->getResponse();
        } else {
            throw new \LogicException('Expecting a response. Got nothing.');
        }

        if (!$response instanceof HtmlResponse) {
            throw new \LogicException("Expected response was a HtmlResponse object. Got ".get_class($response));
        }

        $this->responseStack->push($response);

        return new TextResponse("template", 418);
    }
}
