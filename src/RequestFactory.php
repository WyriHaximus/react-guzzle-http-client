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
use Clue\React\HttpProxy\ProxyConnector as HttpProxyClient;
use Clue\React\Socks\Client as SocksProxyClient;
use Psr\Http\Message\RequestInterface;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\HttpClient\Client as HttpClient;
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
     * @param $resolver Resolver
     * @param HttpClient $httpClient
     * @param LoopInterface $loop
     * @return \React\Promise\Promise
     */
    public function create(
        RequestInterface $request,
        array $options,
        Resolver $resolver,
        HttpClient $httpClient,
        LoopInterface $loop
    ) {
        return \WyriHaximus\React\futurePromise($loop)->then(function () use (
            $request,
            $options,
            $resolver,
            $httpClient,
            $loop
        ) {
            $sender = $this->createSender($options, $resolver, $httpClient, $loop);
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
    protected function createSender(array $options, Resolver $resolver, HttpClient $httpClient, LoopInterface $loop)
    {
        $connector = $this->getProperty($httpClient, 'connector');

        if (isset($options['proxy'])) {
            switch (parse_url($options['proxy'], PHP_URL_SCHEME)) {
                case 'http':
                    $connector = new HttpProxyClient($options['proxy'], $connector);
                    break;
                case 'socks':
                    $connector = (new SocksProxyClient(
                        $options['proxy'],
                        $loop,
                        $connector,
                        $resolver
                    ))->createConnector();
                    break;
                case 'socks4':
                case 'socks4a':
                    $proxyClient = new SocksProxyClient(
                        $options['proxy'],
                        $loop,
                        $connector,
                        $resolver
                    );
                    $proxyClient->setProtocolVersion(4);
                    $connector = $proxyClient->createConnector();
                    break;
                case 'socks5':
                    $proxyClient = new SocksProxyClient(
                        $options['proxy'],
                        $loop,
                        $connector,
                        $resolver
                    );
                    $proxyClient->setProtocolVersion(5);
                    $connector = $proxyClient->createConnector();
                    break;
            }
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
