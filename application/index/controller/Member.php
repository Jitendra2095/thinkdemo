<?php

namespace app\index\controller;


use app\common\entity\Config;
use app\common\entity\ConfigUserLevelModel;
use app\common\entity\ManageUser;
use app\common\entity\MyWalletLog;
use app\common\entity\RechargeModel;
use app\common\entity\TaskOrderModel;
use app\common\entity\UserInviteCode;
use app\common\entity\UserOtherModel;
use app\common\PHPMailer\Exception;
use app\common\service\Users\Identity;
use app\common\service\Users\Service;
use app\index\model\Alipay;
use app\index\model\SendCode;
use app\index\model\UserWalletLog;
use app\index\validate\RegisterForm;
use think\App;
use think\Request;
use app\common\entity\User;
use think\Db;
use app\index\model\User as UserModel;


class Member extends Base
{


    public function sendChange()
    {

        $mobile = User::where ('id', $this->userId)->value('mobile');
        //检验手机号码

        $model = new SendCode($mobile, 'change-password');
        if ($model->send()) {
            return json(['code' => 0, 'msg' => '你的验证码发送成功']);
        }
        return json(['code' => 1, 'msg' => '发送失败']);
    }

    /**
     * 清除缓存
     */
    public function delCache()
    {
        $identity = new Identity();
        $identity->delCache($this->userId);
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        $service = new Identity();
        $service->logout();
        return _result(true,'退出成功');
    }




    /*
     * 客服
     */
    public function getService()
    {
        $service_name = Config::getValue('service_name');
        $service_tel = Config::getValue('service_tel');
        return json(['code' => 0, 'msg' => '获取成功', 'info' => [
            'service_name' => $service_name,
            'service_tel' => $service_tel,
        ]]);
    }
    /**
     * 我的团队
     */
    public function getTeamInfo(Request $request)
    {
        $userModel = new User();
        $num = $request->post()??1;//层级
        $nextArr = $userModel->getChildsInfo1($this->userId,3);

        $nextArrId = [];
        $todayStrattime = strtotime(date('Y-m-d',\time())."00:00:00");
        $todayEndtime = strtotime(date('Y-m-d',\time())."23:59:59");
        $new = [];
        foreach ($nextArr as $k=>&$v){

            foreach ($v as $key=>$val){
                $nextArrId[] = $val['id'];
                if(strtotime($val['register_time']) >= $todayStrattime && strtotime($val['register_time']) <= $todayEndtime){
                    $new[] = $val['id'];
                }
                $v[$key]['mobile'] = substr_replace($val['mobile'],'****',3,4);
                $v[$key]['register_time'] = date('Y-m-d H:i:s',$v[$key]['register_time']);
            }
            $nextArr[$k] = $v;
        }

        //团队流水
        $team_stream = MyWalletLog::whereIn('uid',$nextArrId)
            ->where('types',4)
            ->where('money_type',1)
            ->where('status',1)
            ->sum('number');
        //团队总佣金
        $total_commission = MyWalletLog::whereIn('uid',$nextArrId)
            ->where('types',6)
            ->where('money_type',2)
            ->where('status',1)
            ->sum('number');
        //团队总提现
        $total_withdrawal = MyWalletLog::whereIn('uid',$nextArrId)
            ->where('types',2)
            ->where('money_type',2)
            ->where('status',2)
            ->sum('number');
        //首冲人数
        $first_flush = RechargeModel::whereIn('id',$nextArrId)
            ->where('status',2)
            ->count('uid');
        //直推人数
        $direct_push = User::where('pid',$this->userId)->count();
        //团队人数
        $team_total_people = count($nextArrId);
        //新增人数
        $new_subordinate = count($new);
        //团队资产
        $result['team_assets'] = [
            'team_stream' => $team_stream,//团队流水
            'total_commission' => $total_commission,//团队总佣金
            'total_withdrawal' => $total_withdrawal,//团队总提现
        ];
        //团队人数
        $result['team_num'] = [
            'first_flush' => $first_flush,//首冲人数
            'direct_push' => $direct_push,//直推人数
            'team_total_people' => $team_total_people,//团队人数
            'new_subordinate' => $new_subordinate,//新增人数
        ];
        $result['list'] = $nextArr;
        return json(['code' => 0, 'msg' => '获取成功', 'info' => $result]);
    }

    /**
     * 收益明细  MyWalletLog::
     *  '任务佣金奖励',  5
    '任务间推分销佣金奖励', 11
    '任务二级分销佣金奖励', 8
    '任务佣金团队奖励', 9
    '任务佣金平级奖励' 10
     */
    public function profit(Request $request)
    {
        //累计收益
        $all_profit = MyWalletLog::where('uid',$this->userId)
            ->where('status',1)
            ->whereIn('types',[5,11,8,9,10])
            ->sum('number');
        //本月收益
        $month_profit = MyWalletLog::where('uid',$this->userId)
            ->where('status',1)
            ->whereIn('types',[5,11,8,9,10])
            ->whereTime('create_time','month')
            ->sum('number');
        //收益明细
        $date = $this->dateInfo();
        $date1 = $this->dateInfo1();

        foreach ($date as $k=>$v){
            if($k<10){
                $v1 = date('Y-m-d', strtotime($v . ' +1 day'));
                $date_profit[$date1[$k]]['task'] = MyWalletLog::where('uid',$this->userId)
                    ->where('status',1)
                    ->where('types',5)
                    ->whereTime('create_time', 'between', [$date[$k+1],$v])
                    ->sum('number');
                $date_profit[$date1[$k]]['foster'] = MyWalletLog::where('uid',$this->userId)
                    ->where('status',1)
                    ->where('types',10)
//                    ->where('name', '任务佣金平级奖励')
                    ->whereTime('create_time', 'between', [$v, $v1])
                    ->sum('number');
                $date_profit[$date1[$k]]['share'] = MyWalletLog::where('uid',$this->userId)
                    ->where('status',1)
                    ->whereIn('types',[11,8])
                    ->whereTime('create_time', 'between', [$v, $v1])
//                    ->whereIn('name',[
//                        '任务间推分销佣金奖励',
//                        '任务二级分销佣金奖励',
//                    ])
                    ->sum('number');
                $date_profit[$date1[$k]]['team'] = MyWalletLog::where('uid',$this->userId)
                    ->where('status',1)
                    ->where('types',9)
//                    ->where('name', '任务佣金团队奖励')
                    ->whereTime('create_time', 'between', [$v, $v1])
                    ->sum('number');
                $date_profit[$date1[$k]]['all'] = $date_profit[$date1[$k]]['team'] +  $date_profit[$date1[$k]]['share'] + $date_profit[$date1[$k]]['foster'] + $date_profit[$date1[$k]]['task'];
            }

        }
        $yesday = date("Y-m-d",strtotime("-1 day"));
//        dump($yesday);
        $data = [
            'all' => $all_profit,
            'today' => $date_profit[date("Y-m-d")]['all'],
            'yesterday' => $date_profit[$yesday]['all'],
            'month' => $month_profit,
            'date_profit' => $date_profit,
        ];
        return json(['code' => 0, 'msg' => '获取成功', 'info' => $data]);
    }
    //推广二维码
    public function spreadImage(Request $request)
    {
        $list = Db::table('spread_image')
            ->select();
        return _result(true,'success',['result'=>$list]);
        // return json(['code' => 0, 'msg' => '获取成功', 'info' => $list]);
    }

