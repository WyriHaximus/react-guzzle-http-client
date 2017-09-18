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
        $connector = $this->prophesize('React\Socket\ConnectorInterface');
        $connector->connect('example.com:80')->shouldBeCalled()->willReturn(new RejectedPromise());
        $client = new Client($loop, $connector->reveal());
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
}
