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

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\LoopInterface;
use React\HttpClient\Client as ReactHttpClient;
use React\HttpClient\Request as HttpRequest;
use React\HttpClient\Response as HttpResponse;
use React\Promise\Deferred;
use React\Stream\Stream as ReactStream;
use Exception;

/**
 * Class Request
 *
 * @package WyriHaximus\React\Guzzle\HttpClient
 */
class Request
{
    /**
     * @var ReactHttpClient
     */
    protected $httpClient;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var HttpResponse
     */
    protected $httpResponse;

    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * @var Stream
     */
    protected $stream;

    /**
     * @var \Exception
     */
    protected $error = '';

    /**
     * @var \React\EventLoop\Timer\TimerInterface
     */
    protected $connectionTimer;

    /**
     * @var \React\EventLoop\Timer\TimerInterface
     */
    protected $requestTimer;

    /**
     * @var ProgressInterface
     */
    protected $progress;

    /**
     * @var Deferred
     */
    protected $deferred;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $defaultOptions = [
        'stream' => false,
        'connect_timeout' => 0,
        'timeout' => 0,
        'delay' => 0,
    ];

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var bool
     */
    protected $connectionTimedOut = false;

    /**
     * @param RequestInterface $request
     * @param array $options
     * @param ReactHttpClient $httpClient
     * @param LoopInterface $loop

     */
    protected function __construct(
        RequestInterface $request,
        array $options,
        ReactHttpClient $httpClient,
        LoopInterface $loop
    ) {
        $this->request = $request;
        $this->applyOptions($options);
        $this->httpClient = $httpClient;
        $this->loop = $loop;
    }

    /**
     * @param RequestInterface $request
     * @param array $options
     * @param ReactHttpClient $httpClient
     * @param LoopInterface $loop
     * @return \React\Promise\Promise
     */
    public static function send(
        RequestInterface $request,
        array $options,
        ReactHttpClient $httpClient,
        LoopInterface $loop,
        Request $requestObject = null
    ) {
        if ($requestObject === null) {
            $requestObject = new static($request, $options, $httpClient, $loop);
        }
        return $requestObject->perform();
    }

    /**
     * @return \React\Promise\Promise
     */
    protected function perform()
    {
        $this->deferred = new Deferred();

        $this->loop->addTimer(
            (int)$this->options['delay'] / 1000,
            function () {
                $this->tickRequest();
            }
        );

        return $this->deferred->promise();
    }

    /**
     *
     */
    protected function tickRequest()
    {
        $this->loop->futureTick(function () {
            $request = $this->setupRequest();
            $this->setupListeners($request);

            $body = $this->request->getBody()->getContents();

            $this->progress->onSending($body);

            $this->setConnectionTimeout($request);
            $request->end($body);
            $this->setRequestTimeout($request);
        });
    }

    /**
     * @return HttpRequest mixed
     */
    protected function setupRequest()
    {
        $headers = [];
        foreach ($this->request->getHeaders() as $key => $values) {
            $headers[$key] = implode(';', $values);
        }

        return $this->httpClient->request(
            $this->request->getMethod(),
            (string)$this->request->getUri(),
            $headers,
            $this->request->getProtocolVersion()
        );
    }

    /**
     * @param HttpRequest $request
     */
    protected function setupListeners(HttpRequest $request)
    {
        $request->on(
            'headers-written',
            function () {
                $this->onHeadersWritten();
            }
        );
        $request->on(
            'drain',
            function () {
                $this->progress->onSent();
            }
        );
        $request->on(
            'response',
            function (HttpResponse $response) use ($request) {
                $this->onResponse($response, $request);
            }
        );
        $request->on(
            'error',
            function ($error) {
                $this->onError($error);
            }
        );
        $request->on(
            'end',
            function () {
                $this->onEnd();
            }
        );
    }

    /**
     * @param HttpRequest $request
     */
    public function setConnectionTimeout(HttpRequest $request)
    {
        if ($this->options['connect_timeout'] > 0) {
            $this->connectionTimer = $this->loop->addTimer(
                $this->options['connect_timeout'],
                function () use ($request) {
                    $request->closeError(new \Exception('Connection time out'));
                }
            );
        }
    }

