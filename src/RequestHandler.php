<?php


namespace Drupal\stratigility_bridge;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
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
    const SYMFONY_REQUEST = 'TheCodingMachine\\Psr15Bridge\\SYMFONY_REQUEST';

    /**
     * @var MiddlewareInterface
     */
    private $httpMiddleware;
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

    public function __construct(MiddlewareInterface $httpMiddleware, HtmlResponseStack $responseStack = null, HttpFoundationFactoryInterface $httpFoundationFactory = null, HttpMessageFactoryInterface $httpMessageFactory = null)
    {
        $this->httpMiddleware = $httpMiddleware;
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

        $psr7Response = $this->httpMiddleware->process($psr7Request, new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
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
