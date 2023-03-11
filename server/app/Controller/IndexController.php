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
