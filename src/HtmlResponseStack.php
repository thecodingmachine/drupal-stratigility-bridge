<?php


namespace Drupal\stratigility_bridge;
use Drupal\Core\Render\HtmlResponse;

/**
 * A service that stores the HtmlResponse through conversion between PSR-7 and Symfony requests (needed for Html responses)
 */
class HtmlResponseStack
{
    /**
     * @var HtmlResponse[]
     */
    private $htmlResponses = [];

    public function push(HtmlResponse $response)
    {
        $this->htmlResponses[] = $response;
    }

    public function pop() : HtmlResponse
    {
        return array_pop($this->htmlResponses);
    }

    public function isEmpty() : bool
    {
        return empty($this->htmlResponses);
    }
}
