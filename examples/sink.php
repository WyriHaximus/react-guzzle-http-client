<?php

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory as EventLoopFactory;
use RingCentral\Psr7\Request;
use WyriHaximus\React\Guzzle\HttpClient\RequestFactory;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$loop = EventLoopFactory::create();


$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dnsResolver = $dnsResolverFactory->createCached('8.8.8.8', $loop);

$factory = new React\HttpClient\Factory();
$client = $factory->create($loop, $dnsResolver);

$requestFactory = new RequestFactory();

$requestFactory->create(
    new Request(
        'GET',
        'https://api.ipify.org'
    ),
    [
        'sink' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ip.txt',
    ],
    $dnsResolver,
    $client,
    $loop
)->done(function (ResponseInterface $response) {
    echo (string)$response->getBody(), PHP_EOL;
    echo file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ip.txt'), PHP_EOL;
});

$loop->run();