    /**
     * @param HttpRequest $request
     */
    public function setRequestTimeout(HttpRequest $request)
    {
        if ($this->options['timeout'] > 0) {
            $this->requestTimer = $this->loop->addTimer(
                $this->options['timeout'],
                function () use ($request) {
                    $request->closeError(new \Exception('Transaction time out'));
                }
            );
        }
    }

    protected function onHeadersWritten()
    {
        if ($this->connectionTimer !== null && $this->loop->isTimerActive($this->connectionTimer)) {
            $this->loop->cancelTimer($this->connectionTimer);
        }
    }

    /**
     * @param HttpResponse $response
     * @param HttpRequest  $request
     */
    protected function onResponse(HttpResponse $response, HttpRequest $request)
    {
        $this->httpResponse = $response;
        if (isset($this->options['sink'])) {
            $this->saveTo();
        }

        $this->handleResponse($request);
    }

    protected function saveTo()
    {
        $saveTo = $this->options['sink'];

        $writeStream = fopen($saveTo, 'w');
        stream_set_blocking($writeStream, 0);
        $saveToStream = new ReactStream($writeStream, $this->loop);

        $saveToStream->on(
            'end',
            function () {
                $this->onEnd();
            }
        );

        $this->httpResponse->pipe($saveToStream);
    }

    /**
     * @param string $data
     */
    protected function onData($data)
    {
        $this->progress->onData($data);
    }

    /**
     * @param \Exception $error
     */
    protected function onError(\Exception $error)
    {
        if ($this->requestTimer !== null && $this->loop->isTimerActive($this->requestTimer)) {
            $this->loop->cancelTimer($this->requestTimer);
        }

        if ($this->connectionTimer !== null && $this->loop->isTimerActive($this->connectionTimer)) {
            $this->loop->cancelTimer($this->connectionTimer);
        }

        $this->error = $error;
        $this->deferred->reject($this->error);
    }

    /**
     *
     */
    protected function onEnd()
    {
        if ($this->requestTimer !== null && $this->loop->isTimerActive($this->requestTimer)) {
            $this->loop->cancelTimer($this->requestTimer);
        }

        if ($this->connectionTimer !== null && $this->loop->isTimerActive($this->connectionTimer)) {
            $this->loop->cancelTimer($this->connectionTimer);
        }

        $this->loop->futureTick(function () {
            if ($this->httpResponse === null) {
                $this->deferred->reject($this->error);
            }
        });
    }

    /**
     *
     */
    protected function handleResponse($request)
    {
        $this->progress->onResponse($this->httpResponse);

        $this->createStream($request);

        $response = new Response(
            $this->httpResponse->getCode(),
            $this->httpResponse->getHeaders(),
            $this->stream,
            $this->httpResponse->getVersion(),
            $this->httpResponse->getReasonPhrase()
        );

        if (!$this->options['stream']) {
            return $request->on('end', function () use ($response) {
                $this->resolveResponse($response);
            });
        }

        $this->resolveResponse($response);
    }

    protected function resolveResponse($response)
    {
        $this->loop->futureTick(function () use ($response) {
            $this->deferred->resolve($response);
        });
    }

    protected function createStream($request)
    {
        $this->stream = new Stream([
            'response' => $this->httpResponse,
            'request' => $request,
            'loop' => $this->loop,
        ]);
    }
    
    private function applyOptions(array $options = [])
    {
        $this->options = array_replace_recursive($this->defaultOptions, $options);
        
        // provides backwards compatibility for Guzzle 3-5.
        if (isset($this->options['client'])) {
            $this->options = array_merge($this->options, $this->options['client']);
            unset($this->options['client']);
        }
        
        // provides for backwards compatibility for Guzzle 3-5
        if (isset($this->options['save_to'])) {
            $this->options['sink'] = $options['save_to'];
            unset($this->options['save_to']);
        }
        
        if (isset($this->options['progress']) && is_callable($this->options['progress'])) {
            $this->progress = new Progress($this->options['progress']);
        } else {
            $this->progress = new Progress(function () {
            });
        }
    }
}
