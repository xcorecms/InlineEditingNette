<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Tests\Handler;

use Closure;
use Nette\Application\Responses\JsonResponse;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use Tester\Assert;
use Tester\TestCase;
use XcoreCMS\InlineEditing\Model\Simple\ContentProvider;
use XcoreCMS\InlineEditingNette\Handler\Route;
use XcoreCMS\InlineEditingNette\Security\InlinePermissionChecker;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class RouteTest extends TestCase
{
    private function createRoute(?IResponse $response = null): Route
    {
        $contentProvider = new class extends ContentProvider {
            public function __construct()
            {
            }
        };

        return new Route(
            '/inline-editing',
            $contentProvider,
            new InlinePermissionChecker,
            $response ?? new Response
        );
    }

    public function testMatchPostOnMask(): void
    {
        $route = $this->createRoute();
        $request = new Request(new UrlScript('http://example.com/inline-editing'), method: 'POST');

        $result = $route->match($request);

        Assert::type('array', $result);
        Assert::same('Nette:Micro', $result['presenter']);
        Assert::type(Closure::class, $result['callback']);
    }

    public function testMatchRejectsOtherMethod(): void
    {
        $route = $this->createRoute();
        $request = new Request(new UrlScript('http://example.com/inline-editing'), method: 'GET');

        Assert::null($route->match($request));
    }

    public function testMatchRejectsOtherPath(): void
    {
        $route = $this->createRoute();
        $request = new Request(new UrlScript('http://example.com/other-path'), method: 'POST');

        Assert::null($route->match($request));
    }

    public function testConstructUrlReturnsNull(): void
    {
        $route = $this->createRoute();

        Assert::null($route->constructUrl([], new UrlScript('http://example.com/')));
    }

    public function testCallbackWithInvalidInputReturnsError(): void
    {
        $response = new Response;
        $route = $this->createRoute($response);

        $invoked = false;
        $route->onInvoke[] = function () use (&$invoked): void {
            $invoked = true;
        };

        $request = new Request(new UrlScript('http://example.com/inline-editing'), method: 'POST');
        $result = $route->match($request);

        // php://input is empty in CLI -> invalid json -> 500 + empty payload
        $appResponse = $result['callback']();

        Assert::true($invoked);
        Assert::type(JsonResponse::class, $appResponse);
        Assert::same([], $appResponse->getPayload());
        Assert::same(IResponse::S500_INTERNAL_SERVER_ERROR, $response->getCode());
    }
}

(new RouteTest)->run();
