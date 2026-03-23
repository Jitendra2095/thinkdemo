<?php

namespace app\common\command;


use app\common\entity\User;
use app\common\entity\UserDiscountList;
use app\common\entity\UserGameTicket;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;


/**
 * 检查门票是否过期  每分钟执行
 *
 */
class UpdateUserVIP extends Command
{

    protected function configure()
    {
        //设置参数
        $this->setName('update_user_vip')
            ->setDescription('检查vip是否过期以及赠送门票');
    }

    protected function execute(Input $input, Output $output)
    {
        $user = Db::name('user')->where("vip",1)->select();
        $times = time();
        $vip_data = [];
        $user_discount_data = [];
        $user_ticket_data = [];
        //配置
//        赠送会员门票ID
        $give_user_ticket_id = Db::name('config')->where('key','ticket_id')->value('value');
        //门票详情
        $game_ticket = Db::name('game_ticket')->where('id',$give_user_ticket_id)->find();
        //赠送折扣卷数量
        $give_user_discount_num = Db::name('config')->where('key','discount')->value('value');
        //折扣卷详情
        $discount_list = Db::name('discount_list')->find();

        if(!empty($user)){
            foreach ($user as $value){
                //过期
                if($value['vip_endtime'] <= $times){
                    $vip_data[] = ['id'=>$value['id'],'vip'=>0];
                }else{
//                    VIP未过期
                    //上次赠送时间是否过了30天
                    if($value['give_time'] + (86400 * 30) <= $times){
                        //赠送折扣卷
                        if($discount_list['discount'] > 0 && $give_user_discount_num > 0){
                            for ($i = 1;$i <= $give_user_discount_num;$i++){
                                $user_discount_data[] = [
                                    'uid'=>$value['id'],
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
                                    'uid'=>$value['id'],
                                    'ticket_id'=>$give_user_ticket_id,
                                    'price'=>$game_ticket['price'],
                                    'is_give'=>1,
                                    'createtime'=>$times,
                                    'orvertime'=>$times + (86400 * 30),
                                ];
                            }
                        }
                    }
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

            if($vip_data){
                $user_model = new User();
                $user_model->isUpdate()->saveAll($vip_data);
            }
        }


        $output->writeln('检查vip是否过期以及赠送门票，执行完成');die;

    }

}