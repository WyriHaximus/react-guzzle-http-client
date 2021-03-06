<?php

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory as EventLoopFactory;
use React\HttpClient\Client as HttpClient;
use RingCentral\Psr7\Request;
use WyriHaximus\React\Guzzle\HttpClient\RequestFactory;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$loop = EventLoopFactory::create();


$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dnsResolver = $dnsResolverFactory->createCached('8.8.8.8', $loop);

$client = new HttpClient($loop);

$requestFactory = new RequestFactory();

$requestFactory->create(
    new Request(
        'GET',
        'https://v4v6.ipv6-test.com/api/myip.php'
    ),
    [
        //'proxy' => 'socks://127.0.0.1:8080/', // ssh -D 8080 username@host
        'proxy' => 'http://127.0.0.1:8888/', // https://github.com/leproxy/leproxy
    ],
    $dnsResolver,
    $client,
    $loop
)->done(function (ResponseInterface $response) {
    echo (string)$response->getBody();
}, function ($e) {
    echo (string)$e;
});

$loop->run();
