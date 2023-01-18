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
    protected $scheme = 'ws';

    /**
     * @param \Swoole\WebSocket\Server $server
     * @param \Swoole\Http\Request $request
     * @return mixed
     */
    public function onOpen(SwooleWebSocketServer $server, SwooleRequest $request)
    {
        return $this->doOpen($server, $request);
    }

    /**
     * @param \Swoole\WebSocket\Server $server
     * @param \Swoole\Http\Request $request
     * @return mixed
     */
    public function doOpen(SwooleWebSocketServer $server, SwooleRequest $request){}

    /**
     * @param \Swoole\Server $server
     * @param \Swoole\WebSocket\Frame $frame
     * @return mixed
     */
    public function onMessage(SwooleServer $server, SwooleFrame $frame)
    {
        return $this->doMessage($server, $frame);
    }

    /**
     * @param \Swoole\Server $server
     * @param \Swoole\WebSocket\Frame $frame
     * @return mixed
     */
    abstract public function doMessage(SwooleServer $server, SwooleFrame $frame);

    /**
     * @return \Swoole\WebSocket\Server
     */
    public function initSwoole()
    {
        return new SwooleWebSocketServer($this->host, $this->port);
    }

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
