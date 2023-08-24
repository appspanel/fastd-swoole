<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace FastD\Swoole;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use FastD\Swoole\Support\Watcher;
use Swoole\Server\Port as SwoolePort;
use Swoole\Server as SwooleServer;
use Throwable;

/**
 * Class Server
 * @package FastD\Swoole
 */
abstract class Server
{
    const VERSION = '2.1.0';

    /**
     * @var string $name
     */
    protected string $name;

    /**
     * @var OutputInterface
     */
    protected OutputInterface $output;

    /**
     * @var \Swoole\Server|null
     */
    protected ?SwooleServer $swoole = null;

    /**
     * Swoole server run configuration.
     *
     * @var array<string,mixed>
     */
    protected array $config = [
        'worker_num' => 8,
        'task_worker_num' => 8,
        'task_tmpdir' => '/tmp',
        'open_cpu_affinity' => true,
    ];

    const SCHEME = 'tcp';

    /**
     * @var string
     */
    protected string $host = '127.0.0.1';

    /**
     * @var int
     */
    protected int $port = 9527;

    /**
     * @var string|null
     */
    protected ?string $pidFile = null;

    /**
     * @var int|null
     */
    protected ?int $pid = null;

    /**
     * @var bool
     */
    protected bool $booted = false;

    /**
     * 多端口支持
     *
     * @var Server[]
     */
    protected array $listens = [];

    /**
     * @var Process[]
     */
    protected array $processes = [];

    /**
     * @var Timer[]
     */
    protected array $timers = [];

    /**
     * @var int|null
     */
    protected ?int $fd = null;

    /**
     * Server constructor.
     * @param string $name
     * @param string|null $address
     * @param array<string,mixed> $config
     * @param OutputInterface|null $output
     */
    public function __construct(string $name, ?string $address = null, array $config = [], ?OutputInterface $output = null)
    {
        $this->name = $name;

        if (null !== $address) {
            $info = parse_url($address);

            $this->host = $info['host'] ?? $this->host;
            $this->port = (int) ($info['port'] ?? $this->port);
        }

        $this->output = null === $output ? new ConsoleOutput() : $output;

        $this->configure($config);
    }

