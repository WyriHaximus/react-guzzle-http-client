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
use Psr\Http\Message\RequestInterface;
use React\EventLoop\LoopInterface;
use React\HttpClient\Client as HttpClient;

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
        return (new Browser($loop, new Sender($httpClient)))->send($request);
    }
}
