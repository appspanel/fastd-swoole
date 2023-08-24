<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace FastD\Swoole\Server;

use FastD\Swoole\Server;
use Swoole\Server as SwooleServer;
use Swoole\WebSocket\Server as SwooleWebSocketServer;
use Swoole\Http\Request as SwooleRequest;
use Swoole\WebSocket\Frame as SwooleFrame;

/**
 * Class WebSocketServer
 *
 * @package FastD\Swoole\Server\WebSocket
 */
abstract class WebSocket extends Server
{
    protected string $scheme = 'ws';

    /**
     * @param \Swoole\WebSocket\Server $server
     * @param \Swoole\Http\Request $request
     */
    public function onOpen(SwooleWebSocketServer $server, SwooleRequest $request): void
    {
        $this->doOpen($server, $request);
    }

    /**
     * @param \Swoole\WebSocket\Server $server
     * @param \Swoole\Http\Request $request
     */
    public function doOpen(SwooleWebSocketServer $server, SwooleRequest $request): void
    {
    }

    /**
     * @param \Swoole\Server $server
     * @param \Swoole\WebSocket\Frame $frame
     * @return mixed
     */
    public function onMessage(SwooleServer $server, SwooleFrame $frame): mixed
    {
        return $this->doMessage($server, $frame);
    }

    /**
     * @param \Swoole\Server $server
     * @param \Swoole\WebSocket\Frame $frame
     * @return mixed
     */
    abstract public function doMessage(SwooleServer $server, SwooleFrame $frame): mixed;

    /**
     * @return \Swoole\WebSocket\Server
     */
    public function initSwoole(): SwooleServer
    {
        return new SwooleWebSocketServer($this->host, $this->port);
    }

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
