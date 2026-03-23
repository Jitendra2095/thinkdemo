<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

return [
//    'app\common\command\OneClick',//任务托管每分钟执行
//    'app\common\command\TeamUpgrade',//计算会员等级
//    'app\common\command\UpdateTaskStatus',//自动审核任务
//    'app\common\command\TeamReward',//任务三级分佣奖励
//    'app\common\command\UpdateTiktokStatus',//抖音信息自动审核
    'app\common\command\CheekTicket',//检查门票是否过期
    'app\common\command\CheekRoom',//检查房间状态
    'app\common\command\UpdateUserVIP',//检查房间状态
    'app\common\command\UserTips',//检查房间状态


];