    /**
     * 获取用户是否是首冲
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function getUserFirstFlush(){
        $uid = $this->userId;
        $result = Db::name('user_recharge_log')->where(['uid'=>$uid,'status'=>1])->count();
        $is_first = 0;
        if($result > 0){
            $is_first = 1;
        }
        return _result(true,'success',['is_first'=>$is_first]);
    }

    /**
     * 充值钻石
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function rechargeDiamonds(Request $request){
//        $Alipay = new Alipay();
//        $Alipay->notify();die;
        $uid = $this->userId;
        $diamonds_set_id = intval($request->post('diamonds_set_id'));
        $diamonds_set = Db::name('diamonds_set')->where('id',$diamonds_set_id)->find();
        if(empty($diamonds_set)){
            return _result(false,"请选择正确的充值数量");
        }

        if($diamonds_set['op_type'] == 1){
            $recharge_num = Db::name('user_recharge_log')->where(['uid'=>$uid,'status'=>1])->count();
            if($recharge_num > 0){
                return _result(false,"您已经享受过首冲优惠");
            }
        }
        $recharge_num = $diamonds_set['num'];
        $recharge_money = $diamonds_set['money'];
//        if($recharge_num <= 0){
//            return _result(false,"请选择正确的钻石数量");
//        }
//        $user_model = new UserModel();
//        $result  = $user_model->setUserWallet($uid,'credit1',1,2,$recharge_num,'钻石充值');
//        if($result['code'] == 400){
//            return _result(false,$result['msg']);
//        }
        if($recharge_num > 0 && $recharge_money > 0){
            $ordersn = $this->createNO('user_recharge_log','ordersn','RE');
            $data = [
                'uid'=>$uid,
                'money'=>$recharge_money,
                'num'=>$recharge_num,
                'ordersn'=>$ordersn,
                'createtime'=>time(),
            ];
            $result = Db::name('user_recharge_log')->insert($data);
            if($result){
                $payment = [
                    'op_type'=>1,
                    'out_trade_no'=>$ordersn,
                    'total_amount'=>$recharge_money,
                    'subject'=>"充值钻石",
                ];
                /*$Alipay = new Alipay();
                $result = $Alipay->createOrder($payment);
                */
                $result = ['order'=>$ordersn,'money'=>$recharge_money];
            }else{
                return _result(false,"充值失败");
            }
        }else{
            return _result(false,"充值失败");
        }

