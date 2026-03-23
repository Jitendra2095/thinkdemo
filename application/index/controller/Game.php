<?php

namespace app\index\controller;
use app\common\entity\Config;
use app\common\PHPMailer\Exception;
use redis\RedisCluster;
use think\cache\driver\Redis;
use think\Db;
use think\Image;
use think\Request;

class Game extends Base
{
    public function edit_url(Request $request){
        $uid = $this->userId;
        $room_id=input('room_id');
        $url=input('url');
        $url = str_replace(array("&amp;"), "&", $url);
        if(empty($url)){
            return _result(false,'请输入房间连接');
        }
        $room=Db::table('game_room')->find($room_id);
        if(empty($room)){
            return _result(false,'赛事不存在');
        }
        $game_log=Db::table('user_participate_game_list')->where(['room_id'=>$room_id,'uid'=>$uid])->find();
        if(empty($game_log)){
            return _result(false,'未找到报名记录');
        }
        if($game_log['team_type']!=1){
            return _result(false,'你不是房主,无法修改');
        }
        $res=Db::table('game_room')->where('id',$room_id)->update(['room_url'=>$url]);
        if($res){
            return _result(true,'修改完成');
        }else{
            return _result(false,'修改失败');
        }
    }
    /**
     * 创建房间1v1
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
     public function up_play_status(Request $request){
        $uid = $this->userId;
        $room_id=input('game_id');
        $room=Db::table('game_room')->find($room_id);
        //查询属于那边
        $game_log=Db::table('user_participate_game_list')->where(['room_id'=>$room_id,'uid'=>$uid])->find();
        if(empty($game_log)){
            return _result(false,'未找到报名记录');
        }
        if($room['status']==5){
            return _result(false,'比赛已结束');
        }
        $uploadModel = new \app\common\service\Upload\Service('file');
        if ($uploadModel->upload()) {
            $img_data = getimagesize("." . $uploadModel->fileName);
            $uploaded_type = $img_data['mime'];
            $savename = date('Ymd') . '/' . md5(microtime(true));
            if ($uploaded_type == 'image/jpeg') {
                $img = imagecreatefromjpeg("." . $uploadModel->fileName);
                imagejpeg($img, "./uploads/" . $savename . ".jpg", 100);
                $atype = ".jpg";
            } else {
                $img = imagecreatefrompng("." . $uploadModel->fileName);
                imagepng($img, "./uploads/" . $savename . ".png", 9);
                $atype = ".png";
            }
            unlink("." . $uploadModel->fileName);
            if (!file_exists("./uploads/" . $savename . $atype)) {
                    return _result(false,'上传失败，请稍后再试');
            }
            $img_path="/uploads/" . $savename . $atype;
        }
        Db::startTrans();
        try {
        //房主
            if($game_log['team_type']==1){
                //如果对方上传更改为审核状态
                if($room['blue_win_img'] && $room['status']!=5){
                    Db::table('game_room')->where('id',$room_id)->update(['status'=>4]);
                }
                Db::table('game_room')->where('id',$room_id)->update(['red_win_img'=>$img_path]);
            }else{
                //如果对方上传更改为审核状态
                if($room['red_win_img'] && $room['status']!=5){
                    Db::table('game_room')->where('id',$room_id)->update(['status'=>4]);
                }
                Db::table('game_room')->where('id',$room_id)->update(['blue_win_img'=>$img_path]);
            }
            Db::commit();
            return _result(true,'上传成绩图完成',['url'=>$img_path]);
        }catch (Exception $e){
            Db::rollback();
            return _result(false,$e->getMessage());
        }
        //完成
         
     }
    /**
     * 创建房间1v1
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create_game(Request $request){
        $post = $request->post();
        $uid = $this->userId;
        $area=input('area');
        $game_url=input('url');
        $game_url = str_replace(array("&amp;"), "&", $game_url);

        if($area==1){
            $area=9;
        }else{
            $area=10;
        }
        $config_menpiao=Db::table('config')->where('key','onegame')->find();
        $config_reward=Db::table('config')->where('key','one_game_reward')->find();
        //判断是否有门票
        $user_game_account = Db::name('user_game_account')->where(['uid'=>$uid,'game_type'=>1,'mobile_type'=>$area])->find();
        if(empty($user_game_account)){
            return _result(false,'请先绑定游戏帐号');
        }
        $user_game_ticket = Db::name('user_game_ticket')
            ->where(['ticket_id'=>$config_menpiao['value'],'status'=>0,'uid'=>$uid])
            ->find();

        $game_ticket = Db::name('game_ticket')->where('id',$config_menpiao['value'])->find();

        if(empty($user_game_ticket)){
            return _result(false,'请先购买门票('.$game_ticket['ticketname'].")");
        }
        if($user_game_ticket['createtime'] + (86400 * 30) < time()){
            return _result(false,'请先购买门票('.$game_ticket['ticketname'].")");
        }
        $config_desc=Db::table('config')->where('key','one_play_desc')->find();
        $config_desc=$config_desc['value'];
        //使用门票
        Db::startTrans();
        try {
            //添加房间记录
            $game_arr=[
                'roomname'=>'王者约战'.rand(11111,99990),
                'image'=>'/static/room_icon.png',
                'game_type'=>1,
                'new_game_type'=>1,
                'match_type'=>16,
                'mobile_type'=>$area,
                'roomgame_type'=>17,
                'ticket_id'=>$config_menpiao['value'],
                'reward_type'=>3,
                'reward'=>json_encode([$config_reward['value']]),
                'currency'=>1,
                'enrolltime'=>time(),
                'readytime'=>time()+600,
                'starttime'=>time()+600,
                'room_url'=>$game_url,
                'rule'=>$config_desc,
                'house_full'=>0,
                'createtime'=>time(),
            ];
            $result = Db::name('user_game_ticket')->where(['uid'=>$uid,'id'=>$user_game_ticket['id']])->update(['status'=>1]);
            if(!$result){
                throw new Exception('创建失败');
            }
            $create_room_id = Db::table('game_room')->insertGetId($game_arr);
            if(!$result){
                throw new Exception('创建失败2');
            }
            $room=Db::table('game_room')->find($create_room_id);
            //添加 报名记录
            $data = [
                'uid'=>$uid,
                'room_id'=>$create_room_id,
                'ticket_id'=>$config_menpiao['value'],
                'game_type'=>1,
                'team_type'=>1,
                'match_type'=>16,
                'game_starttime'=>$room['starttime'],
                'createtime'=>time(),
            ];
            Db::table('user_participate_game_list')->insertGetId($data);
            
            Db::commit();
            return _result(true,'创建成功');
        }catch (Exception $e){
            Db::rollback();
            return _result(false,$e->getMessage());
        }
        
        
        
    }

    /**
     * 获取赛事详情
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGameDetail(Request $request){
        $post = $request->post();
        $room_id = intval($post['room_id']);
        $uid = $this->userId;
        if(empty($room_id)){
            return _result(false,'请选择赛事');
        }
//        $where['status'] = ['<',5];
        $where['id'] = ['=',$room_id];
        $where['deleted'] = ['=',0];
        $room = Db::name('game_room')->where($where)->find();
        if(empty($room)){
            return _result(false,'该赛事不存在');
        }
        $roomgame_type = $this->getRoomClassify($room['roomgame_type']);
        $match_type = $this->getRoomClassify($room['match_type']);
//        $mobile_type = $this->getRoomClassify($room['mobile_type']);
        $room['surplus_time'] = ['readytime'=>$room['readytime'],'enrolltime'=>$room['enrolltime']];
        $room['enrolltime'] = date("m-d H:i",$room['enrolltime']);
        $room['readytime'] = date("m-d H:i",$room['readytime']);
		$room['starttime0'] = $room['starttime'];
        $room['starttime'] = date("m-d H:i",$room['starttime']);
        $room['pattern'] = [
            $roomgame_type['title'],
            $match_type['title'],
//            $mobile_type['title'],
        ];
        $room['reward'] = json_decode($room['reward'],true);

        $room['rule'] = htmlspecialchars_decode($room['rule']);
        if($room['game_type'] == 2){
            $user_participate_game_list = Db::name('user_participate_game_list')
                ->alias('l')
                ->join('user u','l.uid = u.id')
                ->field('u.nick_name,u.avatar')
                ->where('l.room_id',$room['id'])
                ->group('l.uid')
                ->order('l.id asc')
                ->limit(5)
                ->select();
        }elseif ($room['game_type'] == 1){
            $user_participate_game_list['teamred'] = Db::name('user_participate_game_list')
                ->alias('l')
                ->join('user u','l.uid = u.id')
                ->field('u.nick_name,u.avatar,u.id')
                ->where(['l.room_id'=>['=',$room['id']],'l.team_type'=>['=',1]])
                ->group('l.uid')
                ->order('l.id asc')
                ->limit(5)
                ->select();
            $user_participate_game_list['teamblue'] = Db::name('user_participate_game_list')
                ->alias('l')
                ->join('user u','l.uid = u.id')
                ->field('u.nick_name,u.avatar,u.id')
                ->where(['l.room_id'=>['=',$room['id']],'l.team_type'=>['=',2]])
                ->group('l.uid')
                ->order('l.id asc')
                ->limit(5)
                ->select();
        }
        //查询用户账号信息 返回标签
        if ($room['game_type'] == 1){
            $position_arr=[
                '1'=>'全能',
                '2'=>'上单',
                '3'=>'中单',
                '4'=>'打野',
                '5'=>'射手',
                '6'=>'游走',
            ];
            $rank_arr=[
                '1'=>'青铜',
                '2'=>'白银',
                '3'=>'黄金',
                '4'=>'铂金',
                '5'=>'钻石',
                '6'=>'星耀',
                '7'=>'最强王者',
                '8'=>'荣耀王者',
            ];
            foreach ($user_participate_game_list['teamred'] as &$value){
                $user_game_account = Db::name('user_game_account')->where(['uid'=>$value['id'],'game_type'=>$room['game_type'],'mobile_type'=>$room['mobile_type']])->find();
                $value['game_rank']=$rank_arr[$user_game_account['game_rank']];
                $value['game_position']=$position_arr[$user_game_account['game_position']];
            }
            foreach ($user_participate_game_list['teamblue'] as &$value){
                $user_game_account = Db::name('user_game_account')->where(['uid'=>$value['id'],'game_type'=>$room['game_type'],'mobile_type'=>$room['mobile_type']])->find();
                $value['game_rank']=$rank_arr[$user_game_account['game_rank']];
                $value['game_position']=$position_arr[$user_game_account['game_position']];
            }
        }
        
        $room['user_participate_game_list'] = $user_participate_game_list;
        $user['is_participate_game'] = Db::name('user_participate_game_list')->where(['uid'=>$uid,'room_id'=>$room_id,'status'=>0])->value('id') ? 1 : 0;
        return _result(true,'success',['room'=>$room,'user'=>$user]);
    }

    /**
     * 退票
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function cancelParticipateGame(Request $request){
        $uid = $this->userId;
        $participate_id = intval($request->post('participate_id'));
        $user_participate_game_list = Db::name('user_participate_game_list')
            ->alias('p')
            ->leftJoin('game_room g','g.id = p.room_id')
            ->field('p.*,g.status as rstatus')
            ->where(['p.uid'=>$uid,'p.id'=>$participate_id])
            ->find();
        if(empty($user_participate_game_list)){
            return _result(false,'该参赛记录不存在');
        }
        if($user_participate_game_list['rstatus'] > 2){
            return _result(false,'比赛已经开始不能退票');
        }
        try {
            Db::startTrans();
            $result = Db::name('user_game_ticket')->where(['uid'=>$uid,'id'=>$user_participate_game_list['ticket_id']])->update(['status'=>0]);
            if(!$result){
                throw new Exception('退票失败');
            }
            $result = Db::name('user_participate_game_list')->where('id',$user_participate_game_list['id'])->update(['status'=>-1]);
            if(!$result){
                throw new Exception('退票失败');
            }
            Db::commit();
            return _result(true,'退票成功');

        }catch (Exception $e){
            Db::rollback();
            return _result(false,$e->getMessage());

        }


    }

    /**
     * 参加比赛
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function participateGame(Request $request){
        $uid = $this->userId;
        $post = $request->post();
        $room_id = intval($post['room_id']);
        $team_type = intval($post['team_type']);
        $room_where = [
            'id'=>['=',$room_id],
            'status'=>['<=',1],
            'deleted'=>0
        ];
        $room = Db::name('game_room')->where($room_where)->find();
        if(empty($room)){
            return _result(false,'赛事不存在或已开始');
        }
        if ($room['house_full'] == 1){
            return _result(false,'赛事已爆满');
        }
        $user_game_account = Db::name('user_game_account')->where(['uid'=>$uid,'game_type'=>$room['game_type'],'mobile_type'=>$room['mobile_type']])->find();
        if(empty($user_game_account)){
            return _result(false,'请先绑定游戏帐号');
        }
        $user_game_ticket = Db::name('user_game_ticket')
            ->where(['ticket_id'=>$room['ticket_id'],'status'=>0,'uid'=>$uid])
            ->find();

        $game_ticket = Db::name('game_ticket')->where('id',$room['ticket_id'])->find();

        if(empty($user_game_ticket)){
            return _result(false,'请先购买门票('.$game_ticket['ticketname'].")");
        }
        if($user_game_ticket['createtime'] + (86400 * 30) < time()){
            return _result(false,'请先购买门票('.$game_ticket['ticketname'].")");
        }
        $user_buy_participate = Db::name('user_participate_game_list')->where(['uid'=>$uid,"room_id"=>$room_id,'status'=>0])->find();
        if(!empty($user_buy_participate)){
            return _result(false,'您已经参赛，请准时参加');
        }

        if(time() < $room['enrolltime']){
            return _result(false,'比赛还没到报名时间');
        }
        if(time() > $room['readytime']){
            return _result(false,'比赛报名已截止');
        }
        if($room['game_type'] == 1){
            $max_participate_num = 10;
        }elseif ($room['game_type'] == 2){
            $max_participate_num = 99;
        }
        $participate_game_num = Db::name('user_participate_game_list')->where(['room_id'=>$room_id,'status'=>0])->count();
        if($participate_game_num >= $max_participate_num){
            return _result(false,'参赛人数已满');
        }
        $data = [
            'uid'=>$uid,
            'room_id'=>$room['id'],
            'ticket_id'=>$user_game_ticket['id'],
            'game_type'=>$room['game_type'],
            'match_type'=>$room['match_type'],
            'game_starttime'=>$room['starttime'],
            'createtime'=>time(),
        ];
        if($room['game_type'] == 1 && $room['new_game_type']!=1){
            if(empty($team_type)){
                return _result(false,'请先选择队伍');
            }
            $data['team_type'] = $team_type;
        }elseif($room['new_game_type']==1){
            $data['team_type']=2;
        }
        if($room['new_game_type']==1){
            //更改游戏状态为已开始
            //更改为已满员
            Db::name('game_room')->where('id',$room_id)->update(['status'=>3,'house_full'=>1]);
        }
        
        if($participate_game_num - 1 >= $max_participate_num){
            Db::name('game_room')->where('id',$room_id)->update(['house_full'=>1]);
        }
        Db::startTrans();
        try {
            $result = Db::name('user_game_ticket')->where(['uid'=>$uid,'id'=>$user_game_ticket['id']])->update(['status'=>1]);
            if(!$result){
                throw new Exception('参赛失败');
            }
            $result = Db::name('user_participate_game_list')->insert($data);
            if(!$result){
                throw new Exception('参赛失败');

            }
            Db::commit();
            
            if($data['team_type']==2){
                //查询两人手机号
                $game_sms_list=Db::table('user_participate_game_list')->where(['room_id'=>$room_id])->select();
                foreach ($game_sms_list as $value){
                    $user_info=Db::table('user')->find($value['uid']);
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
                    $content='【熊猫电竞】您的王者约战已有玩家报名加入，请及时进入APP参赛！';//要发送的短信内容
                    $phone = $user_info['mobile'];//要发送短信的手机号码
                    $sendurl = $smsapi."sms?u=".$user."&p=".$pass."&m=".$phone."&c=".urlencode($content);
                    $result =file_get_contents($sendurl) ;
                }
            }
            return _result(true,'参赛成功');
        }catch (Exception $e){
            Db::rollback();
            return _result(false,$e->getMessage());
        }

    }


    /**
     * 获取分类
     * @param $class_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getRoomClassify($class_id){
        $where['id'] = $class_id;
        $where['status'] = 1;
        $where['deleted'] = 0;
        $roomclassify = Db::name('roomclassify')->where($where)->field('id,title')->find();
        return $roomclassify;
    }



}