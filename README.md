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
