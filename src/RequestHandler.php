<?php


namespace Drupal\stratigility_bridge;


use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Zend\Diactoros\Response\TextResponse;

/**
 * Listens to every single request to Drupal and put them through a PSR-15 middleware pipe.
 */
class RequestHandler implements EventSubscriberInterface
{
    const SYMFONY_REQUEST = 'TheCodingMachine\\HttpInteropBridge\\SYMFONY_REQUEST';

    /**
     * @var ServerMiddlewareInterface
     */
    private $httpInteropMiddleware;
    /**
     * @var HtmlResponseStack
     */
    private $responseStack;
    /**
     * @var HttpFoundationFactoryInterface
     */
    private $httpFoundationFactory;
    /**
     * @var HttpMessageFactoryInterface
     */
    private $httpMessageFactory;

    public function __construct(ServerMiddlewareInterface $httpInteropMiddleware, HtmlResponseStack $responseStack = null, HttpFoundationFactoryInterface $httpFoundationFactory = null, HttpMessageFactoryInterface $httpMessageFactory = null)
    {
        $this->httpInteropMiddleware = $httpInteropMiddleware;
        $this->responseStack = $responseStack;
        $this->httpFoundationFactory = $httpFoundationFactory ?: new HttpFoundationFactory();
        $this->httpMessageFactory = $httpMessageFactory ?: new DiactorosFactory();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $events[KernelEvents::REQUEST][] = array('handleRequest', 33);
        return $events;
    }

    public function handleRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $psr7Request = $this->httpMessageFactory->createRequest($request);

        $psr7Request = $psr7Request->withAttribute(self::SYMFONY_REQUEST, $request);

        $psr7Response = $this->httpInteropMiddleware->process($psr7Request, new class implements DelegateInterface {
            public function process(RequestInterface $request)
            {
                return new TextResponse("bypass", 418);
            }
        });

        $body = (string)$psr7Response->getBody();
        if ($body === 'bypass' && $psr7Response->getStatusCode() === 418) {
            return;
        } elseif ($body === 'template') {
            if (!$this->responseStack->isEmpty()) {
                $response = $this->responseStack->pop();
                $event->setResponse($response);
                return;
            } else {
                throw new \LogicException('Response stack is empty. Expecting a response there.');
            }
        }

        $response = $this->httpFoundationFactory->createResponse($psr7Response);

        $event->setResponse($response);
    }
}
