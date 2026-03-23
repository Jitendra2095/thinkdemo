<?php
namespace app\common\command;


use app\common\entity\ConfigUserLevelModel;
use app\common\entity\MyWallet;
use app\common\entity\MyWalletLog;
use app\common\service\Task\Service;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;


/**
 * 计算三级分佣奖励
 *
 * 每天01:00定时执行
 */
class TeamReward extends Command
{
    protected function configure()
    {
        $this->setName('team_reward')
            ->setDescription('任务三级分佣奖励');
    }
    protected function execute(Input $input, Output $output)
    {
        Db('reward_user')
            ->field('id,uid,status,count_time')
            ->where('status',1)
//            ->whereTime('count_time','not between',[$start_time,$end_time])
            ->chunk(100,function ($data){
                foreach ($data as $k =>$v){
                    if($v['uid']){
                        $query = new Service();
                        $query->retailStore($v['uid'],$v['id']);

                        dump($v['id'].'完成');
                    }

                }
            },'id','desc');

        $output->writeln('任务三级分佣奖励，执行完成');
    }

}