<?php

/**
 * This file is part of ReactGuzzleRing.
 *
 ** (c) 2014 Cees-Jan Kiewiet
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace WyriHaximus\React\Guzzle\HttpClient;

use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Io\Sender;
use Clue\React\Socks\Client as SocksClient;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\LoopInterface;
use React\HttpClient\Client as HttpClient;
use React\SocketClient\Connector;
use React\SocketClient\ConnectorInterface;
use React\SocketClient\DnsConnector;
use ReflectionObject;

/**
 * Class RequestFactory
 *
 * @package WyriHaximus\React\Guzzle\HttpClient
 */
class RequestFactory
{
    /**
     *
     * @param RequestInterface $request
     * @param array $options
     * @param HttpClient $httpClient
     * @param LoopInterface $loop
     * @return \React\Promise\Promise
     */
    public function create(RequestInterface $request, array $options, HttpClient $httpClient, LoopInterface $loop)
    {
        $sender = $this->createSender($options, $httpClient, $loop);
        return (new Browser($loop, $sender))
            ->withOptions($this->convertOptions($options))
            ->send($request);
    }

    protected function createSender(array $options, HttpClient $httpClient, LoopInterface $loop)
    {
        if (isset($options['proxy'])) {
            $connector = $this->extractConnector($httpClient);
            $resolver = $this->extractResolver($connector);
            $socks = new SocksClient($options['proxy'], $loop, $connector, $resolver);
            return Sender::createFromLoopConnectors($loop, $socks->createConnector());
        }

        return new Sender($httpClient);
    }

    protected function convertOptions(array $options)
    {
        return $options;
    }

    protected function extractConnector(HttpClient $httpClient)
    {
        $reflection = new ReflectionObject($httpClient);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        return $property->getValue($httpClient);
    }

    protected function extractResolver(ConnectorInterface $connector)
    {
        if ($connector instanceof Connector) {
            $reflection = new ReflectionObject($connector);
            $property = $reflection->getProperty('resolver');
            $property->setAccessible(true);
            return $property->getValue($connector);
        }

        if ($connector instanceof DnsConnector) {
            $reflection = new ReflectionObject($connector);
            $property = $reflection->getProperty('resolver');
            $property->setAccessible(true);
            return $property->getValue($connector);
        }

        return null;
    }
}
