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
namespace ROC\RPC;

class User implements \JsonSerializable
{
    public function __construct(public mixed $id, public string $name, public mixed $gender)
    {
    }

    public function jsonSerialize(): mixed
    {
        return ['id' => $this->id, 'name' => $this->name, 'gender' => $this->gender];
    }

    public static function jsonDeSerialize(array $data): static
    {
        return new User($data['id'], $data['name'], $data['gender']);
    }
}
