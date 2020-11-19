<?php

namespace crmeb\repositories;

use app\models\community\ServiceOrder;
use app\models\community\SocialService;
use app\models\user\User;
use app\models\user\UserBill;
use app\models\user\WechatUser;
use app\admin\model\order\ServiceOrder as AdminServiceOrder;
use crmeb\services\MiniProgramService;
use crmeb\services\WechatService;

/**
 * Class ServiceOrderRepository
 * @package crmeb\repositories
 */
class ServiceOrderRepository
{

    /**
     * TODO 小程序JS支付
     * @param $orderId
     * @param string $field
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function jsPay($orderId, $field = 'order_id')
    {
        if (is_string($orderId))
            $orderInfo = ServiceOrder::where($field, $orderId)->find();
        else
            $orderInfo = $orderId;
        if (!$orderInfo || !isset($orderInfo['paid'])) exception('支付订单不存在!');
        if ($orderInfo['paid']) exception('支付已支付!');
        if ($orderInfo['total_price'] <= 0) exception('该支付无需支付!');
        $openid = WechatUser::getOpenId($orderInfo['uid']);
        $serviceInfo = json_decode($orderInfo['service_info'], true);
        $bodyContent = $serviceInfo['title'] ?: '社工服务';
        $site_name = sys_config('site_name');
        if (!$bodyContent && !$site_name) exception('支付参数缺少：请前往后台设置->系统设置-> 填写 网站名称');
        return MiniProgramService::jsPay($openid, $orderInfo['order_id'], $orderInfo['pay_price'], 'service', ServiceOrder::getSubstrUTf8($site_name . ' - ' . $bodyContent, 30));
    }
}