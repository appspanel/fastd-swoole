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
     * @return void
     */
    public function onPacket(SwooleServer $server, $data, array $clientInfo)
    {
        try {
            $this->doPacket($server, $data, $clientInfo);
        } catch (\Exception $e) {
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
     * @param $data
     * @param $clientInfo
     * @return mixed
     */
    abstract public function doPacket(SwooleServer $server, $data, $clientInfo);

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