//        var_dump($result);die;
        return _result(true,'success',['result'=>$result]);
    }

    /**
     * 充值钻石记录
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserRechargeDiamondsLog(Request $request){
        $uid = $this->userId;
        $page = max(1,intval($request->post('page')));
        $psize = 5;
        $list = Db::name('user_recharge_log')->where(['uid'=>$uid])->field('id,money,ordersn,status,createtime')->order('id desc')->page($page,$psize)->select();
        foreach ($list as &$value){
            $value['createtime'] = date("Y-m-d H:i",$value['createtime']);
        }
        return _result(true,'success',['result'=>$list]);
    }

    /**
     * 获取会员钻石记录
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserDiamondsLog(Request $request){
        $uid = $this->userId;
        $post = $request->post();
        $page = intval(isset($post['page']) ? $post['page'] : 1);
        $psize = 10;
        $where = [
            'uid'=>$uid,
            'credit'=>'credit1'
        ];
        $user_wallet__log_model = new UserWalletLog();
        $field = 'id,remarks,change_money,createtime,op_type';
        $user_record = $user_wallet__log_model->getUserWalletLog($uid,$page,$psize,$field,'credit1');
        return _result(true,'success',$user_record);
    }

    /**
     * 充值VIP
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function rechargeVip(Request $request){
//        $Alipay = new Alipay();
//        $Alipay->vipNotify();die;
        $uid = $this->userId;
        $post = $request->post();
        $config_vip_id = $post['config_vip_id'];
        if(empty($config_vip_id)){
            return _result(false,'请选择开通天数');
        }
        $config_vip = DB::table('config_vip')
            ->where('id',$config_vip_id)
            ->find();

        if(empty($config_vip)){
            return _result(false,'未知错误，请联系管理员');
        }
        $ordersn = $this->createNO('user_open_vip_log','ordersn','VL');
        $data = [
            'uid'=>$uid,
            'ordersn'=>$ordersn,
            'day_num'=>$config_vip['day_num'],
            'money'=>$config_vip['money'],
            'createtime'=>time(),
        ];
        $result = Db::name('user_open_vip_log')->insert($data);
        if($result){
            $payment = [
                'op_type'=>2,
                'out_trade_no'=>$ordersn,
                'total_amount'=>$config_vip['money'],
                'subject'=>"开通VIP",
            ];
            /*$Alipay = new Alipay();
            $result = $Alipay->createOrder($payment);
            */
            $result = ['order'=>$ordersn,'money'=>$config_vip['money']];
        }else{
            return _result(false,"开通失败");
        }
        return _result(true,'success',['result'=>$result]);

    }

    /**
     * 获取会员购买记录
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserPurchaseLog(Request $request){
        $uid = $this->userId;
        $post = $request->post();
        $page = intval(isset($post['page']) ? $post['page'] : 1);
        $psize = 10;
        $where = [
            'ut.uid'=>$uid,
        ];
        $user_record = Db::name('user_game_ticket')
            ->alias('ut')
            ->leftJoin('game_ticket gt','gt.id = ut.ticket_id')
            ->where($where)
            ->field('ut.id,ut.price,ut.ticket_id,ut.createtime,gt.ticketname')->page($page,$psize)->order('ut.id desc')->select();
        foreach ($user_record as &$value){
            $value['createtime'] = date("Y-m-d H:i",$value['createtime']);
        }
        return _result(true,'success',$user_record);
    }

    /**
     * 获取会员钻石
     * @return \think\response\Json
     */
    public function getUserDiamonds(){
        $uid = $this->userId;
        $user_model = new UserModel();
        $credit1 = $user_model->getUserWallet($uid,'credit1');
        return _result(true,'success',['credit1'=>$credit1]);
    }

    /**
     * 获取个人中心
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserInfo(){
        $uid = $this->userId;
        $field = "id,vip,nick_name,avatar";
        $user = Db::name('user')->where('id',$uid)->field($field)->find();
        return _result(true,'success',$user);
    }


    /**
     * 获取用户个人资料
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getPersonalData(){
        $uid = $this->userId;
        //吃鸡
        $chicken = Db::name('user_participate_game_list')->where(['uid'=>$uid,'game_type'=>2,'status'=>1])->field("count(id) as participate_num,sum(eliminate_num) as total_eliminate,sum(win_num) as total_win_num,sum(bonus) as total_bonus")->find();
        //王者荣耀
        $kings = Db::name('user_participate_game_list')->where(['uid'=>$uid,'game_type'=>1,'status'=>1])->field("count(id) as participate_num,sum(eliminate_num) as total_eliminate,sum(win_num) as total_win_num,sum(bonus) as total_bonus")->find();
        //奖金数
        $chicken_bonus_num = Db::name('user_participate_game_list')->where(['uid'=>$uid,'game_type'=>2,'status'=>1,'bonus'=>['>',0]])->count();
        $kings_bonus_num = Db::name('user_participate_game_list')->where(['uid'=>$uid,'game_type'=>1,'status'=>1,'bonus'=>['>',0]])->count();
        //淘汰数
        $chicken['total_eliminate'] = intval($chicken['total_eliminate']);
        //参赛数
        $chicken['participate_num'] = intval($chicken['participate_num']);
        //吃鸡、获奖数
        $chicken['total_win_num'] = intval($chicken['total_win_num']);
        //奖金数
        $chicken['total_bonus'] = intval($chicken['total_bonus']);
        //获奖率
        $chicken['awards'] = "0.00";
        //获奖率
        if($chicken_bonus_num > 0){
            $chicken['awards'] = sprintf("%.2f",$chicken['participate_num'] / $chicken_bonus_num);
        }
        $chicken['game_account'] = Db::name('user_game_account')->where(['uid'=>$uid,'game_type'=>2])->field('id,game_name,mobile_type')->select();
        $kings['total_eliminate'] = intval($kings['total_eliminate']);
        $kings['participate_num'] = intval($kings['participate_num']);
        $kings['total_win_num'] = sprintf("%.2f",$kings['total_win_num']);
        $kings['total_bonus'] = sprintf("%.2f",$kings['total_bonus']);
        $kings['awards'] = "0.00";
        if($kings_bonus_num > 0){
            $kings['awards'] = sprintf("%.2f",$kings['participate_num'] / $kings_bonus_num);
        }
        $kings['game_account'] = Db::name('user_game_account')->where(['uid'=>$uid,'game_type'=>1])->field('id,game_name,mobile_type')->select();
        return _result(true,'success',['chicken'=>$chicken,'kings'=>$kings]);
    }

    /**
     * 报名记录
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getParticipateGameLog(Request $request){
        $play_status=$request->post('status');
        $type=$request->post('type');
        $type=$type?$type:1;
        
        $uid = $this->userId;
        $page = max(1,intval($request->post('page')));
        $psize = 5;
        $game_status=[
            '1'=>'约战中',
            '2'=>'准备中',
            '3'=>'进行中',
            '4'=>'审核中',
            '5'=>'已结束',
        ];
        if($type==3){
            if($play_status==0){
                $list = Db::name('user_participate_game_list')
                ->alias('p')
                ->leftJoin('game_room r','r.id = p.room_id')
                ->leftJoin('roomclassify c','c.id = r.game_type')
                ->where(['p.uid'=>$uid,'p.status'=>['>=',0],'r.new_game_type'=>1])
                ->field('p.id,r.roomname,p.createtime,c.title,r.game_type,r.status')
                ->page($page,$psize)
                ->order('p.id desc')
                ->select();
                
            }elseif($play_status==1){
                $list = Db::name('user_participate_game_list')
                ->alias('p')
                ->leftJoin('game_room r','r.id = p.room_id')
                ->leftJoin('roomclassify c','c.id = r.game_type')
                ->where(['p.uid'=>$uid,'p.status'=>['>=',0],'r.new_game_type'=>1,'r.status'=>1])
                ->field('p.id,r.roomname,p.createtime,c.title,r.game_type,r.status')
                ->page($page,$psize)
                ->order('p.id desc')
                ->select();
            }elseif($play_status==2){
                $list = Db::name('user_participate_game_list')
                ->alias('p')
                ->leftJoin('game_room r','r.id = p.room_id')
                ->leftJoin('roomclassify c','c.id = r.game_type')
                ->where(['p.uid'=>$uid,'p.status'=>['>=',0],'r.new_game_type'=>1])
                ->where('r.status','in','2,3,4')
                ->field('p.id,r.roomname,p.createtime,c.title,r.game_type,r.status')
                ->page($page,$psize)
                ->order('p.id desc')
                ->select();
            }elseif($play_status==3){
                $list = Db::name('user_participate_game_list')
                ->alias('p')
                ->leftJoin('game_room r','r.id = p.room_id')
                ->leftJoin('roomclassify c','c.id = r.game_type')
                ->where(['p.uid'=>$uid,'p.status'=>['>=',0],'r.new_game_type'=>1,'r.status'=>5])
                ->field('p.id,r.roomname,p.createtime,c.title,r.game_type,r.status')
                ->page($page,$psize)
                ->order('p.id desc')
                ->select();
            }
            foreach ($list as &$value){
                $value['status']=$game_status[$value['status']];   
            }
            
        }else{
           $list = Db::name('user_participate_game_list')
            ->alias('p')
            ->leftJoin('game_room r','r.id = p.room_id')
            ->leftJoin('roomclassify c','c.id = r.game_type')
            ->where(['p.uid'=>$uid,'p.status'=>['>=',0]])
            ->field('p.id,r.roomname,p.createtime,c.title,r.game_type')
            ->page($page,$psize)
            ->order('p.id desc')
            ->select(); 
        }
        
        foreach ($list as &$value){
            $value['createtime'] = date('Y-m-d H:i',$value['createtime']);
        }
        return _result(true,'success',$list);
    }

    /**
     * 获取报名记录详情
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getParticipateGameDetail(Request $request){
        $uid = $this->userId;
        $participate_id = intval($request->post('participate_id'));
        $list = Db::name('user_participate_game_list')
            ->alias('p')
            ->leftJoin('user u','u.id = p.uid')
            ->leftJoin('user_game_ticket ut','ut.id = p.ticket_id')
            ->leftJoin('game_ticket t','t.id = ut.ticket_id')
            ->leftJoin('game_room r','r.id = p.room_id')
            ->where(['p.id'=>$participate_id])
            ->field('p.id,r.id as room_id,r.image,r.roomname,r.match_type,r.reward,r.reward_type,r.currency,r.mobile_type,r.roomgame_type,u.nick_name,u.mobile,t.ticketname,p.createtime,p.game_starttime,r.game_type,r.status')
            ->find();
        if(!empty($list)){
            $game_log=Db::table('user_participate_game_list')->where(['room_id'=>$list['room_id'],'uid'=>$uid])->find();
            $room=Db::table('game_room')->find($list['room_id']);
            if($game_log['team_type']==1){
                $list['play_img']=$room['red_win_img'];
                if(empty($room['blue_win_img'])){
                    if($room['status']!=3 && $room['status']!=1){
                        $list['status_text']='等待对方上传战绩图';
                    }else{
                        if($room['status']==1){
                            $list['status_text']='约战中';
                        }else{
                            $list['status_text']='比赛中';
                        }
                    }
                }else{
                    if($room['status']==4){
                        $list['status_text']='等待审核';
                    }elseif($room['status']==3){
                        $list['status_text']='比赛中';
                    }elseif($room['status']==5){
                        $list['status_text']='已结束';
                    }
                }
            }else{
                $list['play_img']=$room['blue_win_img'];
                if(empty($room['red_win_img'])){
                    if($room['status']!=3 && $room['status']!=1){
                        $list['status_text']='等待对方上传战绩图';
                    }else{
                        if($room['status']==1){
                            $list['status_text']='约战中';
                        }else{
                            $list['status_text']='比赛中';
                        }
                    }
                }else{
                    if($room['status']==4){
                        $list['status_text']='等待审核';
                    }elseif($room['status']==3){
                        $list['status_text']='比赛中';
                    }elseif($room['status']==5){
                        $list['status_text']='已结束';
                    }
                }
            }
            
            $list['game_account'] = Db::name('user_game_account')->where(['uid'=>$uid,'game_type'=>$list['game_type']])->value('game_name');
            $reward = json_decode($list['reward'],true);
            if($list['currency'] == 1){
                $currency = "元";
            }else{
                $currency = "金币";
            }
            if($list['reward_type'] == 1){
                $reward_text = "比赛奖励：".$reward[0].$currency."/人";
            }else{
                $reward_text = "比赛奖励：".array_sum($reward).$currency;
            }
            $roomgame_type = $this->getRoomClassify($list['roomgame_type']);
            $match_type = $this->getRoomClassify($list['match_type']);
//        $mobile_type = $this->getRoomClassify($list['mobile_type']);
            $list['pattern'] = [
                $roomgame_type['title'],
                $match_type['title'],
//            $mobile_type['title'],
            ];
            $list['reward'] = $reward_text;
            $list['game_number'] = substr($list['createtime'],0,7).$list['room_id'];
            $list['createtime'] = date("Y-m-d H:i",$list['createtime']);
            $list['game_starttime'] = date("Y-m-d H:i",$list['game_starttime']);
        }

        return _result(true,'success',$list);
    }

    /**
     * 卡包(门票)
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function cardBag(Request $request){
        $uid = $this->userId;
        $page = max(1,intval($request->post('page')));
        $psize = 10;
        $list = Db::name('user_game_ticket')
            ->alias('ut')
            ->leftJoin('game_ticket gt',' ut.ticket_id = gt.id')
            ->where(['ut.uid'=>$uid,'ut.status'=>0])
            ->field('gt.ticketname,ut.orvertime,gt.image,ut.ticket_id')
            ->order('ut.status asc,ut.id desc')
            ->page($page,$psize)
            ->select();
        foreach ($list as &$value){
            $value['orvertime'] = date("Y-m-d",$value['orvertime']);
        }
        return _result(true,'success',$list);
    }

    /**
     * 获取用户全部折扣卷
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserAllDiscountList(){
        $uid = $this->userId;
        $list = Db::name('user_discount_list')->where(['uid'=>$uid,'status'=>0])->select();
        foreach ($list as &$value){
            $value['createtime'] = date("Y-m-d H:i",$value['createtime']);
        }
        return _result(true,'success',$list);
    }


    /**
     * 获取用户优惠券列表
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserDiscountList(Request $request){
        $uid = $this->userId;
        $page = max(1,intval($request->post('page')));
        $status = intval($request->post('status'));
        if(!in_array($status,[0,1])){
            return _result(false,'未知错误');
        }
        $psize = 5;
        $list = Db::name('user_discount_list')
            ->alias('ud')
            ->leftJoin('discount_list d',' d.id = ud.discount_id')
            ->where(['ud.uid'=>$uid,'ud.status'=>$status])
            ->field('d.title,ud.status,ud.discount,ud.createtime,ud.usetime')
            ->order('ud.status asc,ud.id desc')
            ->page($page,$psize)
            ->select();
        foreach ($list as &$value){
            $value['createtime'] = date("Y-m-d H:i",$value['createtime']);
            if($value['status']){
                $value['usetime'] = date("Y-m-d H:i",$value['usetime']);
            }
        }
        return _result(true,'success',$list);


    }

    /**
     * 获取用户可参加的比赛
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserCanParticipateGame(Request $request){
        $uid = $this->userId;
        $page = max(1,intval($request->post('page')));
        $psize = 5;
        $ticket_id = intval($request->post('ticket_id'));
        $field = "r.id as room_id,r.roomname,r.game_type,r.match_type,r.mobile_type,r.roomgame_type,r.enrolltime,r.reward,r.reward_type,r.currency,r.image";
        $list = Db::name('user_game_ticket')
            ->alias('t')
            ->leftJoin('game_room r','r.ticket_id = t.ticket_id')
            ->where(['t.uid'=>$uid,'r.ticket_id'=>$ticket_id,'r.readytime'=>['>',time()],'r.enrolltime'=>['<=',time()]])
            ->group('t.ticket_id')
            ->field($field)
            ->order('r.house_full asc,r.id desc')
            ->page($page,$psize)
            ->select();
        foreach ($list as &$value){
            $roomgame_type = $this->getRoomClassify($value['roomgame_type']);
            $match_type = $this->getRoomClassify($value['match_type']);
            $mobile_type = $this->getRoomClassify($value['mobile_type']);
            $value['pattern'] = [
                $roomgame_type['title'],
                $match_type['title'],
                // $mobile_type['title'],
            ];
            $value['enrolltime'] = date("H:i",$value['enrolltime']);
            $reward = json_decode($value['reward'],true);
            if($value['currency'] == 1){
                $currency = "元";
            }else{
                $currency = "金币";
            }
            if($value['reward_type'] == 1){
                $value['reward'] = "比赛奖励：".$reward[0].$currency."/人";
            }else{
                $value['reward'] = "比赛奖励：".array_sum($reward).$currency;
            }
        }
        return _result(true,'success',$list);
    }



    /**
     * 关联游戏账号
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function relevanceUserGameAccount(){
        $uid = $this->userId;
        $chicken_wechat = Db::name('user_game_account')->where(['game_type'=>2,'mobile_type'=>9,'uid'=>$uid])->field('id,game_name,mobile_type')->find();
        $chicken_qq = Db::name('user_game_account')->where(['game_type'=>2,'mobile_type'=>10,'uid'=>$uid])->field('id,game_name,mobile_type')->find();
        $king_wechat = Db::name('user_game_account')->where(['game_type'=>1,'mobile_type'=>9,'uid'=>$uid])->field('id,game_name,mobile_type')->find();
        $king_qq = Db::name('user_game_account')->where(['game_type'=>1,'mobile_type'=>10,'uid'=>$uid])->field('id,game_name,mobile_type')->find();
        $list['chicken']['wechat'] = empty($chicken_wechat) ? ['mobile_type'=>9] : $chicken_wechat;
        $list['chicken']['qq'] = empty($chicken_qq) ? ['mobile_type'=>10] : $chicken_qq;
        $list['chicken']['game_type'] = 2;
        $list['chicken']['game_name'] = "和平精英";
        $list['king']['wechat'] = empty($king_wechat) ? ['mobile_type'=>9] : $king_wechat;
        $list['king']['qq'] = empty($king_qq) ? ['mobile_type'=>10] : $king_qq;
        $list['king']['game_type'] = 1;
        $list['king']['game_name'] = "王者荣耀";
        return _result(true,'success',$list);
    }

    /**
     * 绑定游戏帐号详情
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function bindGameAccountDetail(Request $request){
        $game_account_id = intval($request->post('game_account_id'));
        $user_game_account = Db::name('user_game_account')->where(['id'=>$game_account_id])->find();
        $game_model = new \app\index\model\Game();
        $user_game_account['game_rank'] = $game_model->getGameRank($user_game_account['game_rank']);
        $user_game_account['game_position'] = $game_model->getGamePosition($user_game_account['game_position']);
        return _result(true,'success',empty($user_game_account) ? [] : $user_game_account);
    }

    /**
     * 获取会员游戏帐号信息
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserGameAccount(Request $request){
        $uid = $this->userId;
        $game_type = intval($request->post('game_type'));
        $mobile_type = intval($request->post('mobile_type'));
        $user_game_account = Db::name('user_game_account')->where(['game_type'=>$game_type,'mobile_type'=>$mobile_type,'uid'=>$uid])->find();
        if(!empty($user_game_account)){
            $game_model = new \app\index\model\Game();
            $user_game_account['game_rank'] = $game_model->getGameRank($user_game_account['game_rank']);
            $user_game_account['game_position'] = $game_model->getGamePosition($user_game_account['game_position']);
        }
         return _result(true,'success',$user_game_account);

    }

    /**
     * 修改用户游戏帐号
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function updateGameAccount(Request $request){
        $uid = $this->userId;
        $game_name = $request->post('game_name');
        $game_account_id = intval($request->post('game_account_id'));
        $user_game_account = Db::name('user_game_account')->where(['id'=>$game_account_id,'uid'=>$uid])->find();
        if(empty($user_game_account)){
            return _result(false,'您绑定的游戏帐号不存在');
        }
        if(!empty($game_name)){
            $data['game_name'] = $game_name;
        }
        if($user_game_account['game_type'] == 1){
            $game_rank = intval($request->post('game_rank'));
            $game_position = intval($request->post('game_position'));
            if(!empty($game_rank)){
                $data['game_rank'] = $game_rank;
            }
            if(!empty($game_position)){
                $data['game_position'] = $game_position;
            }
        }else{
            $game_number = intval($request->post('game_number'));
            if(!empty($game_number)){
                $data['game_number'] = $game_number;
            }
        }
        Db::name('user_game_account')->where('id',$game_account_id)->update($data);

        return _result(true,'修改成功');
    }

    /**
     * 绑定游戏帐号
     * @param Request $request
     * @return \think\response\Json
     */
    public function bindGameAccount(Request $request){
        $uid = $this->userId;
        $game_type = intval($request->post('game_type'));
        $mobile_type = intval($request->post('mobile_type'));
        $game_name = $request->post('game_name');
        $user_game_account = Db::name('user_game_account')->where(['game_type'=>$game_type,'mobile_type'=>$mobile_type,'uid'=>$uid])->value("id");
        if(!empty($user_game_account)){
            return _result(false,'您已绑定游戏帐号');
        }
        if(empty($game_name)){
            return _result(false,'请输入游戏昵称');
        }
        $data = [
            'uid'=>$uid,
            'game_type'=>$game_type,
            'mobile_type'=>$mobile_type,
            'game_name'=>$game_name,
        ];
        if($game_type == 1){
            $game_rank = $request->post('game_rank');
            $game_position = $request->post('game_position');
            if(empty($game_rank)){
                return _result(false,'请选择游戏段位');
            }
            if(empty($game_position)){
                return _result(false,'请选择游戏位置');
            }
            $data['game_rank'] = $game_rank;
            $data['game_position'] = $game_position;
        }else{
            $game_number = $request->post('game_number');
            if(empty($game_number)){
                return _result(false,'请输入游戏编号');
            }
            $data['game_number'] = $game_number;
        }
        $result = Db::name('user_game_account')->insert($data);

        if(!$result){
            return _result(false,'添加游戏帐号失败');
        }
        return _result(true,'添加游戏帐号成功');
    }

    /**
     * 获取用户门票历史记录
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserParticipateLog(Request $request){
        $uid = $this->userId;
        $page = max(1,intval($request->post('page')));
        $psize = 10;
        $status = intval($request->post('status'));
        if(!in_array($status,[0,1,-1])){
            return _result(false,'发生未知错误');
        }
        $list = Db::name('user_game_ticket')
            ->alias('ut')
            ->leftJoin('game_ticket gt',' ut.ticket_id = gt.id')
            ->where(['ut.uid'=>$uid,'ut.status'=>$status])
            ->field('gt.ticketname,ut.orvertime,gt.image,ut.ticket_id')
            ->order('ut.status asc,ut.id desc')
            ->page($page,$psize)
            ->select();
        foreach ($list as &$value){
            $value['orvertime'] = date("Y-m-d",$value['orvertime']);
        }
        return _result(true,'success',$list);
    }

    /**
     * 获取会员邀请码
     * @param Request $request
     * @return \think\response\Json
     */
    public function getUserQrcode(Request $request){
        $domain = $request->domain();

        $uid = $this->userId;
        $user = Db::name('user')->where('id',$uid)->field('nick_name,avatar')->find();
        $invite_code = Db::name('user_invite_code')->where('user_id',$uid)->value('invite_code');
        $user['invite_code'] = $invite_code;
        $user_model = new \app\index\model\User();
        $url = $domain."/h5/#/pages/login/register/register?invite_code=".$invite_code;
        if(!file_exists('../public/upload/'.md5($invite_code).'.png')){
            $code = $user_model->getQrCode($url,md5($invite_code));
        }else{
            $code = '/upload/'.md5($invite_code).'.png';
        }
        $spread_image = Db::name('spread_image')->order('sort desc')->select();
        return _result(true,'success',['invite_code'=>$code,'user'=>$user,'spread_image'=>$spread_image]);
    }

    /**
     * 获取个人信息
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getPersonalInfo(){
        $uid = $this->userId;
        $user_model = new UserModel();
        $field = "id,nick_name,avatar,mobile,gender,occupation,region";
        $user = Db::name('user')->where('id',$uid)->field($field)->find();
        $user['region'] = json_decode($user['region'],true);
        $user['occupation'] = $user_model->getUserOccupation($user['occupation']);
        $user['gender'] = $user_model->getUserGender($user['gender']);
        return _result(true,'success',$user);
    }

    /**
     * 修改用户信息
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function UpdateUserinfo(Request $request){
        $uid = $this->userId;
        //1修改昵称2修改性别3修改职业4修改地区5修改头像
        $type = $request->post('type');
        $data = [];
        if($type == 1){
            $nick_name = $request->post('nick_name');
            if(empty($nick_name)){
                return _result(false,'请填写昵称');
            }
            $data['nick_name'] = $nick_name;
        }elseif ($type == 2){
            $gender = $request->post('gender');
            if(empty($gender) && $gender != 0){
                return _result(false,'请选择性别');
            }
            $data['gender'] = $gender;
        }elseif ($type == 3){
            $occupation = $request->post('occupation');
            if(empty($occupation)){
                return _result(false,'请选择职业');
            }
            $data['occupation'] = $occupation;
        }elseif ($type == 4){
            $province = $request->post('province');
            if(empty($province)){
                return _result(false,'请选择省份');
            }
            $city = $request->post('city');
            if(empty($city)){
                return _result(false,'请选择市区');
            }
            $area = $request->post('area');
            if(empty($area)){
                return _result(false,'请选择城镇');
            }
            $data['region'] = json_encode([
                'province'=>$province,
                'city'=>$city,
                'area'=>$area,
            ]);
        }elseif ($type == 5){
            $avatar = $request->post('avatar');
            if(empty($avatar)){
                return _result(false,'请上传头像');
            }
            $data['avatar'] = $avatar;
        }
        if(!empty($data)){
            Db::name('user')->where('id',$uid)->update($data);
            return _result(true,'修改成功');
        }
        return _result(false,'修改失败');
    }

    /**
     * 修改用户密码
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function UpdateUserPassword(Request $request){
        $uid = $this->userId;
        $mobile = $request->post('mobile');
        $new_password = $request->post('new_password');
        $code = $request->post('code');
        $info = Db::name('user')->where('mobile',$mobile)->find();
        if (!$info) {
            return _result(false,"用户不存在");
        }
        if(empty($new_password)){
            return _result(false,"请输入新密码");
        }
        $form = new RegisterForm();
        $msg_checking = Config::getValue('msg_checking');//开启短信验证
        if($msg_checking){
            if (!$form->checkCode($code, $mobile)) {
                return _result(false,"验证码输入错误");
            }
        }
        Db::name('user')->where('id',$uid)->update(['password'=>md5(md5("eco_member" . $new_password))]);
        return _result(true,"新密码设置成功");
    }

    /**
     * 获取用户支付宝
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserPayment(){
        $uid = $this->userId;
        $user_payment = Db::name('user_payment')->where('uid',$uid)->field('alipay_name,alipay_account')->find();
        return _result(true,'success',$user_payment);
    }





    /**
     * 设置支付宝
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function setUserPayment(Request $request){
        $uid = $this->userId;
        $alipay_name = $request->post('alipay_name');
        $alipay_account = $request->post('alipay_account');
        $code = $request->post('code');
        if(empty($alipay_name)){
            _result(false,'请填写真实姓名');
        }
        if(empty($alipay_account)){
            _result(false,'请填写支付宝绑定的手机号');
        }

        $mobile = Db::name('user')->where('id',$uid)->value('mobile');
        $form = new RegisterForm();
        $msg_checking = Config::getValue('msg_checking');//开启短信验证
        if($msg_checking){
            if(empty($code)){
                _result(false,'请填写验证码');
            }
            if (!$form->checkCode($code, $mobile)) {
                return _result(false,"验证码输入错误");
            }
        }
        $user_payment = Db::name('user_payment')->where('uid',$uid)->find();
        if(!empty($user_payment)){
            Db::name('user_payment')->where('uid',$uid)->update(['alipay_name'=>$alipay_name,'alipay_account'=>$alipay_account]);
        }else{
            Db::name('user_payment')->insert(['uid'=>$uid,'alipay_name'=>$alipay_name,'alipay_account'=>$alipay_account]);
        }
        return _result(true,"设置成功");

    }

    /**
     * 获取用户手机号
     * @return \think\response\Json
     */
    public function getUserMobile(){
        $uid = $this->userId;
        $mobile = Db::name('user')->where('id',$uid)->value('mobile');
        return _result(true,'success',['mobile'=>$mobile]);
    }

    /**
     * 推广
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserExtension(){
        $uid = $this->userId;
        $times = $this->getTime();
        $user = Db::name('user')
            ->alias('u')
            ->leftJoin('user_invite_code i','i.user_id = u.id')
            ->leftJoin('user_wallet w','w.uid = u.id')
            ->field('u.id,u.nick_name,u.avatar,u.pid,i.invite_code,w.commission')
            ->where('u.id',$uid)
            ->find();
        $user['agent'] = Db::name('user')->where('id',$user['pid'])->field('avatar,nick_name,id')->find();
        $user_model = new UserModel();
        $today_profit_where = [
            'uid'=>$uid,
            'credit'=>"commission",
            'op_type'=>2,
            'createtime'=>['between',$times['today']['start'].",".$times['today']['end']],
        ];
        $this_moeth_profit_where = [
            'uid'=>$uid,
            'credit'=>"commission",
            'op_type'=>2,
            'createtime'=>['between',$times['this_month']['start'].",".$times['this_month']['end']],
        ];
        $last_moeth_profit_where = [
            'uid'=>$uid,
            'credit'=>"commission",
            'op_type'=>2,
            'createtime'=>['between',$times['last_month']['start'].",".$times['last_month']['end']],
        ];
        //今天收益
        $today_profit = Db::name('user_wallet_log')->where($today_profit_where)->sum('change_money');
        //今月收益
        $this_moeth_profit = Db::name('user_wallet_log')->where($this_moeth_profit_where)->sum('change_money');
        //上月收益
        $last_moeth_profit = Db::name('user_wallet_log')->where($last_moeth_profit_where)->sum('change_money');
        $user['today_profit'] = sprintf("%.2f",$today_profit);
        $user['this_moeth_profit'] = sprintf("%.2f",$this_moeth_profit);
        $user['last_moeth_profit'] = sprintf("%.2f",$last_moeth_profit);
        //团队
        $teams = $user_model->getTeam($uid);
        $user_subordinate = "";
        foreach ($teams as $value){
            $user_subordinate .= $value['id'].",";
        }
        $user_subordinate = trim($user_subordinate,',');
        //今天新增
        $todat_newly_add_user = Db::name('user')->where(['id'=>['in',$user_subordinate],'register_time'=>['between',$times['today']['start'].",".$times['today']['end']]])->field('id')->count('id');
        //昨天新增
        $yesterday_newly_add_user = Db::name('user')->where(['id'=>['in',$user_subordinate],'register_time'=>['between',$times['yesterday']['start'].",".$times['yesterday']['end']]])->field('id')->count('id');
        //团队人数
        $user['total_team_num'] = count($teams);
        //今天新增人数
        $user['todat_newly_add_user'] = $todat_newly_add_user;
        //昨天新增人数
        $user['yesterday_newly_add_user'] = $yesterday_newly_add_user;
        //一级粉丝
        $first_team = [];
        $first_team = $user_model->getLayerTeam($uid,$first_team,1);
        if(!empty($first_team)){
            foreach ($first_team as &$first_fans){
                //一级粉丝团队总人数
                $first_fans['total_team_num'] = count($user_model->getTeam($first_fans['id']));
                $fans_today_profit_where = [
                    'uid'=>$first_fans['id'],
                    'credit'=>"commission",
                    'op_type'=>2,
                    'createtime'=>['between',$times['today']['start'].",".$times['today']['end']],
                ];
                $fans_this_moeth_profit_where = [
                    'uid'=>$first_fans['id'],
                    'credit'=>"commission",
                    'op_type'=>2,
                    'createtime'=>['between',$times['this_month']['start'].",".$times['this_month']['end']],
                ];
                $fans_last_moeth_profit_where = [
                    'uid'=>$first_fans['id'],
                    'credit'=>"commission",
                    'op_type'=>2,
                    'createtime'=>['between',$times['last_month']['start'].",".$times['last_month']['end']],
                ];
                //一级粉丝今天收益
                $fans_today_profit = Db::name('user_wallet_log')->where($fans_today_profit_where)->sum('change_money');
                //一级粉丝今月收益
                $fans_this_moeth_profit = Db::name('user_wallet_log')->where($fans_this_moeth_profit_where)->sum('change_money');
                //一级粉丝上月收益
                $fans_last_moeth_profit = Db::name('user_wallet_log')->where($fans_last_moeth_profit_where)->sum('change_money');
                $first_fans['today_profit'] = sprintf("%.2f",$fans_today_profit);
                $first_fans['this_moeth_profit'] = sprintf("%.2f",$fans_this_moeth_profit);
                $first_fans['last_moeth_profit'] = sprintf("%.2f",$fans_last_moeth_profit);
                //注册时间
                $first_fans['register_time'] = date('Y-m-d H:i',$first_fans['register_time']);
            }
        }

        $second_team = [];
        $second_team = $user_model->getLayerTeam($uid,$second_team,2);
        if(!empty($second_team)){
            foreach ($second_team as &$second_fans){
                //二级粉丝团队总人数
                $second_fans['total_team_num'] = count($user_model->getTeam($second_fans['id']));
                $second_today_profit_where = [
                    'uid'=>$second_fans['id'],
                    'credit'=>"commission",
                    'op_type'=>2,
                    'createtime'=>['between',$times['today']['start'].",".$times['today']['end']],
                ];
                $second_this_moeth_profit_where = [
                    'uid'=>$second_fans['id'],
                    'credit'=>"commission",
                    'op_type'=>2,
                    'createtime'=>['between',$times['this_month']['start'].",".$times['this_month']['end']],
                ];
                $second_last_moeth_profit_where = [
                    'uid'=>$second_fans['id'],
                    'credit'=>"commission",
                    'op_type'=>2,
                    'createtime'=>['between',$times['last_month']['start'].",".$times['last_month']['end']],
                ];
                //二级今天收益
                $second_today_profit = Db::name('user_wallet_log')->where($second_today_profit_where)->sum('change_money');
                //二级今月收益
                $second_this_moeth_profit = Db::name('user_wallet_log')->where($second_this_moeth_profit_where)->sum('change_money');
                //二级上月收益
                $second_last_moeth_profit = Db::name('user_wallet_log')->where($second_last_moeth_profit_where)->sum('change_money');
                $second_fans['today_profit'] = sprintf("%.2f",$second_today_profit);
                $second_fans['this_moeth_profit'] = sprintf("%.2f",$second_this_moeth_profit);
                $second_fans['last_moeth_profit'] = sprintf("%.2f",$second_last_moeth_profit);
                //注册时间
                $second_fans['register_time'] = date('Y-m-d H:i',$second_fans['register_time']);
            }
        }
        //一级粉丝
        $user['first_team'] = $first_team;
        //二级粉丝
        $user['second_team'] = $second_team;
        unset($first_fans);
        unset($second_fans);
        return _result(true,'success',$user);
    }

    /**
     * 获取用户推广码
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserExtensionCode(Request $request){
        $domain = $request->domain();
        $uid = $this->userId;
        $user_invite_code = Db::name('user_invite_code')->where('user_id',$uid)->value('invite_code');
        $user_model = new \app\index\model\User();
        $url = $domain."/h5/#/pages/login/register/register?invite_code=".$user_invite_code;
        if(!file_exists('../public/upload/'.md5($user_invite_code).'.png')){
            $invite_code = $user_model->getQrCode($url,md5($user_invite_code));
        }else{
            $invite_code = '/upload/'.md5($user_invite_code).'.png';
        }
        $user = Db::name('user')->where('id',$uid)->field('id,avatar,nick_name')->find();
        //总收益
        $total_profit = Db::name('user_wallet_log')->where(['uid'=>$uid,'credit'=>'commission','op_type'=>2])->sum('change_money');

        $times = $this->getTime();

        $today_profit_where = [
            'uid'=>$uid,
            'credit'=>"commission",
            'op_type'=>2,
            'createtime'=>['between',$times['today']['start'].",".$times['today']['end']],
        ];
        $this_moeth_profit_where = [
            'uid'=>$uid,
            'credit'=>"commission",
            'op_type'=>2,
            'createtime'=>['between',$times['this_month']['start'].",".$times['this_month']['end']],
        ];
        //今日收益
        $today_profit = Db::name('user_wallet_log')->where($today_profit_where)->sum('change_money');
        //今月收益
        $this_moeth_profit = Db::name('user_wallet_log')->where($this_moeth_profit_where)->sum('change_money');
        $user['total_profit'] = sprintf("%.2f",$total_profit);
        $user['today_profit'] = sprintf("%.2f",$today_profit);
        $user['this_moeth_profit'] = sprintf("%.2f",$this_moeth_profit);
        $user['invite_code'] = $user_invite_code;
        $user['invite_qr_cede'] = $invite_code;
        return _result(true,'success',$user);
    }

    /**
     * 获取用户佣金明细
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserCommissionLog(Request $request){
        $uid = $this->userId;
        $page = max(1,intval($request->post('page')));
        $psize = 10;
        $CommissionLog = Db::name('user_wallet_log')->where(['uid'=>$uid,'credit'=>'commission','op_type'=>2])->order('id desc')->page($page,$psize)->select();
        foreach ($CommissionLog as &$value){
            $value['createtime'] = date('Y-m-d H:i',$value['createtime']);
        }
        return _result(true,'success',$CommissionLog);
    }

    /**
     * 获取用户可提现金额
     */
    public function getUserCanWithdrawalMoney(Request $request){
        $uid = $this->userId;
        $type = $request->post('type');
        $where = ['uid'=>$uid];
        if($type == 1){
            $value = "commission";
        }elseif ($type == 2){
            $value = "bonus";
        }
        if(empty($value)){
            return _result(false,'未知错误');
        }
        $withdrawal_money = Db::name('user_wallet')->where($where)->value($value);
        return _result(true,'success',['withdrawal_money'=>$withdrawal_money]);
    }

    /**
     * 用户佣金提现
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function UserCommissionWithdrawal(Request $request){
        $uid = $this->userId;
        $money = sprintf("%.2f",$request->post('money'));
//        $alipay_name = $request->post('alipay_name');
//        $alipay_account = $request->post('alipay_account');
        $user_payemt = Db::name('user_payment')->where('uid',$uid)->find();
        if(empty($user_payemt['alipay_account']) || empty($user_payemt['alipay_name'])){
            return _result(false,'请先完善支付宝信息');
        }
        $alipay_name = $user_payemt['alipay_name'];
        $alipay_account = $user_payemt['alipay_account'];
//        if(empty($alipay_name)){
//            return _result(false,'请输入支付宝昵称');
//        }
//        if(empty($alipay_account)){
//            return _result(false,'请输入支付宝帐号');
//        }
        $user_commission_money = Db::name('user_wallet')->where('uid',$uid)->value('commission');
        if($user_commission_money < $money){
            return _result(false,'您的可提现金额不足');
        }
        $user_model = new UserModel();
        $user_vip = $user_model->getUserVip($uid);
        $times = $this->getTime();

        //佣金每日可提现次数
        $commission_withdraw_num = $this->getConfig('commission_withdraw_num');
        //佣金提现开始时间
        $commission_start_time = $this->getConfig('commission_start_time');
        //佣金提现结束时间
        $commission_end_time = $this->getConfig('commission_end_time');
        //提现手续费（%）
        $withdrawa_fee = $this->getConfig('withdrawa_fee');
        //起提金额
        $msg_ch = $this->getConfig('msg_ch');
        //vip起提金额
        $vip_msg_ch = $this->getConfig('vip_msg_ch');
        //用户今天提现次数
        $user_withdrawal_count = Db::name('user_withdrawal')->where(['uid'=>$uid,'createtime'=>['between',$times['today']['start'].",".$times['today']['end']]])->count();
        if($user_withdrawal_count >= $commission_withdraw_num && $commission_withdraw_num > 0){
//            return _result(false,'每天只能提现'.$commission_withdraw_num.'次');
        }
        if(time() < strtotime(date("Y-m-d",time())." ".$commission_start_time)){
            return _result(false,'提现开始时间为每天：'.$commission_start_time);
        }

        if(time() >= strtotime(date("Y-m-d",time())." ".$commission_end_time)){
            return _result(false,'提现结束时间为每天：'.$commission_end_time);
        }
        if($user_vip){
            if($money < $vip_msg_ch){
                return _result(false,'最少提现金额为：'.$msg_ch.'元');
            }
        }else{
            if($money < $msg_ch){
                return _result(false,'最少提现金额为：'.$msg_ch.'元');
            }
        }

        if($withdrawa_fee > 0 && !$user_vip){
            $realmoney = sprintf("%.2f",$money - ($money * $withdrawa_fee / 100));
        }else{
            $realmoney = sprintf("%.2f",$money);
        }
        Db::startTrans();
        try {
            $result = $user_model->setUserWallet($uid,'commission',3,1,$money,'佣金提现');
            if($result['code'] == 400){
                throw new Exception($result['msg']);
            }
            $data = [
                'uid'=>$uid,
                'money'=>$money,
                'realmoney'=>$realmoney,
                'alipay_name'=>$alipay_name,
                'alipay_account'=>$alipay_account,
                'createtime'=>time(),
            ];
            $result = Db::name('user_withdrawal')->insert($data);
            if(!$result){
                throw new Exception("提现失败");
            }
            Db::commit();
            return _result(true,'提现成功');
        }catch (Exception $e){
            Db::rollback();
            return _result(false,$e->getMessage());
        }

    }

    /**
     * 佣金提现记录
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserCommissionWithdrawalLog(Request $request){
        $uid = $this->userId;
        $page = max(1,intval($request->post('page')));
        $psize = 10;
        $log = Db::name('user_withdrawal')->where(['uid'=>$uid,'op_type'=>1])->page($page,$psize)->order('id desc')->select();
        foreach ($log as &$value){
            $value['createtime'] = date("Y-m-d H:i",$value['createtime']);
            $value['examinetime'] = date("Y-m-d H:i",$value['examinetime']);
            if($value['status'] == 0){
                $value['statustext'] = "待审核";
            }elseif ($value['status'] == 1){
                $value['statustext'] = "审核通过";
            }elseif ($value['status'] == -1){
                $value['statustext'] = "审核不通过";
            }
        }
        return _result(true,'success',$log);
    }

    /**
     * 获取用户奖金
     * @return \think\response\Json
     */
    public function getUserBonus(){
        $uid = $this->userId;
        $user_model = new UserModel();
        $user_bonus = $user_model->getUserWallet($uid,'bonus');
        $totalHistory = Db::name('user_wallet_log')->where(['uid'=>$uid,'credit'=>"bonus",'op_type'=>2])->sum('change_money');
        return _result(true,'success',['user_bonus'=>$user_bonus,'totalhistory'=>$totalHistory]);
    }


    /**
     * 用户奖金提现
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function UserBonusWithdrawal(Request $request){
        $uid = $this->userId;
        $money = sprintf("%.2f",$request->post('money'));

        $user_payemt = Db::name('user_payment')->where('uid',$uid)->find();
//        dump($user_payemt);die;
        if(empty($user_payemt['alipay_account']) || empty($user_payemt['alipay_name'])){
            return _result(false,'请先完善支付宝信息');
        }
        $alipay_name = $user_payemt['alipay_name'];
        $alipay_account = $user_payemt['alipay_account'];
//        $alipay_name = $request->post('alipay_name');
//        $alipay_account = $request->post('alipay_account');

//        if(empty($alipay_name)){
//            return _result(false,'请输入支付宝昵称');
//        }
//        if(empty($alipay_account)){
//            return _result(false,'请输入支付宝帐号');
//        }
        $user_commission_money = Db::name('user_wallet')->where('uid',$uid)->value('bonus');
        if($user_commission_money < $money){
            return _result(false,'您的可提现金额不足');
        }
        $times = $this->getTime();

        //佣金每日可提现次数
        $commission_withdraw_num = $this->getConfig('commission_withdraw_num');
        //佣金提现开始时间
        $commission_start_time = $this->getConfig('commission_start_time');
        //佣金提现结束时间
        $commission_end_time = $this->getConfig('commission_end_time');
        //提现手续费（%）
        $withdrawa_fee = $this->getConfig('withdrawa_fee');
        //起提金额
        $msg_ch = $this->getConfig('msg_ch');
        //用户今天提现次数
        $user_withdrawal_count = Db::name('user_withdrawal')->where(['uid'=>$uid,'createtime'=>['between',$times['today']['start'].",".$times['today']['end']]])->count();
        if($user_withdrawal_count >= $commission_withdraw_num){
            return _result(false,'每天只能提现'.$commission_withdraw_num.'次');
        }
        if(time() < strtotime(date("Y-m-d",time())." ".$commission_start_time)){
            return _result(false,'提现开始时间为每天：'.$commission_start_time);
        }

        if(time() >= strtotime(date("Y-m-d",time())." ".$commission_end_time)){
            return _result(false,'提现结束时间为每天：'.$commission_end_time);
        }

        if($money < $msg_ch){
            return _result(false,'最少提现金额为：'.$msg_ch.'元');
        }
        $user_model = new UserModel();
        $realmoney = sprintf("%.2f",$money - ($money * $withdrawa_fee / 100));
        Db::startTrans();
        try {
            $result = $user_model->setUserWallet($uid,'bonus',3,1,$money,'奖金提现');
            if($result['code'] == 400){
                throw new Exception($result['msg']);
            }
            $data = [
                'uid'=>$uid,
                'money'=>$money,
                'realmoney'=>$realmoney,
                'op_type'=>2,
                'alipay_name'=>$alipay_name,
                'alipay_account'=>$alipay_account,
                'createtime'=>time(),
            ];
            $result = Db::name('user_withdrawal')->insert($data);
            if(!$result){
                throw new Exception("提现失败");
            }
            Db::commit();
            return _result(true,'提现成功');
        }catch (Exception $e){
            Db::rollback();
            return _result(false,$e->getMessage());
        }
    }

    /**
     * 奖金提现记录
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserBonusWithdrawalLog(Request $request){
        $uid = $this->userId;
        $page = max(1,intval($request->post('page')));
        $psize = 10;
        $log = Db::name('user_withdrawal')->where(['uid'=>$uid,'op_type'=>2])->page($page,$psize)->order('id desc')->select();
        foreach ($log as &$value){
            $value['createtime'] = date("Y-m-d H:i",$value['createtime']);
            $value['examinetime'] = date("Y-m-d H:i",$value['examinetime']);
            if($value['status'] == 0){
                $value['statustext'] = "待审核";
            }elseif ($value['status'] == 1){
                $value['statustext'] = "审核通过";
            }elseif ($value['status'] == -1){
                $value['statustext'] = "审核不通过";
            }
        }
        return _result(true,'success',$log);
    }

    public function getUserBonusLog(Request $request){
        $uid = $this->userId;
        $page = max(1,intval($request->post('page')));
        $psize = 10;
        $log = Db::name('user_wallet_log')->where(['uid'=>$uid,'credit'=>"bonus"])->page($page,$psize)->order('id desc')->select();
        foreach ($log as &$value){
            $value['createtime'] = date("Y-m-d H:i",$value['createtime']);
        }
        return _result(true,'success',$log);
    }

    /**
     * 获取用户战绩
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserCombatGains(){
        $uid = $this->userId;
        //吃鸡
        $chicken = Db::name('user_participate_game_list')->where(['uid'=>$uid,'game_type'=>2,'status'=>1])->field("count(id) as participate_num,sum(eliminate_num) as total_eliminate,sum(win_num) as total_win_num")->find();
        //王者荣耀
        $kings = Db::name('user_participate_game_list')->where(['uid'=>$uid,'game_type'=>1,'status'=>1])->field("count(id) as participate_num,sum(eliminate_num) as total_eliminate,sum(win_num) as total_win_num")->find();
        //淘汰数
        $chicken['total_eliminate'] = intval($chicken['total_eliminate']);
        //参赛数
        $chicken['participate_num'] = intval($chicken['participate_num']);
        //吃鸡、获奖数
        $chicken['total_win_num'] = intval($chicken['total_win_num']);
        $chicken['game_type'] = 2;

        $chicken['game_account'] = Db::name('user_game_account')->where(['uid'=>$uid,'game_type'=>2])->field('id,game_name')->find();
        $kings['total_eliminate'] = intval($kings['total_eliminate']);
        $kings['participate_num'] = intval($kings['participate_num']);
        $kings['total_win_num'] = intval($kings['total_win_num']);
        $kings['game_type'] = 1;
        $kings['game_account'] = Db::name('user_game_account')->where(['uid'=>$uid,'game_type'=>1])->field('id,game_name')->find();
        return _result(true,'success',['chicken'=>$chicken,'kings'=>$kings]);
    }


    /**
     * 获取赛事详情
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserParticipateGameLog(Request $request){
        $uid = $this->userId;
        $page = max(1,intval($request->post('page')));
        $psize = 5;
        $game_type = intval($request->post('game_type'));
        if(!in_array($game_type,[1,2])){
            return _result(false,'发生未知错误');
        }
        $list = Db::name('user_participate_game_list')
            ->alias('p')
            ->leftJoin('game_room r','r.id = p.room_id')
            ->where(['p.uid'=>$uid,'p.game_type'=>$game_type])
            ->field('p.id,p.ranking,p.createtime,r.roomname,r.id as room_id')
            ->order('p.id desc')
            ->page($page,$psize)
            ->select();
        foreach ($list as &$value){
            $value['createtime'] = date("Y-m-d H:i",$value['createtime']);
        }
        return _result(true,'success',$list);
    }

    /**
     * 获取赛事结果
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGameResult(Request $request){
        $uid = $this->userId;
        $room_id = intval($request->post('room_id'));
        $page = max(1,intval($request->post('page')));
        $psize = 5;
        $room = Db::name('game_room')->where('id',$room_id)->field('id,status')->find();
        if(empty($room)){
            return _result(false,'房间不存在');
        }
        $user_participate_game_list = Db::name('user_participate_game_list')->where(['room_id'=>$room_id,'uid'=>$uid,'status'=>1])->field('id,ranking,bonus')->order('createtime desc')->find();
        $list = Db::name('user_participate_game_list')
            ->alias('p')
            ->leftJoin('user u','u.id = p.uid')
            ->leftJoin('game_room r','r.id = p.room_id')
            ->leftJoin('user_game_account a','a.uid = p.uid and a.game_type = p.game_type and a.mobile_type = r.mobile_type')
            ->where(['p.room_id'=>$room_id,'p.status'=>1])
            ->field('p.id,p.uid,p.bonus,p.eliminate_num,u.avatar,u.nick_name,a.game_name,p.ranking')
            ->order('p.ranking asc')
            ->page($page,$psize)
            ->select();

        return _result(true,'success',['user_participate_game_list'=>$user_participate_game_list,'list'=>$list]);
    }

    /**
     * 查看战绩
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCombatGains(Request $request){
        $participate_game_list_id = intval($request->post('participate_game_list_id'));
        $user_participate_game_list = Db::name('user_participate_game_list')
            ->alias('p')
            ->leftJoin('user u','u.id = p.uid')
            ->leftJoin('roomclassify c','c.id = p.match_type')
            ->where(['p.id'=>$participate_game_list_id,'p.status'=>1])
            ->field('p.id,p.ranking,p.bonus,p.eliminate_num,c.title,u.nick_name,u.avatar,u.vip')
            ->find();

        return _result(true,'success',$user_participate_game_list);

    }


    /**
     * 获取网站设置
     * @param $key
     * @return mixed
     */
    public function getConfig($key){
        $config = Db::name('config')->where(['un_id'=>1,'type'=>1,'status'=>1,'key'=>$key])->value('value');
        return $config;
    }

    /**
     * 获取时间戳
     * @return array
     */
    private function getTime(){
        $t = time();
        //今天开始的时间戳
        $today_start_time = mktime(0,0,0,date("m",$t),date("d",$t),date("Y",$t));
        //今天结束的时间戳
        $today_end_time = mktime(23,59,59,date("m",$t),date("d",$t),date("Y",$t));
        //昨天开始的时间戳
        $beginYesterday=mktime(0,0,0,date('m'),date('d')-1,date('Y'));
        //昨天结束的时间戳
        $endYesterday=mktime(0,0,0,date('m'),date('d'),date('Y'))-1;
        //本月开始的时间戳
        $beginThismonth=mktime(0,0,0,date('m'),1,date('Y'));
        //本月结束的时间戳
        $endThismonth=mktime(23,59,59,date('m'),date('t'),date('Y'));
        $m = date('Y-m-d', mktime(0,0,0,date('m')-1,1,date('Y'))); //上个月的开始日期
        $t = date('t',strtotime($m)); //上个月共多少天
        $start = date('Y-m-d', mktime(0,0,0,date('m')-1,1,date('Y'))); //上个月的开始日期
        $end = date('Y-m-d', mktime(0,0,0,date('m')-1,$t,date('Y'))); //上个月的结束日期
        $lastMonthStartTime=strtotime($start);//上个月的开始时间戳
        $lastMonthEndTime=strtotime($end);//上个月的结束时间戳

        $data = [
            'today'=>[
                'start'=>$today_start_time,
                'end'=>$today_end_time,
            ],
            'yesterday'=>[
                'start'=>$beginYesterday,
                'end'=>$endYesterday,
            ],
            'this_month'=>[
                'start'=>$beginThismonth,
                'end'=>$endThismonth,
            ],
            'last_month'=>[
                'start'=>$lastMonthStartTime,
                'end'=>$lastMonthEndTime,
            ]
        ];
        return $data;
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

    public function createNO($table, $field, $prefix)
    {
        $billno = date("YmdHis") . mt_rand(100000, 999999);
        while (1) {
            $count = Db::name($table)->where($field,$prefix.$billno)->count();
            if ($count <= 0) {
                break;
            }
            $billno = date("YmdHis") . mt_rand(100000, 999999);
        }
        return $prefix . $billno;
    }








}
