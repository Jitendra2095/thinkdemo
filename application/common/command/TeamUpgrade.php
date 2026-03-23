<?php

namespace app\common\command;

use app\common\entity\ConfigTeamLevelModel;
use app\common\entity\ConfigUserLevelModel;
use app\common\entity\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;



/**
 * 计算星级达人升级
 *
 * 3小时执行一次
 */
class TeamUpgrade extends Command
{

    //配置
    protected function configure()
    {
        $this->setName('team_upgrade')
            ->setDescription('计算会员等级');
    }
    /**
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
        $PlatformSettingLogic = new ConfigUserLevelModel();
        $mse = $PlatformSettingLogic;
        $start_time=strtotime(date("Y-m-d",time()));
        //当天结束之间
        $end_time=$start_time+60*60*24;
        Db('user')->where('id', '>', 0)
            ->field('id,star_upgrade_time')
            ->whereTime('star_upgrade_time','not between',[$start_time,$end_time])
            ->chunk(100,function ($all_user) use ($mse){
                foreach ($all_user as $item) {
                    $push = 0;
                    $team = false;
                    $query = new User();
                    //团队有效人数
                    $teamRealNum = $query->getChildsRealNum($item['id'],3);
                    for ($x=1; $x<=7; $x++) {
                        $lv_push = $mse
                            ->where('id',$x)
                            ->value('valid_num');
                        if($teamRealNum >= $lv_push){
                            $push = $x;
                        }
                    }
                    if($push){
                        //团队人数
                        $teamNum = $query->getChildsInfoNum($item['id'],3);
                        $lv_push = $mse
                            ->where('id',$push)
                            ->value('team_num');
                        if($teamNum >= $lv_push){
                            $team = true;
                        }
                        if(!$team && $push > 1){
                            for ($y=1;$y<=$push-1;$y++){
                                $push = $push - $y;
                                $lv_push = $mse
                                    ->where('id',$push)
                                    ->value('team_num');
                                if($teamNum >= $lv_push){
                                    $team = true;
                                    break;
                                }
                            }
                        }
                    }
                    if($team){
                        Db('user')
                            ->where('id',$item['id'])
                            ->update([
                                'level' => $push
                            ]);
                    }else{
//                        Db('user')
//                            ->where('id',$item['id'])
//                            ->update([
//                                'level' => 0
//                            ]);
                    }
                    Db('user')
                        ->where('id',$item['id'])
                        ->update([
                            'star_upgrade_time' => time()
                        ]);
                    dump($item['id'].'完成');
                }
            },'star_upgrade_time','desc');
        $output->writeln('计算会员等级，执行完成');
    }
}
