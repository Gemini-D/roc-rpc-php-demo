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

class UserInput implements \JsonSerializable
{
    public function __construct(public mixed $id)
    {
    }

    public function jsonSerialize(): mixed
    {
        return ['id' => $this->id];
    }

    public static function jsonDeSerialize(array $data): static
    {
        return new UserInput($data['id']);
    }
}
