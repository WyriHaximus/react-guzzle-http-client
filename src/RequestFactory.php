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
        return \WyriHaximus\React\futurePromise($loop)->then(function () use ($request, $options, $httpClient, $loop) {
            $sender = $this->createSender($options, $httpClient, $loop);
            return (new Browser($loop, $sender))
                ->withOptions($this->convertOptions($options))
                ->send($request);
        });
    }

    /**
     * @param array $options
     * @param HttpClient $httpClient
     * @param LoopInterface $loop
     * @return Sender
     */
    protected function createSender(array $options, HttpClient $httpClient, LoopInterface $loop)
    {
        $connector = $this->getProperty($httpClient, 'connector');

        if (isset($options['proxy'])) {
            $resolver = $this->extractResolver($connector);
            $connector = (new SocksClient($options['proxy'], $loop, $connector, $resolver))->createConnector();
        }

        return Sender::createFromLoopConnectors($loop, $connector);
    }

    /**
     * @param array $options
     * @return array
     */
    protected function convertOptions(array $options)
    {
        return $options;
    }

    /**
     * @param ConnectorInterface $connector
     * @return mixed|null
     */
    protected function extractResolver(ConnectorInterface $connector)
    {
        if ($connector instanceof Connector || $connector instanceof DnsConnector) {
            return $this->getProperty($connector, 'resolver');
        }

        return null;
    }

    /**
     * @param object $object
     * @param string $desiredProperty
     * @return mixed
     */
    protected function getProperty($object, $desiredProperty)
    {
        $reflection = new ReflectionObject($object);
        $property = $reflection->getProperty($desiredProperty);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}
