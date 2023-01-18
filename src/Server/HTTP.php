<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace FastD\Swoole\Server;

use Exception;
use FastD\Http\HttpException;
use FastD\Http\Response;
use FastD\Http\SwooleServerRequest;
use FastD\Swoole\Server;
use Psr\Http\Message\ServerRequestInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Server as SwooleServer;

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
    public function initSwoole()
    {
        return new SwooleHttpServer($this->getHost(), $this->getPort());
    }

    /**
     * @param \Swoole\Http\Request $swooleRequet
     * @param \Swoole\Http\Response $swooleResponse
     */
    public function onRequest(SwooleRequest $swooleRequet, SwooleResponse $swooleResponse)
    {
        try {
            $swooleRequestServer = SwooleServerRequest::createServerRequestFromSwoole($swooleRequet);
            $response = $this->doRequest($swooleRequestServer);
            $this->sendHeader($swooleResponse, $response);
            $swooleResponse->status($response->getStatusCode());
            $swooleResponse->end((string) $response->getBody());
            unset($response);
        } catch (HttpException $e) {
            $swooleResponse->status($e->getStatusCode());
            $swooleResponse->end($e->getMessage());
        } catch (Exception $e) {
            $swooleResponse->status(500);
            $swooleResponse->end(static::SERVER_INTERVAL_ERROR);
        }
    }

    /**
     * @param \Swoole\Http\Response $swooleResponse
     * @param Response $response
     */
    protected function sendHeader(SwooleResponse $swooleResponse, Response $response)
    {
        foreach ($response->getHeaders() as $key => $header) {
            $swooleResponse->header($key, $response->getHeaderLine($key));
        }

        foreach ($response->getCookieParams() as $key => $cookieParam) {
            $swooleResponse->cookie($key, $cookieParam);
        }
    }

    /**
     * @param ServerRequestInterface $serverRequest
     * @return Response
     */
    abstract public function doRequest(ServerRequestInterface $serverRequest);

    /**
     * @param \Swoole\Server $server
     * @param $data
     * @param $taskId
     * @param $workerId
     * @return mixed
     */
    public function doTask(SwooleServer $server, $data, $taskId, $workerId){}

    /**
     * @param \Swoole\Server $server
     * @param $data
     * @param $taskId
     * @return mixed
     */
    public function doFinish(SwooleServer $server, $data, $taskId){}

    /**
     * @param \Swoole\Server $server
     * @param $fd
     * @param $from_id
     */
    public function doConnect(SwooleServer $server, $fd, $from_id){}

    /**
     * @param \Swoole\Server $server
     * @param $fd
     * @param $fromId
     */
    public function doClose(SwooleServer $server, $fd, $fromId){}
}
