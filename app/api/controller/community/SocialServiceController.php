<?php
/**
 * SocialServiceController.php
 * @author gujn [<gujnmiao@gmail.com>]
 * created on 2020/11/15
 *
 */


namespace app\api\controller\community;


use app\http\validates\user\RegisterValidates;
use app\models\community\ServiceOrder;
use app\models\community\SocialCrowd;
use app\models\community\SocialService;
use app\models\store\StoreOrder;
use app\Request;
use crmeb\repositories\ServiceOrderRepository;
use crmeb\services\CacheService;
use crmeb\services\UtilService;
use think\exception\ValidateException;

class SocialServiceController
{
    /**
     * 商品列表
     * @param Request $request
     * @return mixed
     */
    public function lst(Request $request)
    {
        $data = UtilService::getMore([
            [['crowd', 'd'], 0],
            [['type', 'd'], 0],
            ['keyword', ''],
            ['priceOrder', ''],
            ['numOrder', ''],
            [['page', 'd'], 1],
            [['limit', 'd'], 20],
            [['category_id', 'd'], 0],
        ], $request);

        $list = SocialService::getServiceList($data);
        $currentTime = date('Y-m-d H:i:s');
        foreach ($list as &$item) {
            if ($item['left_num'] == 0) {
                // 已报满
                $item['state'] = 1;
            } elseif (!is_null($item['limit_time']) && $item['limit_time'] < $currentTime) {
                // 已结束
                $item['state'] = -1;
            } else {
                // 报名中
                $item['state'] = 0;
            }
            unset($item['left_num']);
            if (!is_null($item['limit_time'])) {
                $item['limit_time'] = date('Y-m-d', strtotime($item['limit_time']));
            }

        }
        return app('json')->successful($list);
    }

    public function searchList(Request $request)
    {
        return app('json')->successful(array(
            array(
                'key' => '人群',
                'value' => 'crowd',
                'list' => SocialCrowd::where('state', 1)->field('id as value,name as `key`')->select()->toArray(),
            ),
            array(
                'key' => '类型',
                'value' => 'type',
                'list' => SocialCrowd::where('state', 1)->field('id,name as `key`')->select()->toArray(),
            ),
            array(
                'key' => '价格',
                'value' => 'priceOrder',
                'list' => array(array('value' => 'desc', 'key' => '价格从高到低'), array('value' => 'asc', 'key' => '价格从低到高')),
            ),
            array(
                'key' => '报名人数',
                'value' => 'numOrder',
                'list' => array(array('value' => 'desc', 'key' => '报名人数从高到低'), array('value' => 'asc', 'key' => '报名人数从低到高')),
            ),
        ));
    }

    public function detail(Request $request, $id)
    {
        if (!$id || !($socialService = SocialService::serviceDetail($id))) return app('json')->fail('社工服务不存在');
        $currentTime = date('Y-m-d H:i:s');
        if ($socialService['left_num'] == 0) {
            // 已报满
            $socialService['state'] = 1;
        } elseif (!is_null($socialService['limit_time']) && $socialService['limit_time'] < $currentTime) {
            // 已结束
            $socialService['state'] = -1;
        } else {
            // 报名中
            $socialService['state'] = 0;
        }
        if (!is_null($socialService['limit_time'])) {
            $socialService['limit_time'] = date('Y-m-d', strtotime($socialService['limit_time']));
        }
        $socialService['start_time'] = date('Y-m-d', strtotime($socialService['start_time']));
        $socialService['end_time'] = date('Y-m-d', strtotime($socialService['end_time']));
        $uid = $request->uid();
        $orderId = null;
        if (!empty($uid)) {
            $serviceOrderObject = ServiceOrder::where(array(
                array('uid', '=', $uid),
                array('status', '>=', 0),
                array('service_id', '=', $id),
            ))->find();
            $orderId = empty($serviceOrderObject) ? null : $serviceOrderObject['order_id'];
        }
        $socialService['order_id'] = $orderId;
        return app('json')->successful($socialService);
    }

