<?php

namespace app\admin\controller;


use crmeb\utils\Redis;

class Test
{
    public function index()
    {

        $redis = Redis::instance();
        var_dump($redis->get(['CRMEB','TESD']));
    }

    public function test()
    {

    }
}