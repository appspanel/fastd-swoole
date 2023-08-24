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
use Throwable;

/**
 * Class Tcp
 *
 * @package FastD\Swoole\Server
 */
abstract class TCP extends Server
{
    /**
     * 服务器同时监听TCP/UDP端口时，收到TCP协议的数据会回调onReceive，收到UDP数据包回调onPacket
     *
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     * @param string $data
     */
    public function onReceive(SwooleServer $server, int $fd, int $reactorId, string $data): void
    {
        try {
            $this->doWork($server, $fd, $data, $reactorId);
        } catch (Throwable $e) {
            $server->send($fd, sprintf("Error: %s\nFile: %s \nCode: %s\nLine: %s\r\n\r\n",
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getCode(),
                    $e->getLine()
                )
            );
            $server->close($fd);
        }
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

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param mixed $data
     * @param int $reactorId
     * @return mixed
     */
    abstract public function doWork(SwooleServer $server, int $fd, mixed $data, int $reactorId): mixed;

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
}
