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
namespace App\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\Rpc\ProtocolManager;
use Hyperf\RpcMultiplex\Constant;
use Hyperf\RpcMultiplex\DataFormatter;
use Hyperf\RpcMultiplex\Packer\JsonPacker;
use Hyperf\RpcMultiplex\PathGenerator;
use Hyperf\RpcMultiplex\Transporter;
use Hyperf\Utils\Serializer\JsonDeNormalizer;
use Psr\Container\ContainerInterface;

#[Listener(priority: -1)]
class RegisterProtocolListener implements ListenerInterface
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void
    {
        $this->container->get(ProtocolManager::class)->register(Constant::PROTOCOL_DEFAULT, [
            'packer' => JsonPacker::class,
            'transporter' => Transporter::class,
            'path-generator' => PathGenerator::class,
            'data-formatter' => DataFormatter::class,
            'normalizer' => JsonDeNormalizer::class,
        ]);
    }
}
