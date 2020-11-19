<?php
/**
 * SocialCategory.php
 * @author gujn [<gujnmiao@gmail.com>]
 * created on 2020/11/14
 *
 */


namespace app\models\community;


use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\model\concern\SoftDelete;

class ServiceCategory extends BaseModel
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
    protected $name = 'service_category';

    use ModelTrait;
}