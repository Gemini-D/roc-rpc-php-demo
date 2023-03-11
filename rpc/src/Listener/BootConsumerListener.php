<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace ROC\RPC\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\RpcMultiplex\Constant;
use Psr\Container\ContainerInterface;
use ROC\RPC\UserInterface;

/**
 * Must handle the event before `Hyperf\RpcClient\Listener\AddConsumerDefinitionListener`.
 * You can set ROC\RPC\Listener\BootConsumerListener::class => 99.
 */
class BootConsumerListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void
    {
        $config = $this->container->get(ConfigInterface::class);

        $consumers = $config->get('services.consumers', []);
        $names = array_column($consumers, 'name');

        $services = [
            UserInterface::class => ['127.0.0.1', 9504],
        ];

        foreach ($services as $interface => [$host, $port]) {
            if (! in_array($interface, $names)) {
                $consumers[] = static::getDefaultConsumer($interface, $host, $port);
            }
        }

        $names = array_column($consumers, 'name');
        if (count($names) !== count(array_unique($names))) {
            throw new \InvalidArgumentException('RPC 组件中，存在重复的 Service 名，请检查并修改后重试');
        }

        $config->set('services.consumers', $consumers);
    }

    public static function getDefaultConsumer(string $interface, string $host, int $port): array
    {
        return [
            'name' => $interface,
            'service' => $interface,
            'id' => $interface,
            'protocol' => Constant::PROTOCOL_DEFAULT,
            'load_balancer' => 'random',
            'nodes' => [
                ['host' => $host, 'port' => $port],
            ],
            'options' => [
                'connect_timeout' => 5.0,
                'recv_timeout' => 5.0,
                'settings' => [
                    // 包体最大值，若小于 Server 返回的数据大小，则会抛出异常，故尽量控制包体大小
                    'package_max_length' => 1024 * 1024 * 2,
                ],
                // 重试次数，默认值为 2
                'retry_count' => 2,
                // 重试间隔，毫秒
                'retry_interval' => 10,
                // 多路复用客户端数量
                'client_count' => 4,
                // 心跳间隔
                'heartbeat' => 20,
            ],
        ];
    }
}