    public function submit(Request $request, $id)
    {
        list($phone, $captcha, $name) = UtilService::postMore([
            ['phone', ''],
            ['captcha', ''],
            ['name', ''],
        ], $request, true);

        //验证手机号
        try {
            validate(RegisterValidates::class)->scene('code')->check(['phone' => $phone]);
        } catch (ValidateException $e) {
            return app('json')->fail($e->getError());
        }

        //验证验证码
        if (false) {
            $verifyCode = CacheService::get('code_' . $phone);
            if (!$verifyCode)
                return app('json')->fail('请先获取验证码');
            $verifyCode = substr($verifyCode, 0, 6);
            if ($verifyCode != $captcha)
                return app('json')->fail('验证码错误');
        }

        $uid = $request->uid();
        $order = ServiceOrder::submitOrder($id, $uid, $phone, $name);
        if ($order === false) return app('json')->fail(ServiceOrder::getErrorInfo('订单生成失败'));
        $orderId = $order['order_id'];
        if ($orderId) {
            $info['order_id'] = $orderId;
            $orderInfo = ServiceOrder::where('order_id', $orderId)->where('uid', $uid)->find();
            if (!$orderInfo) return app('json')->fail('支付订单不存在!');
            $orderInfo = $orderInfo->toArray();
            if ($orderInfo['status'] !== 0) return app('json')->fail('该订单状态不能支付!');
            if (bcsub((float)$orderInfo['pay_price'], 0, 2) <= 0) {
                //创建订单jspay支付
                $payPriceStatus = ServiceOrder::jsPayPrice($orderId, $uid);
                if ($payPriceStatus)//0元支付成功
                    return app('json')->status('success', '微信支付成功', $info);
                else
                    return app('json')->status('pay_error', StoreOrder::getErrorInfo());
            } else {
                try {
                    $jsConfig = ServiceOrderRepository::jsPay($orderId); //创建订单jspay
                } catch (\Exception $e) {
                    return app('json')->status('pay_error', $e->getMessage(), $info);
                }
                $info['jsConfig'] = $jsConfig;
                return app('json')->status('wechat_pay', '订单创建成功', $info);
            }
        } else return app('json')->fail(ServiceOrder::getErrorInfo('订单生成失败!'));
    }

    public function cancel(Request $request, $orderId)
    {

        $uid = $request->uid();
        $result = ServiceOrder::cancelOrder($orderId, $uid);
        if ($result) {
            return app('json')->successful('订单取消成功!');
        } else return app('json')->fail(ServiceOrder::getErrorInfo('订单取消失败!'));
    }

    public function paySuccess(Request $request, $orderId)
    {
        $uid = $request->uid();
        $orderInfo = ServiceOrder::where(array('uid' => $uid, 'order_id' => $orderId, 'paid' => 1))->find();
        if (empty($orderInfo)) {
            return app('json')->fail('订单不存在!');
        }
        return app('json')->successful(json_decode($orderInfo['service_info'], true));
    }

    public function order(Request $request, $orderId)
    {
        $uid = $request->uid();
        $field = "order_id,service_info,real_name,user_phone,pay_price,status,pay_type,add_time,verify_code";
        $orderInfo = ServiceOrder::where(array(
            'uid' => $uid, 'order_id' => $orderId
        ))->field($field)->find();
        if (empty($orderInfo)) {
            return app('json')->fail('订单不存在!');
        }
        $orderInfo = $orderInfo->toArray();
        $orderInfo['service_info'] = json_decode($orderInfo['service_info'],true);
        $orderInfo['service_info']['start_time'] = date('Y-m-d', strtotime($orderInfo['service_info']['start_time']));
        $orderInfo['service_info']['end_time'] = date('Y-m-d', strtotime($orderInfo['service_info']['end_time']));
        return app('json')->successful($orderInfo);
    }

    public function orderList(Request $request) {
        $uid = $request->uid();
        $data = UtilService::getMore([
            [['page', 'd'], 1],
            [['limit', 'd'], 20],
            [['status', 'd'], 0],
        ], $request);
        $list = ServiceOrder::getOrderList($data, $uid);
        foreach ($list as &$item) {
            $item['service_info'] = json_decode($item['service_info'], true);
            $item['service_info']['start_time'] = date('Y-m-d', strtotime($item['service_info']['start_time']));
            $item['service_info']['end_time'] = date('Y-m-d', strtotime($item['service_info']['end_time']));
        }
        return app('json')->successful($list);
    }

    public function pay(Request $request, $orderId)
    {
        $uid = $request->uid();
        $orderInfo = ServiceOrder::where('order_id', $orderId)->where('uid', $uid)->find();
        if (!$orderInfo) return app('json')->fail('支付订单不存在!');
        $orderInfo = $orderInfo->toArray();
        if ($orderInfo['status'] !== 0) return app('json')->fail('该订单状态不能支付!');
        try {
            $jsConfig = ServiceOrderRepository::jsPay($orderId); //创建订单jspay
        } catch (\Exception $e) {
            return app('json')->status('pay_error', $e->getMessage(), array('order_id' => $orderInfo['order_id']));
        }
        $info['jsConfig'] = $jsConfig;
        return app('json')->status('wechat_pay', ['jsConfig' => $jsConfig, 'order_id' => $orderInfo['order_id']]);
    }
}