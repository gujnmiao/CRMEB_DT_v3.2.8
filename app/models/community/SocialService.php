<?php
/**
 * SocialService.php
 * @author gujn [<gujnmiao@gmail.com>]
 * created on 2020/11/15
 *
 */


namespace app\models\community;


use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;

class SocialService extends BaseModel
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
    protected $name = 'social_service';

    use ModelTrait;

    public static function getServiceList($data)
    {
        $priceOrder = $data['priceOrder'];
        $numOrder = $data['numOrder'];
        $crowd = $data['crowd'];
        $type = $data['type'];
        $categoryId = $data['category_id'];
        $page = $data['page'];
        $limit = $data['limit'];
        $keyword = $data['keyword'];
        $model = self::alias('s')
            ->join('social_crowd c', 's.crowd_id = c.id')
            ->join('social_type t', 's.type_id = t.id');
        if (!empty($crowd)) {
            $model->where('s.crowd_id', $crowd);
        }
        if (!empty($type)) {
            $model->where('s.type_id', $type);
        }
        if (!empty($categoryId)) {
            $model->where('s.category_id', $categoryId);
        }
        if ($keyword != '') {
            $model->where('s.title', 'like', "%$keyword%");
        }
        $currentTime = date('Y-m-d H:i:s');
        $model->where('s.state', 1)->where('s.report_time', '<=', $currentTime);
        if (!empty($priceOrder)) {
            $model->order('s.price', $priceOrder);
        }

        if (!empty($numOrder)) {
            $model->order('s.left_num', $numOrder);
        }

        $field = 's.id as service_id,s.category_id,s.picture,
        s.title,s.price,s.limit_time,t.name as type_name,s.left_num';
        return $model->field($field)->page($page, $limit)->select()->toArray();
    }

    public static function serviceDetail($serviceId)
    {
        $field = 's.id as service_id,s.title,s.picture,s.price,s.total_num,s.left_num,s.limit_time,
        s.price,s.start_time,s.end_time,s.desc,ca.name as category_name,cm.name as community_name,
        cm.longitude,cm.latitude,cm.address';
        $service = self::alias('s')
            ->join('social_crowd c', 's.crowd_id = c.id')
            ->join('service_category ca', 's.category_id = ca.id')
            ->join('community cm', 's.community_id = cm.id')
            ->where('s.id', $serviceId)
            ->where('s.state', 1)
            ->field($field)
            ->find();
        if ($service) return $service->toArray();
        else return false;
    }

    public static function socialServiceInfo($id)
    {
        $currentTime = date('Y-m-d H:i:s');
        $field = 's.start_time,s.end_time,s.title,s.price,s.picture,c.latitude,c.longitude,c.name,c.address';
        return self::alias('s')
            ->join('community c', 'c.id = s.community_id')
            ->where(array(
                array('s.id', '=', $id),
                array('s.state', '=', 1),
                array('s.left_num', '>=', 1),
                array('s.report_time', '<=', $currentTime)
            ))->whereRaw("(s.limit_time is null) OR ( s.limit_time >= '$currentTime')")
            ->field($field)
            ->lock(true)
            ->find();
    }
}