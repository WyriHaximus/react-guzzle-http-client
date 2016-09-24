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
        $request = new Request('GET', 'http://example.com/');
        $this->assertInstanceOf(
            'React\Promise\PromiseInterface',
            $this->requestFactory->create(
                $request,
                [],
                Phake::partialMock(
                    'React\HttpClient\Client',
                    Phake::mock('React\SocketClient\ConnectorInterface'),
                    Phake::mock('React\SocketClient\ConnectorInterface')
                ),
                Phake::mock('\React\EventLoop\StreamSelectLoop')
            )
        );
    }
}
