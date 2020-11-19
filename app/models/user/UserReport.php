<?php
/**
 * Created by CRMEB.
 * Copyright (c) 2017~2019 http://www.crmeb.com All rights reserved.
 * Author: liaofei <136327134@qq.com>
 * Date: 2019/3/27 21:44
 */

namespace app\models\user;


use crmeb\traits\ModelTrait;
use crmeb\basic\BaseModel;

/**
 * TODO 用户消费新增金额明细 model
 * Class UserBill
 * @package app\models\user
 */
class UserReport extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'user_report';

    use ModelTrait;
}
