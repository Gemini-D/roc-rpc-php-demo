# ROC Demo

## 安装 RPC 生成工具

[roc-generator](https://github.com/hyperf/roc-generator)

## 编写 rpc.proto 文件

```protobuf
syntax = "proto3";

option php_namespace = "ROC\\RPC";

package rpc;

service UserInterface {
  rpc info(UserInput) returns (User) {}
}

message UserInput{
  uint64 id = 1;
}

message User {
  uint64 id = 1;
  string name = 2;
  uint32 gender = 3;
}
```

## 根据文件生成代码

```shell
cd rpc
roc-php gen:roc rpc.proto -O src
```

接下来，我们就可以在 rpc/src 目录下看到了生成的文件。

## 完善自定义 rpc 组件代码

> 正常情况下，我们需要按照官网配置，增加对应配置文件，但这样会比较麻烦，所以我们通过事件的方式，将对应配置自动加载进来

### 编写监听器

增加 `rpc/src/Listener/BootConsumerListener`.

```php
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
```

## 编写RPC服务端代码

### 增加对应仓库

首先我们现在 server/composer.json 中增加对应的仓库配置

```json
{
    "repositories": {
        "rpc": {
            "type": "path",
            "url": "../rpc"
        }
    }
}
```

接下来再通过执行脚本，载入对应组件包

```shell
composer require roc/rpc
```

### 导入 RPC 相关组件

> 因为相关的代码，还没有发布 Release 版本，所以需要导入 x-dev 包

```shell
composer require "hyperf/rpc-client:3.0.x-dev" -W
composer require "hyperf/rpc:3.0.x-dev" -W
composer require "hyperf/rpc-multiplex:3.0.x-dev" -W
composer require "hyperf/rpc-server:3.0.x-dev" -W
```

### 增加 RPC 服务配置

让我们修改 `config/server.php` 文件，增加 rpc 相关配置

```php
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
use Hyperf\Engine\Constant\SocketType;
use Hyperf\Server\Event;
use Hyperf\Server\Server;

return [
    'mode' => SWOOLE_BASE,
    'type' => Hyperf\Server\CoroutineServer::class,
    'servers' => [
        [
            'name' => 'http',
            'type' => Server::SERVER_HTTP,
            'host' => '0.0.0.0',
            'port' => 9501,
            'sock_type' => SocketType::TCP,
            'callbacks' => [
                Event::ON_REQUEST => [Hyperf\HttpServer\Server::class, 'onRequest'],
            ],
        ],
        [
            'name' => 'rpc',
            'type' => Server::SERVER_BASE,
            'host' => '0.0.0.0',
            'port' => 9504,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_RECEIVE => [Hyperf\RpcMultiplex\TcpServer::class, 'onReceive'],
            ],
            'settings' => [
                'open_length_check' => true,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 4,
                'package_max_length' => 1024 * 1024 * 2,
            ],
        ],
    ],
    'settings' => [
        'enable_coroutine' => true,
        'worker_num' => 4,
        'pid_file' => BASE_PATH . '/runtime/hyperf.pid',
        'open_tcp_nodelay' => true,
        'max_coroutine' => 100000,
        'open_http2_protocol' => true,
        'max_request' => 0,
        'socket_buffer_size' => 2 * 1024 * 1024,
        'package_max_length' => 2 * 1024 * 1024,
    ],
    'callbacks' => [
        Event::ON_BEFORE_START => [Hyperf\Framework\Bootstrap\ServerStartCallback::class, 'beforeStart'],
        Event::ON_WORKER_START => [Hyperf\Framework\Bootstrap\WorkerStartCallback::class, 'onWorkerStart'],
        Event::ON_PIPE_MESSAGE => [Hyperf\Framework\Bootstrap\PipeMessageCallback::class, 'onPipeMessage'],
        Event::ON_WORKER_EXIT => [Hyperf\Framework\Bootstrap\WorkerExitCallback::class, 'onWorkerExit'],
    ],
];

```

### 增加服务实现

新增 `app/RPC/UserService.php` 文件，增加以下代码

```php
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
namespace App\RPC;

use Hyperf\RpcMultiplex\Constant;
use Hyperf\RpcServer\Annotation\RpcService;
use ROC\RPC\User;
use ROC\RPC\UserInput;
use ROC\RPC\UserInterface;

#[RpcService(name: UserInterface::class, server: 'rpc', protocol: Constant::PROTOCOL_DEFAULT)]
class UserService implements UserInterface
{
    public function info(UserInput $input): User
    {
        return new User(
            $input->id,
            'Hyperf',
            1
        );
    }
}

```

### 配置监听器

修改 `server/config/autoload/listeners.php`

```php
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
return [
    Hyperf\ExceptionHandler\Listener\ErrorExceptionHandler::class,
    Hyperf\Command\Listener\FailToHandleListener::class,
    ROC\RPC\Listener\BootConsumerListener::class => 99,
];

```

## 测试 RPC 效果

### 编写 RegisterProtocolListener

> 因为默认的 RPC 组件，使用的是默认的 Normalizer，而代码生成的接口文件，是需要支持 Json 转 Object，所以我们需要进行替换

```php
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

```

### 编写测试代码

```php
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
namespace App\Controller;

use Hyperf\Di\Annotation\Inject;
use ROC\RPC\UserInput;
use ROC\RPC\UserInterface;

class IndexController extends Controller
{
    #[Inject]
    protected UserInterface $user;

    public function index()
    {
        $result = $this->user->info(new UserInput(1));

        return $this->response->success($result);
    }
}

```

### 启动服务

```shell
php bin/hyperf.php start
```

### 访问接口

```shell
$ curl http://127.0.0.1:9501/
{"code":0,"data":{"id":1,"name":"Hyperf","gender":1}}
```

