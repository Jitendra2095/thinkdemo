<?php

namespace app\index\controller;
use app\index\model\Alipay;
use think\Controller;
use think\Db;
use think\Image;
use think\Request;
use think\Session;

class Pay extends Controller
{
    public function hpay(){
        $order=input('order');
        $order_info=Db::table('user_recharge_log')->where('ordersn',$order)->find();
        if(empty($order_info)){
            $this->error('无订单');
        }
        $pay = new Alipay();
        return $pay->h5createOrder(['op_type'=>1,'out_trade_no'=>$order,'total_amount'=>$order_info['money'],'subject'=>'充值钻石']);
        
    }
    public function vip_pay(){
        $order=input('order');
        $order_info=Db::table('user_open_vip_log')->where('ordersn',$order)->find();
        if(empty($order_info)){
            $this->error('无订单');
        }
        $pay = new Alipay();
        return $pay->h5createOrder(['op_type'=>2,'out_trade_no'=>$order,'total_amount'=>$order_info['money'],'subject'=>'开通VIP']);
        
    }
    public function alipay(){
        $pay = new Alipay();
        $pay->notify();
    }

    public function vipAlipay(){
        $pay = new Alipay();
        $pay->vipNotify();
    }

}