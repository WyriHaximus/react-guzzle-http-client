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

use GuzzleHttp\Psr7\Request;
use Phake;
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
                $client,
                $loop
            )
        );

        $loop->run();
    }

    public function testCreateProxy()
    {
        $loop = Factory::create();
        $connector = $this->prophesize('React\SocketClient\ConnectorInterface');
        $connector->create('foo.bar', 1080)->shouldBeCalled()->willReturn(new RejectedPromise());
        $secureConnector = $this->prophesize('React\SocketClient\ConnectorInterface');
        $client = new Client($connector->reveal(), $secureConnector->reveal());
        $request = new Request('GET', 'http://example.com/');
        $this->assertInstanceOf(
            'React\Promise\PromiseInterface',
            $this->requestFactory->create(
                $request,
                [
                    'proxy' => 'foo.bar',
                ],
                $client,
                $loop
            )
        );

        $loop->run();
    }
}
