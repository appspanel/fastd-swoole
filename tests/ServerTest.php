<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */


use FastD\Swoole\Server\TCP;
use PHPUnit\Framework\TestCase;
use Swoole\Server as SwooleServer;

class TcpServer extends TCP
{
    /**
     * @param \Swoole\ $server
     * @param $fd
     * @param $data
     * @param $from_id
     * @return mixed
     */
    public function doWork(SwooleServer $server, $fd, $data, $from_id)
    {
        // TODO: Implement doWork() method.
    }
}

class ServerTest extends TestCase
{
    public function testNewServer()
    {
        $server = new TcpServer('foo', '127.0.0.1:9528');
        $this->assertNull($server->getSwoole());

        $this->assertEquals('127.0.0.1', $server->getHost());
        $this->assertEquals('9528', $server->getPort());
        $this->assertEquals('foo', $server->getName());
        $this->assertEquals('/tmp/foo.pid', $server->getPidFile());
    }

    public function testServerBootstrap()
    {
        $server = new TcpServer('foo', '127.0.0.1:9529');
        $this->assertNull($server->getSwoole());

        $server->daemon();
        $server->bootstrap();
        $this->assertEquals('127.0.0.1', $server->getSwoole()->host);
        $this->assertEquals(9529, $server->getSwoole()->port);
        $this->assertEquals('/tmp/foo.pid', $server->getPidFile());
        $this->assertEquals([
            'daemonize' => true,
            'task_worker_num' => 8,
            'task_tmpdir' => '/tmp',
            'pid_file' => '/tmp/foo.pid',
            'worker_num' => 8,
            'open_cpu_affinity' => true,
        ], $server->getSwoole()->setting);
    }

    public function testServerBootstrapConfig()
    {
        $server = new TcpServer('foo', 'tcp://127.0.0.1:9530', ['pid_file' => '/tmp/foo.pid',]);
        $this->assertNull($server->getSwoole());

        $server->daemon();
        $server->bootstrap();
        $this->assertEquals('127.0.0.1', $server->getSwoole()->host);
        $this->assertEquals(9530, $server->getSwoole()->port);
        $this->assertEquals('/tmp/foo.pid', $server->getPidFile());
        $this->assertEquals([
            'daemonize' => true,
            'pid_file' => '/tmp/foo.pid',
            'task_worker_num' => 8,
            'task_tmpdir' => '/tmp',
            'worker_num' => 8,
            'open_cpu_affinity' => true,
        ], $server->getSwoole()->setting);
    }
}
