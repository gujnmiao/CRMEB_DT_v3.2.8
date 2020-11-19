<?php
/**
 * ServiceOrder.php
 * @author gujn [<gujnmiao@gmail.com>]
 * created on 2020/11/17
 *
 */


namespace app\models\community;


use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;


class ServiceOrder extends BaseModel
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
    protected $name = 'service_order';

    use ModelTrait;


    /**
     * 生成订单唯一id
     * @param $uid 用户uid
     * @return string
     */
    public static function getNewOrderId()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');
        $orderId = 'sc' . $msectime . mt_rand(10000, 99999);
        if (self::be(['order_id' => $orderId])) $orderId = 'sc' . $msectime . mt_rand(10000, 99999);
        return $orderId;
    }

    public static function getVerifyCode()
    {
        list($msec, $sec) = explode(' ', microtime());
        $num = bcadd(time(), mt_rand(10, 999999), 0) . '' . substr($msec, 2, 3);//生成随机数
        if (strlen($num) < 12)
            $num = str_pad((string)$num, 12, 0, STR_PAD_RIGHT);
        else
            $num = substr($num, 0, 12);
        return $num;
    }

    public static function submitOrder($id, $uid, $phone, $name)
    {
        self::beginTrans();
        try{
            // 判断时间人数状态
            $serviceObject = SocialService::socialServiceInfo($id);
            if (empty($serviceObject)) {
                return self::setErrorInfo('社工服务不存在或已结束!');
            }
            // 开始报名
            $serviceOrderObject = ServiceOrder::where(
                array(
                    array('uid', '=', $uid),
                    array('service_id', '=', $id),
                    array('status', '>=', 0),
                )
            )->find();
            if (!empty($serviceOrderObject)) {
                return self::setErrorInfo('社工服务已参与报名!');
            }
            $totalPrice = is_null($serviceObject['price']) ? 0 : $serviceObject['price'];
            $orderInfo = array(
                'order_id' =>  self::getNewOrderId(),
                'uid' => $uid,
                'service_id' => $id,
                'real_name' => $name,
                'user_phone' => $phone,
                'total_price' => $totalPrice,
                'pay_price' => $totalPrice,
                'status' => 0,
                'pay_type' => 'weixin',
                'add_time' => date('Y-m-d H:i:s'),
                'total_num' => 1,
                'verify_code' => self::getVerifyCode(),
                'service_info' => json_encode($serviceObject->toArray(), JSON_UNESCAPED_UNICODE)
            );

            $serviceOrderModel = self::create($orderInfo);
            if (!$serviceOrderModel || !SocialService::where('id', $id)->dec('left_num')->update()) {
                return self::setErrorInfo('订单生成失败!', true);
            }
            self::commitTrans();
            return $serviceOrderModel;
        } catch (\PDOException $e) {
            self::rollbackTrans();
            return self::setErrorInfo('生成订单时SQL执行错误错误原因：' . $e->getMessage());
        } catch (\Exception $e) {
            self::rollbackTrans();
            return self::setErrorInfo('生成订单时系统错误错误原因：' . $e->getMessage());
        }
    }


    public static function cancelOrder($orderId, $uid)
    {
        self::beginTrans();
        try{
            $condition = array(
                array('order_id', '=', $orderId),
                array('uid', '=', $uid),
                array('status', '=', 0),
            );
            $serviceOrderObject = self::where($condition)->find();
            if (empty($serviceOrderObject)) {
                return self::setErrorInfo('社工服务订单不存在!');
            }
            $step1 = self::where($condition)->update(array('status' => -1));
            $step2 = SocialService::where('id', $serviceOrderObject['service_id'])->inc('left_num')->update();
            if (!$step1 || !$step2) {
                return self::setErrorInfo('订单取消失败!', true);
            }
            self::commitTrans();
            return true;
        } catch (\PDOException $e) {
            self::rollbackTrans();
            return self::setErrorInfo('取消订单时SQL执行错误错误原因：' . $e->getMessage());
        } catch (\Exception $e) {
            self::rollbackTrans();
            return self::setErrorInfo('取消订单时系统错误错误原因：' . $e->getMessage());
        }
    }

    public static function jsPayPrice($orderId, $paytype = 'weixin')
    {
        $orderInfo = self::where('order_id', $orderId)->find();
        if (!$orderInfo) return self::setErrorInfo('订单不存在!');
        if ($orderInfo['paid']) return self::setErrorInfo('该订单已支付!');
        $result = self::where('order_id', $orderId)->update(['status' => 1, 'pay_type' => $paytype, 'pay_time' => date('Y-m-d H:i:s')]);//订单改为支付
        return false !== $result;
    }

    public static function getOrderList($data, $uid)
    {
        $page = $data['page'];
        $limit = $data['limit'];
        $status = $data['status'];
        $model = self::where('uid', $uid);
        if (!empty($status)) {
            $model->where('status', $status);
        }
        $field = 'order_id,service_info,total_num,status';
        return $model->field($field)->page($page, $limit)->order('add_time desc')->select()->toArray();
    }
}