<?php
namespace app\admin\validate;

use think\Request;
use think\Validate;

class Ticket extends Validate
{

    protected $rule = [
        'ticketname' => 'require',
//        'game_type' => 'require',
//        'match_type' => 'require',
        'ticket_type' => 'require',
        'ticket_match_type' => 'require',
        'commission' => 'require',
        'price' => 'require',
        'vip_price' => 'require',
    ];

    protected $message = [
        'ticketname.require' => '门票名称不能为空',
        'ticket_type.require' => '请选择门票类型',
        'ticket_match_type.require' => '请填写门票比赛类型',
        'price.require' => '请填写价格',
        'vip_price.require' => '请填写VIP价格',
        'commission.require' => '请填写佣金',
//        'game_type.require'  => '请选择游戏分类',
//        'match_type.require'  => '请选择比赛分类',
    ];


}