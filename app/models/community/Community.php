<?php

namespace app\models\community;

use app\models\store\StoreProduct;
use crmeb\services\SystemConfigService;
use think\facade\Db;
use crmeb\traits\ModelTrait;
use crmeb\basic\BaseModel;
use think\model\concern\SoftDelete;


class Community extends BaseModel
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
    protected $name = 'community';

    use SoftDelete,ModelTrait;
}
