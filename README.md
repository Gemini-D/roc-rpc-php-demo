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


