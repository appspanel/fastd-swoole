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
 * Class Udp
 *
 * @package FastD\Swoole\Server
 */
abstract class UDP extends Server
{
    const SCHEME = 'udp';

    /**
     * 服务器同时监听TCP/UDP端口时，收到TCP协议的数据会回调onReceive，收到UDP数据包回调onPacket
     *
     * @param \Swoole\Server $server
     * @param string $data
     * @param array $clientInfo
     */
    public function onPacket(SwooleServer $server, string $data, array $clientInfo): void
    {
        try {
            $this->doPacket($server, $data, $clientInfo);
        } catch (Throwable $e) {
            $content = sprintf("Error: %s\nFile: %s \n Code: %s",
                $e->getMessage(),
                $e->getFile(),
                $e->getCode()
            );
            $server->sendto($clientInfo['address'], $clientInfo['port'], $content);
        }
    }

    /**
     * @param \Swoole\Server $server
     * @param string $data
     * @param array $clientInfo
     * @return mixed
     */
    abstract public function doPacket(SwooleServer $server, string $data, array $clientInfo): mixed;

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
     * @param $data
     * @param int $taskId
     * @return mixed
     */
    public function doFinish(SwooleServer $server, $data, int $taskId): mixed
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
