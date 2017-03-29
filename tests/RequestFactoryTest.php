<?php

/**
 * This file is part of ReactGuzzle.
 *
 ** (c) 2014 Cees-Jan Kiewiet
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace WyriHaximus\React\Tests\Guzzle\HttpClient;

use Exception;
use GuzzleHttp\Psr7\Request;
use React\Dns\Resolver\Factory as ResolverFactory;
use React\EventLoop\Factory;
use React\HttpClient\Client;
use React\Promise\RejectedPromise;
use WyriHaximus\React\Guzzle\HttpClient\RequestFactory;

/**
 * Class RequestFactoryTest
 *
 * @package WyriHaximus\React\Tests\Guzzle
 */
class RequestFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    public function setUp()
    {
        parent::setUp();

        $this->requestFactory = new RequestFactory();
    }

    public function tearDown()
    {
        parent::tearDown();

        unset($this->requestFactory);
    }

    public function testCreate()
    {
        $loop = Factory::create();
        $resolver = (new ResolverFactory())->createCached('8.8.8.8', $loop);
        $connector = $this->prophesize('React\SocketClient\ConnectorInterface');
        $connector->create('example.com', 80)->shouldBeCalled()->willReturn(new RejectedPromise());
        $secureConnector = $this->prophesize('React\SocketClient\ConnectorInterface');
        $client = new Client($connector->reveal(), $secureConnector->reveal());
        $request = new Request('GET', 'http://example.com/');
        $this->assertInstanceOf(
            'React\Promise\PromiseInterface',
            $this->requestFactory->create(
                $request,
                [],
                $resolver,
                $client,
                $loop
            )
        );

        $loop->run();
    }

    public function provideProxies()
    {
        return [
            ['http://127.0.0.1:8080'],
            ['socks://127.0.0.1:8080'],
            ['socks4://127.0.0.1:8080'],
            ['socks4a://127.0.0.1:8080'],
            ['socks5://127.0.0.1:8080'],
        ];
    }

    /**
     * @dataProvider provideProxies
     */
    public function testCreateProxy($proxy)
    {
        $exception = new Exception();
        $loop = Factory::create();
        $resolver = (new ResolverFactory())->createCached('8.8.8.8', $loop);
        $connector = $this->prophesize('React\SocketClient\ConnectorInterface');
        $connector->create('127.0.0.1', 8080)->shouldBeCalled()->willReturn(new RejectedPromise($exception));
        $secureConnector = $this->prophesize('React\SocketClient\ConnectorInterface');
        $client = new Client($connector->reveal(), $secureConnector->reveal());
        $request = new Request('GET', 'http://example.com/');
        $promise = $this->requestFactory->create(
            $request,
            [
                'proxy' => $proxy,
            ],
            $resolver,
            $client,
            $loop
        );

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $previousException = null;
        try {
            $result = \Clue\React\Block\await($promise, $loop, 5);
        } catch (Exception $catchedException) {
            $previousException = $catchedException;
            do {
                $previousException = $previousException->getPrevious();
            } while ($previousException !== null && $previousException !== $exception);
        }

        $this->assertSame($exception, $previousException);
    }
}
