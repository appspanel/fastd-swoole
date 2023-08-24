<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace FastD\Swoole\Server;

use FastD\Http\HttpException;
use FastD\Http\SwooleServerRequest;
use FastD\Swoole\Server;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Server as SwooleServer;
use Throwable;

/**
 * Class HttpServer
 *
 * @package FastD\Swoole\Server
 */
abstract class HTTP extends Server
{
    const SERVER_INTERVAL_ERROR = 'Server Interval Error';

    const SCHEME = 'http';

    /**
     * @return \Swoole\Http\Server
     */
    public function initSwoole(): SwooleServer
    {
        return new SwooleHttpServer($this->getHost(), $this->getPort());
    }

    /**
     * @param \Swoole\Http\Request $swooleRequest
     * @param \Swoole\Http\Response $swooleResponse
     */
    public function onRequest(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void
    {
        try {
            $swooleServerRequest = SwooleServerRequest::createServerRequestFromSwoole($swooleRequest);
            $response = $this->doRequest($swooleServerRequest);
            $this->sendHeader($swooleResponse, $response);
            $swooleResponse->status($response->getStatusCode());
            $swooleResponse->end((string) $response->getBody());
            unset($response);
        } catch (HttpException $e) {
            $swooleResponse->status($e->getStatusCode());
            $swooleResponse->end($e->getMessage());
        } catch (Throwable) {
            $swooleResponse->status(500);
            $swooleResponse->end(static::SERVER_INTERVAL_ERROR);
        }
    }

    /**
     * @param \Swoole\Http\Response $swooleResponse
     * @param \FastD\Http\Response $response
     */
    protected function sendHeader(SwooleResponse $swooleResponse, ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $key => $header) {
            $swooleResponse->header($key, $response->getHeaderLine($key));
        }

        foreach ($response->getCookieParams() as $key => $cookieParam) {
            $swooleResponse->cookie($key, $cookieParam);
        }
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $serverRequest
     * @return \Psr\Http\Message\ResponseInterface
     */
    abstract public function doRequest(ServerRequestInterface $serverRequest): ResponseInterface;

    /**
     * @param \Swoole\Server $server
     * @param mixed $data
     * @param int $taskId
     * @param int $workerId
     * @return mixed
     */
    public function doTask(SwooleServer $server, mixed $data, int $taskId, int $workerId): mixed
    {
        return null;
    }

    /**
     * @param \Swoole\Server $server
     * @param mixed $data
     * @param int $taskId
     * @return mixed
     */
    public function doFinish(SwooleServer $server, mixed $data, int $taskId): mixed
    {
        return null;
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     */
    public function doConnect(SwooleServer $server, int $fd, int $reactorId): void
    {
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     */
    public function doClose(SwooleServer $server, int $fd, int $reactorId): void
    {
    }
}
