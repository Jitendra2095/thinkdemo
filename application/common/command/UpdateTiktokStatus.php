<?php
namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Db;


/**
 * 自动审核过30秒自动通过
 *
 * 30秒执行一次
 */
class UpdateTiktokStatus extends Command
{

    //配置
    protected function configure()
    {

        $this->setName('update_tiktok_status')
            ->addArgument('name', Argument::OPTIONAL, "your name")
            ->addOption('city', null, Option::VALUE_REQUIRED, 'city name')
            ->setDescription('抖音信息自动审核');
    }
    //执行入口
    protected function execute(Input $input, Output $output)
    {
        $output->writeln("抖音信息自动审核开始");
        $time = time();
        $where = [];
        $where['status'] = 1;
        $where['types'] = 1;
        $tiktok = Db('user_other')->where($where)->select();
        $tiktok_automatic = 1;
        $second = $tiktok_automatic * 60;

        Db::startTrans();

        try {
            foreach ($tiktok as $item){
                if($time >= $item['create_time'] + $second && !empty($item['create_time'])){
                    $data = [
                        'status' => 2,
                        'examine_time' => $time,
                    ];
                    $result = Db('user_other')
                        ->where(['id'=>$item['id']])
                        ->update($data);
                    \app\common\entity\User::where('id',$item['uid'])
                        ->setField('tiktok_status',3);
                    if(!$result)throw new \Exception();
                }
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $output->writeln("抖音信息自动审核失败");
        }

        $output->writeln("抖音信息自动审核结束");
    }

}
