<?php

namespace app\common\command;


use app\common\entity\UserParticipateGameList;
use app\common\service\Task\Service;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;



/**
 * 检查房间状态  每分钟执行
 *
 */
class UserTips extends Command
{

    protected function configure()
    {
        //设置参数
        $this->setName('user_tips')
            ->setDescription('提示用户开始比赛');
    }

    protected function execute(Input $input, Output $output)
    {
        $times = time();
        $advance_time = $times + (60 * 10);
        $todaystart_time = strtotime(date('Y-m-d',time())."00:00:00");
        $today_end_time = strtotime(date('Y-m-d',time())."23:59:59");
        $list = Db::name('user_participate_game_list')
            ->alias('l')
            ->leftJoin('user u','u.id = l.uid')
            ->field('u.mobile,l.game_starttime,l.id')
            ->where(['l.status'=>['>=',0],'tip_status'=>0,'l.match_type'=>['<>',16],'game_starttime'=>['<=',$advance_time],'game_starttime'=>['between',[$todaystart_time,$today_end_time]]])
            ->select();
        if(!empty($list)){
            $data = [];
            $update_data = [];
            $game_starttime = [];
            foreach ($list as $value){
                if(!in_array($value['game_starttime'],$game_starttime)){
                    $data[$value['game_starttime']][] = $value['mobile'];
                    $update_data[] = ['id'=>$value['id'],'tip_status'=>1];
                }
            }
            $data[1610689206] = ['15707581596','15766132405'];
            if(!empty($data)){
                foreach ($data as $key => $item){
                    $mobile = implode(',',$item);
                    $mobile = trim($mobile,',');
                    $result = $this->sendCode($mobile,date("Y-m-d H:i",$key));
                    if(!$result){
                        $output->writeln($result['message']);
                    }
                }
            }
            if($update_data){
                $UserParticipateGameList = new UserParticipateGameList();
                $UserParticipateGameList->isUpdate(true)->saveAll($update_data);
                $output->writeln('提示用户开始比赛，执行完成("有门票更新")');die;
            }
        }

        $output->writeln('提示用户开始比赛，执行完成("无门票更新")');die;


    }

    private function sendCode($mobile,$time)
    {
        
        $statusStr = array(
        "0" => "短信发送成功",
        "-1" => "参数不全",
        "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
        "30" => "密码错误",
        "40" => "账号不存在",
        "41" => "余额不足",
        "42" => "帐户已过期",
        "43" => "IP地址限制",
        "50" => "内容含有敏感词"
        );
        $smsapi = "http://api.smsbao.com/";
        $user = "18060072822"; //短信平台帐号
        $pass = md5("6458643736AAAAA"); //短信平台密码
        $content='【熊猫电竞】您报名参加的比赛将在'.$time.'准时开赛，请尽快前往我的-报名记录”中进入赛事房间。';//要发送的短信内容
        $phone = $mobile;//要发送短信的手机号码
        $sendurl = $smsapi."sms?u=".$user."&p=".$pass."&m=".$phone."&c=".urlencode($content);
        $result =file_get_contents($sendurl) ;
        if ($statusStr[$result]==0) {
            return true;
        }else{
            return $statusStr[$result];
        }
        
        $sms_setting = [
            'userid' => '66520',
            'account' => '20201226',
            'password' => 'ad4560',
        ];

        $body=array(
            'action'=>'send',
            'userid'=>$sms_setting['userid'],
            'account'=>$sms_setting['account'],
            'password'=>$sms_setting['password'],
            'mobile'=>$mobile,
            'content'=>'【熊猫电竞】您报名参加的比赛将在'.$time.'准时开赛，请尽快前往我的-报名记录”中进入赛事房间。',
        );
        $ch=curl_init();
        curl_setopt($ch, CURLOPT_URL,"https://dx.ipyy.net/smsJson.aspx");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        $result = curl_exec($ch);
        curl_close($ch);
        $result_data = json_decode($result, true);
//        dump($result_data);
//        die;
        if (isset($result_data['returnstatus']) && $result_data['returnstatus'] === 'Success') {
            return true;
        }else{
            return $result_data;
        }
    }


}