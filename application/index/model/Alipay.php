<?php

namespace app\index\model;

use app\common\entity\UserDiscountList;
use app\common\entity\UserGameTicket;
use app\common\PHPMailer\Exception;
use think\Db;
use think\Request;
use Yansongda\Pay\Pay;
use Yansongda\Pay\Log;

class Alipay {

    protected $config = [];

    public function __construct()
    {
        $this->config = \config('alipay');
    }
    public function h5createOrder($data = [])
    {
        if($data['op_type'] == 2){
            $this->config['notify_url'] = Request::instance()->domain()."/index/pay/vipAlipay";
            $this->config['return_url'] = Request::instance()->domain()."/index/pay/vipAlipay";
        }
        $order = [
            'out_trade_no' => $data['out_trade_no'],
            'total_amount' => $data['total_amount'],
            'subject' => $data['subject'],
        ];

        $alipay = Pay::alipay($this->config)->wap($order);
        return $alipay->getContent();
    }
    public function createOrder($data = [])
    {
        if($data['op_type'] == 2){
            $this->config['notify_url'] = Request::instance()->domain()."/index/pay/vipAlipay";
            $this->config['return_url'] = Request::instance()->domain()."/index/pay/vipAlipay";
        }
        $order = [
            'out_trade_no' => $data['out_trade_no'],
            'total_amount' => $data['total_amount'],
//            'total_amount' => 0.1,
            'subject' => $data['subject'],
        ];

        $alipay = Pay::alipay($this->config)->app($order);

        return $alipay->getContent();
//        return $alipay->send();// laravel 框架中请直接 `return $alipay`
    }

//    public function return()
//    {
//        $data = Pay::alipay($this->config)->verify(); // 是的，验签就这么简单！
//
//        // 订单号：$data->out_trade_no
//        // 支付宝交易号：$data->trade_no
//        // 订单总金额：$data->total_amount
//    }

