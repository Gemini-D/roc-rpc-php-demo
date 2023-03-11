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
namespace App\Service;

use Hyperf\RpcServer\Annotation\RpcService;
use ROC\RPC\User;
use ROC\RPC\UserInput;
use ROC\RPC\UserInterface;

#[RpcService()]
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
