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
        $services = [
            UserInterface::class => ['127.0.0.1', 9504],
        ];

        foreach ($services as $interface => [$host, $port]) {
            $consumers[] = static::getDefaultConsumer($interface, $host, $port);
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
                    // ??????????????????????????? Server ????????????????????????????????????????????????????????????????????????
                    'package_max_length' => 1024 * 1024 * 2,
                ],
                // ??????????????????????????? 2
                'retry_count' => 2,
                // ?????????????????????
                'retry_interval' => 10,
                // ???????????????????????????
                'client_count' => 4,
                // ????????????
                'heartbeat' => 20,
            ],
        ];
    }
}