    /**
     * 充值钻石异步回调
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function notify()
    {
//        $content = Db::name('alipay_notify')->where('id',50)->value('content');
//
//        $content = json_decode($content,true);
//        $data = $content;
//        var_dump($content);
//        var_dump($data);die;
//        var_dump($this->config);die;
        $request = input('post.');
        Db::name('alipay_notify')->insert(['op_type'=>1,'content'=>json_encode($request)]);
        $alipay = Pay::alipay($this->config);
        $data = $alipay->verify(); // 是的，验签就这么简单！
        if($data['trade_status'] == "TRADE_SUCCESS" || $data['trade_status'] == "TRADE_FINISHED"){
            Db::startTrans();
            try {
                $user_recharge_log = Db::name('user_recharge_log')->where('ordersn',$data['out_trade_no'])->find();

                if(!empty($user_recharge_log)){
                    $user_model = new User();
                    $agent = [];
                    //                    var_dump($user_recharge_log);die;
                    if($user_recharge_log['money'] > 0 && $user_recharge_log['num'] > 0 && $user_recharge_log['status'] == 0){
                        $commission_level = Db::name('commission_level')->select();
                        //充值钻石
                        $result = $user_model->setUserWallet($user_recharge_log['uid'],'credit1',1,2,$user_recharge_log['num'],'充值钻石');
                        if(!$result){
                            throw new Exception("充值失败(充值钻石)");
                        }
                        $user_model->getUserAgents($user_recharge_log['uid'],$agent);
                        if(!empty($agent)){
                            //充值佣金
                            foreach ($agent as $value){
                                if($value['commission_level'] > 0){
                                    $recharge_commission = $commission_level[($value['commission_level'] - 1)]['recharge_commission'];
                                    if($recharge_commission > 0){
                                        $commission_money = sprintf("%.2f",($user_recharge_log['money'] * $recharge_commission) / 100);
                                        if($commission_money > 0){
                                            $result = $user_model->setUserWallet($value['id'],'commission',6,2,$commission_money,['uid'=>$user_recharge_log['uid'],'msg'=>"团队奖励"]);
                                            if($result['code'] == 400){
                                                throw new Exception($result['msg']."(团队奖励)");
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        //修改状态
                        $result = Db::name('user_recharge_log')->where('id',$user_recharge_log['id'])->update(['status'=>1]);
                        if(!$result){
                            throw new Exception("充值失败(修改状态)");
                        }
                    }
                }
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                $log = "<br />\r\n\r\n".'==================='."\r\n".date("Y-m-d H:i:s")."\r\n".$e->getMessage();
                @file_put_contents('logs/notify_log.txt', $log, FILE_APPEND);
                return $alipay->success()->send();
            }
        }
//            $request = input('post.');
//            Db::name('alipay_notify')->insert(['content'=>json_encode($request)]);
        //写入文件做日志 调试用
//            $log = "<br />\r\n\r\n".'==================='."\r\n".date("Y-m-d H:i:s")."\r\n".json_encode($request);
//            @file_put_contents('upload/alipay.txt', $log, FILE_APPEND);

//            Db::name('user_wallet')->where('uid',2)->update(['commission'=>2]);
        // 请自行对 trade_status 进行判断及其它逻辑进行判断，在支付宝的业务通知中，只有交易通知状态为 TRADE_SUCCESS 或 TRADE_FINISHED 时，支付宝才会认定为买家付款成功。
        // 1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号；
        // 2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额）；
        // 3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email）；
        // 4、验证app_id是否为该商户本身。
        // 5、其它业务逻辑情况

//            Log::debug('Alipay notify', $data->all());
        return $alipay->success()->send();// laravel 框架中请直接 `return $alipay->success()`
    }

    /**
     *充值VIP异步回调
     */
    public function vipNotify(){
        $this->config['notify_url'] = Request::instance()->domain()."/index/pay/vipAlipay";
        $this->config['return_url'] = Request::instance()->domain()."/index/pay/vipAlipay";
//        $request = input('post.');
//        Db::name('alipay_notify')->insert(['content'=>json_encode($request)]);
//        echo "success";die;
//        $content = Db::name('alipay_notify')->where('id',46)->value('content');
//        $content = json_decode($content,true);
//        $data = $content;
//        $request = input('post.');
//        Db::name('alipay_notify')->insert(['op_type'=>2,'content'=>json_encode($request)]);
        $alipay = Pay::alipay($this->config);
        $data = $alipay->verify(); // 是的，验签就这么简单！
        if($data['trade_status'] == "TRADE_SUCCESS" || $data['trade_status'] == "TRADE_FINISHED"){
            $user_open_vip_log = Db::name('user_open_vip_log')->where('ordersn',$data['out_trade_no'])->find();
            Db::startTrans();
            try {
                if(!empty($user_open_vip_log) && $user_open_vip_log['status'] == 0){
                    $times = time();
                    $user = Db::name('user')->where(['id'=>$user_open_vip_log['uid']])->field('vip,give_time,vip_endtime')->find();
                    if($user['vip']){
                        if($user['vip_endtime'] > $times){
                            //VIP未过期
                            //VIP结束时间
                            $vip_endtime =  $user['vip_endtime'] + ($user_open_vip_log['day_num'] * 86400);
                        }else{
                            //VIP已过期
                            //VIP结束时间
                            $vip_endtime =  $times + ($user_open_vip_log['day_num'] * 86400);

                        }
                        $result = Db::name('user')->where('id',$user_open_vip_log['uid'])->update(['vip_endtime'=>$vip_endtime]);
                        if(!$result){
                            throw new Exception('修改VIP结束时间失败');
                        }
                    }else{
                        //未开通过VIP
                        //VIP结束时间
                        $vip_endtime =  $times + ($user_open_vip_log['day_num'] * 86400);
                        $result = Db::name('user')->where('id',$user_open_vip_log['uid'])->update(['vip_endtime'=>$vip_endtime,'vip'=>1]);
                        if(!$result){
                            throw new Exception('修改VIP结束时间失败');
                        }
                        //赠送门票和折扣卷
                        if(empty($user['give_time']) || intval($user['give_time']) <= 0){
                            //配置
                            //赠送会员门票ID
                            $give_user_ticket_id = Db::name('config')->where('key','ticket_id')->value('value');
                            //门票详情
                            $game_ticket = Db::name('game_ticket')->where('id',$give_user_ticket_id)->find();
                            //赠送折扣卷数量
                            $give_user_discount_num = Db::name('config')->where('key','discount')->value('value');
                            //折扣卷详情
                            $discount_list = Db::name('discount_list')->find();
                            $user_ticket_data = [];
                            $user_discount_data = [];
                            //赠送折扣卷
                            if($discount_list['discount'] > 0 && $give_user_discount_num > 0){
                                for ($i = 1;$i <= $give_user_discount_num;$i++){
                                    $user_discount_data[] = [
                                        'uid'=>$user_open_vip_log['uid'],
                                        'discount_id'=>$discount_list['id'],
                                        'discount'=>$discount_list['discount'],
                                        'createtime'=>$times,
                                    ];
                                }
                            }
                            //赠送赏金卷
                            if($give_user_ticket_id && $game_ticket){
                                for ($i = 1;$i <= 5;$i++){
                                    $user_ticket_data[] = [
                                        'uid'=>$user_open_vip_log['uid'],
                                        'ticket_id'=>$give_user_ticket_id,
                                        'price'=>$game_ticket['price'],
                                        'is_give'=>1,
                                        'createtime'=>$times,
                                        'orvertime'=>$times + (86400 * 30),
                                    ];
                                }
                            }

                            if($user_ticket_data){
                                $user_discount_list_model = new UserGameTicket();
                                $user_discount_list_model->isUpdate(false)->saveAll($user_ticket_data);
                            }

                            if($user_discount_data){
                                $user_discount_list_model = new UserDiscountList();
                                $user_discount_list_model->isUpdate(false)->saveAll($user_discount_data);
                            }

                            $result = Db::name('user')->where('id',$user_open_vip_log['uid'])->update(['give_time'=>$times]);
                            if(!$result){
                                throw new Exception('修改赠送时间失败');
                            }
                        }
                    }
                    $result = Db::name('user_open_vip_log')->where('id',$user_open_vip_log['id'])->update(['status'=>1]);
                    if(!$result){
                        throw new Exception('修改记录状态失败');
                    }
                }
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                $log = "<br />\r\n\r\n".'==================='."\r\n".date("Y-m-d H:i:s")."\r\n".$e->getMessage();
                @file_put_contents('logs/notify_log.txt', $log, FILE_APPEND);
                return $alipay->success()->send();
            }
        }

        Log::debug('Alipay notify', $data->all());

        return $alipay->success()->send();// laravel 框架中请直接 `return $alipay->success()`
    }

}