    /**
     * @param array $config
     * @return $this
     */
    public function configure(array $config): static
    {
        $this->config = array_merge($this->config, $config);

        if (isset($this->config['pid_file'])) {
            $this->pidFile = $this->config['pid_file'];
        }

        if (empty($this->pidFile)) {
            $this->pidFile = '/tmp/' . str_replace(' ', '-', $this->name) . '.pid';
            $this->config['pid_file'] = $this->pidFile;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * 守護進程
     *
     * @return $this
     */
    public function daemon(): static
    {
        $this->config['daemonize'] = true;

        return $this;
    }

    /**
     * @return string
     */
    public function getScheme(): string
    {
        return static::SCHEME;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Get client connection server's file descriptor.
     *
     * @return int|null
     */
    public function getFileDescriptor(): ?int
    {
        return $this->fd;
    }

    /**
     * @return int
     */
    public function getSocketType(): int
    {
        switch (static::SCHEME) {
            case 'udp':
                $type = SWOOLE_SOCK_UDP;
                break;
            case 'unix':
                $type = SWOOLE_UNIX_STREAM;
                break;
            case 'tcp':
            default :
                $type = SWOOLE_SOCK_TCP;
        }

        return $type;
    }

    /**
     * @return string|null
     */
    public function getPidFile(): ?string
    {
        return $this->pidFile;
    }

    /**
     * @return int|null
     */
    public function getPid(): ?int
    {
        return $this->pid;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return \Swoole\Server|null
     */
    public function getSwoole(): ?SwooleServer
    {
        return $this->swoole;
    }

    /**
     * @param null $name
     * @return Server[]
     */
    public function getListeners($name = null): array
    {
        return $this->listens;
    }

    /**
     * @param string $name
     * @return Server
     */
    public function getListener(string $name): Server
    {
        return $this->listens[$name];
    }

    /**
     * @return $this
     */
    protected function handleCallback(): static
    {
        $handles = get_class_methods($this);
        $isListenerPort = false;
        $serverClass = get_class($this->getSwoole());
        if ('Swoole\Server\Port' == $serverClass || 'swoole_server_port' == $serverClass) {
            $isListenerPort = true;
        }
        foreach ($handles as $value) {
            if (str_starts_with($value, 'on')) {
                if ($isListenerPort) {
                    if ('udp' === $this->getScheme()) {
                        $callbacks = ['onPacket',];
                    } else {
                        $callbacks = ['onConnect', 'onClose', 'onReceive',];
                    }
                    if (in_array($value, $callbacks)) {
                        $this->swoole->on(lcfirst(substr($value, 2)), [$this, $value]);
                    }
                } else {
                    $this->swoole->on(lcfirst(substr($value, 2)), [$this, $value]);
                }
            }
        }
        return $this;
    }

    /**
     * 引导服务，当启动是接收到 swoole server 信息，则默认以这个swoole 服务进行引导
     *
     * @param \Swoole\Server|\Swoole\Server\Port|null $swoole Server or swoole server port
     * @return $this
     */
    public function bootstrap(SwooleServer|SwoolePort|null $swoole = null): static
    {
        if (!$this->isBooted()) {
            $this->swoole = null === $swoole ? $this->initSwoole() : $swoole;

            $this->swoole->set($this->config);

            $this->handleCallback();

            $this->booted = true;
        }

        return $this;
    }

    /**
     * 如果需要自定义自己的swoole服务器,重写此方法
     *
     * @return \Swoole\Server
     */
    public function initSwoole(): SwooleServer
    {
        return new SwooleServer($this->host, $this->port, SWOOLE_PROCESS, $this->getSocketType());
    }

    /**
     * @param Server $server
     * @return $this
     */
    public function listen(Server $server): static
    {
        $this->listens[$server->getName()] = $server;

        return $this;
    }

    /**
     * @param Process $process
     * @return $this
     */
    public function process(Process $process): static
    {
        $process->withServer($this);

        $this->processes[] = $process;

        return $this;
    }

    /**
     * @param Timer $timer
     * @return $this
     */
    public function timer(Timer $timer): static
    {
        $timer->withServer($this);

        $this->timers[] = $timer;

        return $this;
    }

    /**
     * @param string $name
     * @param string|null $address
     * @param array<string,mixed> $config
     * @return static
     */
    public static function createServer(string $name, ?string $address = null, array $config = []): Server
    {
        return new static($name, $address, $config);
    }

    /**
     * @return int
     */
    public function start(): int
    {
        if ($this->isRunning()) {
            $this->output->writeln(sprintf('Server <info>[%s] %s:%s</info> address already in use', $this->name, $this->host, $this->port));
        } else {
            try {
                $this->bootstrap();
                if (!file_exists($dir = dirname($this->pidFile))) {
                    mkdir($dir, 0755, true);
                }
                // 多端口监听
                foreach ($this->listens as $listen) {
                    $swoole = $this->swoole->listen($listen->getHost(), $listen->getPort(), $listen->getSocketType());
                    $listen->bootstrap($swoole);
                }
                // 进程控制
                foreach ($this->processes as $process) {
                    $this->swoole->addProcess($process->getProcess());
                }

                $this->output->writeln(sprintf('Server: <info>%s</info>', $this->name));
                $this->output->writeln(sprintf('App version: <info>%s</info>', Server::VERSION));
                $this->output->writeln(sprintf('Swoole version: <info>%s</info>', SWOOLE_VERSION));

                $this->swoole->start();
            } catch (Throwable $e) {
                $this->output->write("<error>{$e->getMessage()}</error>\n");
            }
        }

        return 0;
    }

    /**
     * @return int
     */
    public function shutdown(): int
    {
        if (!$this->isRunning()) {
            $this->output->writeln(sprintf('Server <info>%s</info> is not running...', $this->name));
            return -1;
        }

        $pid = (int) @file_get_contents($this->getPidFile());
        if (process_kill($pid, SIGTERM)) {
            unlink($this->pidFile);
        }

        $this->output->writeln(sprintf('Server <info>%s</info> [<info>#%s</info>] is shutdown...', $this->name, $pid));
        $this->output->writeln(sprintf('PID file %s is unlink', $this->pidFile), OutputInterface::VERBOSITY_DEBUG);

        return 0;
    }

    /**
     * @return int
     */
    public function reload(): int
    {
        if (!$this->isRunning()) {
            $this->output->writeln(sprintf('Server <info>%s</info> is not running...', $this->name));
            return -1;
        }

        $pid = (int)@file_get_contents($this->getPidFile());

        posix_kill($pid, SIGUSR1);

        $this->output->writeln(sprintf('Server <info>%s</info> [<info>%s</info>] is reloading...', $this->name, $pid));

        return 0;
    }

    /**
     * @return int
     */
    public function restart(): int
    {
        $this->shutdown();
        return $this->start();
    }

    /**
     * @return int
     */
    public function status(): int
    {
        if (!$this->isRunning()) {
            $this->output->writeln(sprintf('Server <info>%s</info> is not running...', $this->name));
            return -1;
        }

        exec("ps axu | grep '{$this->name}' | grep -v grep", $output);

        // list all process
        $output = array_map(function ($v) {
            $status = preg_split('/\s+/', $v);
            unset($status[2], $status[3], $status[4], $status[6], $status[9]); //
            $status = array_values($status);
            $status[5] = $status[5] . ' ' . implode(' ', array_slice($status, 6));
            return array_slice($status, 0, 6);
        }, $output);

        // combine
        $headers = ['USER', 'PID', 'RSS', 'STAT', 'START', 'COMMAND'];
        foreach ($output as $key => $value) {
            $output[$key] = array_combine($headers, $value);
        }

        $table = new Table($this->output);
        $table
            ->setHeaders($headers)
            ->setRows($output)
        ;

        $this->output->writeln(sprintf('Server: <info>%s</info>', $this->name));
        $this->output->writeln(sprintf('App version: <info>%s</info>', Server::VERSION));
        $this->output->writeln(sprintf('Swoole version: <info>%s</info>', SWOOLE_VERSION));
        $this->output->writeln(sprintf('PID file: <info>%s</info>, PID: <info>%s</info>', $this->pidFile, (int) @file_get_contents($this->pidFile)) . PHP_EOL);
        $table->render();

        unset($table, $headers, $output);

        return 0;
    }

    /**
     * @param array $directories
     */
    public function watch(array $directories = ['.']): void
    {
        $that = $this;

        if (!$this->isRunning()) {
            $process = new Process('server watch process', function () use ($that) {
                $that->start();
            }, true);
            $process->start();
        }

        foreach ($directories as $directory) {
            $this->output->writeln(sprintf('Watching directory: ["<info>%s</info>"]', realpath($directory)));
        }

        $watcher = new Watcher($this->output);

        $watcher->watch($directories, function () use ($that) {
            $that->reload();
        });

        $watcher->run();

        process_wait();
    }

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        if (file_exists($this->config['pid_file'])) {
            return posix_kill(file_get_contents($this->config['pid_file']), 0);
        }

        return process_is_running("{$this->name} master") && port_is_running($this->port);
    }

    /**
     * Base start handle. Storage process id.
     *
     * @param \Swoole\Server $server
     */
    public function onStart(SwooleServer $server): void
    {
        if (version_compare(SWOOLE_VERSION, '1.9.5', '<')) {
            file_put_contents($this->pidFile, $server->master_pid);
            $this->pid = $server->master_pid;
        }

        process_rename($this->name . ' master');

        $this->output->writeln(sprintf("Listen: <info>%s://%s:%s</info>", $this->getScheme(), $this->getHost(), $this->getPort()));
        foreach ($this->listens as $listen) {
            $this->output->writeln(sprintf(" <info> ></info> Listen: <info>%s://%s:%s</info>", $listen->getScheme(), $listen->getHost(), $listen->getPort()));
        }

        $this->output->writeln(sprintf('PID file: <info>%s</info>, PID: <info>%s</info>', $this->pidFile, $server->master_pid));
        $this->output->writeln(sprintf('Server Master[<info>%s</info>] is started', $server->master_pid), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * Shutdown server process.
     *
     * @param \Swoole\Server $server
     */
    public function onShutdown(SwooleServer $server): void
    {
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }

        $this->output->writeln(sprintf('Server <info>%s</info> Master[<info>%s</info>] is shutdown ', $this->name, $server->master_pid), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * @param \Swoole\Server $server
     */
    public function onManagerStart(SwooleServer $server): void
    {
        process_rename($this->getName() . ' manager');

        $this->output->writeln(sprintf('Server Manager[<info>%s</info>] is started', $server->manager_pid), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * @param \Swoole\Server $server
     */
    public function onManagerStop(SwooleServer $server): void
    {
        $this->output->writeln(sprintf('Server <info>%s</info> Manager[<info>%s</info>] is shutdown.', $this->name, $server->manager_pid), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $workerId
     */
    public function onWorkerStart(SwooleServer $server, int $workerId): void
    {
        $workerName = $server->taskworker ? 'task' : 'worker';
        process_rename($this->getName() . ' ' . $workerName);
        $this->output->write(sprintf('Server %s[<info>%s</info>] is started [<info>%s</info>]', ucfirst($workerName), $server->worker_pid, $workerId).PHP_EOL);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $workerId
     */
    public function onWorkerStop(SwooleServer $server, int $workerId): void
    {
        $this->output->writeln(sprintf('Server <info>%s</info> Worker[<info>%s</info>] is shutdown', $this->name, $workerId), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $workerId
     * @param int $workerPid
     * @param int $code
     */
    public function onWorkerError(SwooleServer $server, int $workerId, int $workerPid, int $code): void
    {
        $this->output->writeln(sprintf('Server <info>%s:%s</info> Worker[<info>%s</info>] error. Exit code: [<question>%s</question>]', $this->name, $workerPid, $workerId, $code), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $taskId
     * @param int $workerId
     * @param mixed $data
     * @return mixed
     */
    public function onTask(SwooleServer $server, int $taskId, int $workerId, mixed $data): mixed
    {
        return $this->doTask($server, $data, $taskId, $workerId);
    }

    /**
     * @param \Swoole\Server $server
     * @param mixed $data
     * @param int $taskId
     * @param int $workerId
     * @return mixed
     */
    abstract public function doTask(SwooleServer $server, mixed $data, int $taskId, int $workerId): mixed;

    /**
     * @param \Swoole\Server $server
     * @param int $taskId
     * @param mixed $data
     * @return mixed
     */
    public function onFinish(SwooleServer $server, int $taskId, mixed $data): mixed
    {
        return $this->doFinish($server, $data, $taskId);
    }

    /**
     * @param \Swoole\Server $server
     * @param mixed $data
     * @param int $taskId
     * @return mixed
     */
    abstract public function doFinish(SwooleServer $server, mixed $data, int $taskId): mixed;

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     */
    public function onConnect(SwooleServer $server, int $fd, int $reactorId): void
    {
        $this->fd = $fd;

        $this->doConnect($server, $fd, $reactorId);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     */
    abstract public function doConnect(SwooleServer $server, int $fd, int $reactorId): void;

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $fromId
     */
    public function onClose(SwooleServer $server, int $fd, int $fromId): void
    {
        $this->doClose($server, $fd, $fromId);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     */
    abstract public function doClose(SwooleServer $server, int $fd, int $reactorId);
}
