<?php

namespace app\index\controller;
use think\Controller;
use think\Db;
use think\Image;
use think\Request;
use think\Session;
use think\Cache;
class Home extends Controller
{

    const SESSION_NAME = 'flow_box_member';
    public function room_help(){
        $nodes=Db::table('article')->where('article_id',11)->value('content');
        return json(['nodes'=>$nodes]);
    }
    public function rule_video()
    {
        $type=input('type');   
        if($type==1){
            //微信
            $video=DB::table('config')->where('key','game_rule_wx_video')->value('value');
        }else{
            //qq   
            $video=DB::table('config')->where('key','game_rule_qq_video')->value('value');
        }
        return json(['url'=>$video]);
    }
    public function rule_app_url()
    {
        $app=DB::table('config')->where('key','app_url')->value('value');
        return json(['url'=>$app]);
    }
    public function user_name(){
        $user_list=Db::table('user_invite_code')->select();
        foreach ($user_list as $value){
            Db::table('user_invite_code')->where('id',$value['id'])->update(['invite_code'=>rand(111111,999999)]);
        }
    }
    public function fuli_list(){
        $list=Db::table('article')->where('status',1)->where('category',3)->select();
        return _result(true,'success',$list);
    }
    public function fuli_item(Request $request){
        $id=$request->post('id');
        $carousel = Db::name('article')->where('article_id',$id)->find();
        $carousel['create_time']=date('Y-m-d H:i:s',$carousel['create_time']);
        return _result(true,'success',$carousel);
    }
    
    public function news_list(){
        $list=Db::table('article')->where('status',1)->where('category',1)->select();
        return _result(true,'success',$list);
    }
     /**
     * 获取精彩赛事
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function video_list(){
        $list=Db::table('article')->where('status',1)->where('category',4)->select();
        return _result(true,'success',$list);
    }
    public function video_item(Request $request){
        $id=$request->post('id');
        $carousel = Db::name('article')->where('article_id',$id)->find();
        $carousel['create_time']=date('Y-m-d H:i:s',$carousel['create_time']);
        return _result(true,'success',$carousel);
    }
    /**
     * 获取轮播图
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCarousel(){
        //轮播图
        $carousel = Db::name('image')->order('sort asc,id desc')->select();
        return _result(true,'success',$carousel);
    }

    /**
     * 获取轮播详情
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCarouselDetail(Request $request){
        $carousel_id = intval($request->post('carousel_id'));
        $carousel = Db::name('image')->where('id',$carousel_id)->find();
        $carousel['content'] = htmlspecialchars_decode($carousel['content']);
        return _result(true,'success',$carousel);
    }

    /**
     * 获取游戏房间（赛事）
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getRoomList(Request $request){
        $post = $request->post();
        $game_type = intval($post['game_type']);
        $match_type = intval($post['match_type']);
        $mobile_type = intval($post['mobile_type']);
        $roomgame_type = intval($post['roomgame_type']);
        $page = max(1,intval(isset($post['page']) ? $post['page'] : 1));
        $psize = 5;
        if(empty($game_type)){
            return _result(false,'请选择游戏类型');
        }
        if(empty($match_type)){
            return _result(false,'请选择比赛类型');
        }
        if(empty($mobile_type)){
            return _result(false,'请选择端游类型');
        }
        if(!empty($roomgame_type)){
            $where['roomgame_type'] = $roomgame_type;
        }
        $where['game_type'] = $game_type;
        $where['match_type'] = $match_type;
        $where['mobile_type'] = $mobile_type;
        $where['deleted'] = 0;
        $where['status'] = ['<=',3];
        $field = "id,roomname,game_type,match_type,mobile_type,roomgame_type,enrolltime,reward,reward_type,currency,image,status";
        $roomlist = Db::name('game_room')->where($where)->field($field)->order(' displayorder desc,status asc,id desc ')->page($page,$psize)->select();
        foreach ($roomlist as &$value){
            //查询一下 比赛类型
            $roomgame_type = $this->getRoomClass($value['roomgame_type']);
            $match_type = $this->getRoomClass($value['match_type']);
            $mobile_type = $this->getRoomClass($value['mobile_type']);
            if($match_type['vip_type']==1){
                $roomgame_type['title']='1V1';
                $match_type['title']='王者荣耀';
            }
            $value['pattern'] = [
                $roomgame_type['title'],
                $match_type['title'],
//                $mobile_type['title'],
            ];
            
            $value['game_types']=$match_type['vip_type'];
            $value['enrolltime'] = date("H:i",$value['enrolltime']);
            $value['reward'] = json_decode($value['reward'],true);
            $value['total_reward'] = array_sum($value['reward']);
        }
        unset($value);
        return _result(true,'success',$roomlist);
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


        $roomgame_type = $this->getRoomClass($room['roomgame_type']);
        $match_type = $this->getRoomClass($room['match_type']);
        $mobile_type = $this->getRoomClass($room['mobile_type']);
        $room['surplus_time'] = max(0,intval(($room['readytime'] - $room['enrolltime']) / 3600));

        $room['enrolltime'] = date("m-d H:i",$room['enrolltime']);
        $room['readytime'] = date("m-d H:i",$room['readytime']);
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
                ->order('l.id asc')
                ->limit(5)
                ->select();
        }elseif ($room['game_type'] == 1){
            $user_participate_game_list['teamred'] = Db::name('user_participate_game_list')
                ->alias('l')
                ->join('user u','l.uid = u.id')
                ->field('u.nick_name,u.avatar')
                ->where(['l.room_id'=>['=',$room['id']],'l.team_type'=>['=',1]])
                ->order('l.id asc')
                ->limit(5)
                ->select();
            $user_participate_game_list['teamblue'] = Db::name('user_participate_game_list')
                ->alias('l')
                ->join('user u','l.uid = u.id')
                ->field('u.nick_name,u.avatar')
                ->where(['l.room_id'=>['=',$room['id']],'l.team_type'=>['=',2]])
                ->order('l.id asc')
                ->limit(5)
                ->select();
        }
        $room['user_participate_game_list'] = $user_participate_game_list;
        return _result(true,'success',$room);
    }

    /**
     * 获取参赛用户列表
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserParticipateGameList(Request $request){
        $post = $request->post();
        !isset($post['page']) && $post['page'] = 1;
        $room_id = intval($post['room_id']);
        $page = max(1,intval($post['page']));
        $psize = 10;
        if(empty($room_id)){
            return _result(false,'请选择赛事');
        }
        $user_participate_game_list = Db::name('game_room')
            ->alias('r')
            ->join('user_participate_game_list l',' l.room_id = r.id')
            ->join('user u',' u.id = l.uid')
            ->join('user_game_account a',' a.uid = l.uid')
            ->field('u.nick_name,u.avatar,a.game_name')
            ->where('r.id',$room_id)
            ->group('l.uid')
            ->page($page,$psize)
            ->order('l.id asc')
            ->select();
        return _result(true,'success',$user_participate_game_list);

    }


    /**
     * 获取选择框
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function Selection(){
        $where['status'] = 1;
        $where['deleted'] = 0;
        $where['op_type'] = 4;
        $roomgame_type = Db::name('roomclassify')->where($where)->field('id,title')->select();
        $where['status'] = 1;
        $where['deleted'] = 0;
        $where['op_type'] = 3;
        $mobile_type = Db::name('roomclassify')->where($where)->field('id,title')->select();
        $data = [
            'format'=>$roomgame_type,
            'district'=>$mobile_type
        ];
        return _result(true,'success',$data);
    }

    /**
     * 获取分类
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getRoomClassify(Request $request){
        $post = $request->post();
        $op_type = intval($post['op_type']);
        $parent_id = intval($post['parent_id']);
        if(!in_array($op_type,[1,2,3,4])){
            return _result(false,'请选择分类');
        }
        if(!empty($parent_id) && $op_type == 2){
            $where['parent_id'] = $parent_id;
        }
        $where['op_type'] = $op_type;
        $where['status'] = 1;
        $where['deleted'] = 0;
        $roomclassify = Db::name('roomclassify')->where($where)->field('id,title,image,vip_type')->order('displayorder desc,id desc')->select();
        $notice = [];
        foreach ($roomclassify as &$value){
            if($op_type == 2){
                $value['room_num'] = Db::name('game_room')->where(['game_type'=>$parent_id,'match_type'=>$value['id'],'status'=>1,'deleted'=>0])->count();
                $bonus_list = Db::name('user_participate_game_list')
                    ->alias('p')
                    ->leftJoin('user u','u.id = p.uid')
                    ->leftJoin('game_room r','r.id = p.room_id')
                    ->field('u.nick_name,p.bonus,p.id')
                    ->where(['p.game_type'=>$parent_id,'p.match_type'=>$value['id'],'p.status'=>1,'r.status'=>['>=',5]])
                    ->order('p.id desc')
                    ->limit(5)
                    ->select();
                if(!empty($bonus_list)){
                    $notice = [];
                    foreach ($bonus_list as $item){
                        $notice[] = "用户：".$item['nick_name'].'获得'.$item['bonus']."奖金";
                    }
                    $value['notice'] = $notice;
                }else{
                    $value['notice'] = [];
                }
            }
        }
        unset($value);
//        Db::name('')
        return _result(true,'success',$roomclassify);
    }

    /**
     * 获取绑定游戏帐号操作步骤
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGameAccountStep(Request $request){
        $game_type = intval($request->post('game_type'));
        $setp = Db::name('bing_game_account_step')->where('game_type',$game_type)->find();
        $setp['content'] = htmlspecialchars_decode($setp['content']);
        return _result(true,'success',$setp);

    }

    /**
     * 获取VIP配置
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getVipConfig(){
        $list = DB::table('config_vip')->select();
        return _result(true,'success',$list);
    }

    /**
     * 获取关于我们
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAboutMe(){
        $list = Db::name('article')->where('category',2)->find();
        $list['content'] = htmlspecialchars_decode($list['content']);
        $list['create_time'] = date('Y-m-d H:i',$list['create_time']);
        return _result(true,'success',$list);
    }

    /**
     * 赏金榜
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function bountyList(Request $request){
        $game_type = intval($request->post('game_type'));
        $times = intval($request->post('times'));
        if(!in_array($game_type,[1,2])){
            return _result(false,'未知错误');
        }
        if($times == 1){
            //本日   开始和结束
            $data['start_time'] = strtotime(date('Y-m-d 00:00:00',time()));
            $data['end_time'] = strtotime(date('Y-m-d 23:59:59',time()));
        }elseif ($times == 2){
            //本周  开始和结束
            $data['start_time']=strtotime(date('Y-m-d H:i:s',mktime(0,0,0,date('m'),date('d')-date('w')+1,date('Y'))));
            $data['end_time']=strtotime(date('Y-m-d H:i:s',mktime(23,59,59,date('m'),date('d')-date('w')+7,date('Y'))));
        }elseif ($times == 3){
//            本月  开始和结束
            $data['start_time']=strtotime(date('Y-m-d H:i:s',mktime(0,0,0,date('m'),1,date('Y'))));
            $data['end_time']=strtotime(date('Y-m-d H:i:s',mktime(23,59,59,date('m'),date('t'),date('Y'))));
        }
        $where = ['game_type'=>$game_type];
        if(!empty($data)){
            $where['createtime'] = ['between',$data['start_time'].",".$data['end_time']];
        }
        $bounty_list = Db::name('bounty_list')
            ->alias('b')
            ->leftJoin('user u','u.id = b.uid')
            ->field('u.nick_name,u.avatar,sum(b.bonus) as total_bonus')
            ->where($where)
            ->order('total_bonus desc')
            ->group('b.uid')
            ->limit(100)
            ->select();
        $config_robot=Db::table('config')->where('key','robot_data_on')->value('value');
        $rank_count=0;
        if($config_robot==1){
            if($game_type==1){
                if($times==1){
                    $bounty_list=cache('robot_data_one_2');
                    if(empty($bounty_list)){
                       $user_list=Db::table('user')->where('id','<=',130)->orderRaw('rand()')->limit(3)->select(); 
                        $bounty_list=[];
                        foreach ($user_list as $key=>$value){
                            if($key==0){
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(300,500)];
                            }elseif($key==1){
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(200,300)];
                            }else{
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(60,200)]; 
                            }
                            $bounty_list[]=$arr;
                        }
                        Cache::set('robot_data_one_2',$bounty_list,21600); 
                    }
                    $rank_count=$this->randomFloat(1000,3000);
                }elseif($times==2){
                    $bounty_list=cache('robot_data_two_2');
                    if(empty($bounty_list)){
                       $user_list=Db::table('user')->where('id','<=',130)->orderRaw('rand()')->limit(3)->select(); 
                        $bounty_list=[];
                        foreach ($user_list as $key=>$value){
                            if($key==0){
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(2000,4700)];
                            }elseif($key==1){
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(1000,2000)];
                            }else{
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(600,1000)]; 
                            }
                            $bounty_list[]=$arr;
                        }
                        Cache::set('robot_data_two_2',$bounty_list,21600); 
                    }
                    $rank_count=$this->randomFloat(1000,3000);
                }else{
                    $bounty_list=cache('robot_data_three_2');
                    if(empty($bounty_list)){
                       $user_list=Db::table('user')->where('id','<=',130)->orderRaw('rand()')->limit(3)->select(); 
                        $bounty_list=[];
                        foreach ($user_list as $key=>$value){
                            if($key==0){
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(12000,29999)];
                            }elseif($key==1){
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(8000,12000)];
                            }else{
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(5000,8000)]; 
                            }
                            $bounty_list[]=$arr;
                        }
                        Cache::set('robot_data_three_2',$bounty_list,21600); 
                    }
                    $rank_count=$this->randomFloat(10000,20000);
                }
            }else{
                if($times==1){
                    $bounty_list=cache('robot_data_one_2');
                    if(empty($bounty_list)){
                       $user_list=Db::table('user')->where('id','<=',130)->orderRaw('rand()')->limit(3)->select(); 
                        $bounty_list=[];
                        foreach ($user_list as $key=>$value){
                            if($key==0){
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(300,500)];
                            }elseif($key==1){
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(200,300)];
                            }else{
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(60,200)]; 
                            }
                            $bounty_list[]=$arr;
                        }
                        Cache::set('robot_data_one_2',$bounty_list,21600); 
                    }
                    $rank_count=$this->randomFloat(1000,3000);
                }elseif($times==2){
                    $bounty_list=cache('robot_data_two_2');
                    if(empty($bounty_list)){
                       $user_list=Db::table('user')->where('id','<=',130)->orderRaw('rand()')->limit(3)->select(); 
                        $bounty_list=[];
                        foreach ($user_list as $key=>$value){
                            if($key==0){
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(2000,4700)];
                            }elseif($key==1){
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(1000,2000)];
                            }else{
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(600,1000)]; 
                            }
                            $bounty_list[]=$arr;
                        }
                        Cache::set('robot_data_two_2',$bounty_list,21600); 
                    }
                    $rank_count=$this->randomFloat(1000,3000);
                }else{
                    $bounty_list=cache('robot_data_three_2');
                    if(empty($bounty_list)){
                       $user_list=Db::table('user')->where('id','<=',130)->orderRaw('rand()')->limit(3)->select(); 
                        $bounty_list=[];
                        foreach ($user_list as $key=>$value){
                            if($key==0){
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(12000,29999)];
                            }elseif($key==1){
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(8000,12000)];
                            }else{
                               $arr=['nick_name'=>$value['nick_name'],'avatar'=>$value['avatar'],'total_bonus'=>$this->randomFloat(5000,8000)]; 
                            }
                            $bounty_list[]=$arr;
                        }
                        Cache::set('robot_data_three_2',$bounty_list,21600); 
                    }
                    $rank_count=$this->randomFloat(10000,20000);
                }
            }
            
            
        }
        if($config_robot==1){
            $total_bonus = Db::name('bounty_list')->where('game_type',$game_type)->sum('bonus');
            $total_bonus=$total_bonus*5;
            foreach ($bounty_list as $value){
                $total_bonus+=$value['total_bonus'];
            }
            $total_bonus=$rank_count+$total_bonus;
            $total_bonus = sprintf("%.2f",$total_bonus); 
            
        }else{
           $total_bonus = Db::name('bounty_list')->where('game_type',$game_type)->sum('bonus');
           $total_bonus = sprintf("%.2f",$total_bonus); 
        }
        
        return _result(true,'success',['total_bonus'=>$total_bonus,'list'=>$bounty_list]);
    }
    function randomFloat($min = 0, $max = 1) {
   	  $num = $min + mt_rand() / mt_getrandmax() * ($max - $min);
   	  return sprintf("%.2f",$num);  //控制小数后几位
	}
    /**
     * 获取登录状态
     * @return \think\response\Json
     */
    public function getLoginStatus(){
        $info = Session::get(self::SESSION_NAME);
        if(!empty($info)){
            return _result(true,'已登录',['login_status'=>1]);
        }
        return _result(true,'未登录',['login_status'=>0]);
    }

    /**
     * 获取协议
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAgreement(Request $request){
        $type = intval($request->post('type'));
        $list = [];
        if($type){
            $list = Db::name('agreement')->where(['id'=>$type])->find();
            $list['content'] = htmlspecialchars_decode($list['content']);
        }

        return _result(true,'success',$list);
    }

    /**
     * 获取钻石价格列表
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getDiamondsSet(){
        $list = Db::name('diamonds_set')->select();
        return _result(true,'success',$list);
    }

    /**
     * 获取VIP详情
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getVipDedetail(){
        $list = Db::name('vipdetail')->select();
        return _result(true,'success',$list);
    }

    /**
     * 获取客服
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCustomer(){
        $list = Db::name('customer')->find();
        $list['createtime'] = date('Y-m-d H:i',$list['createtime']);
        return _result(true,'success',$list);
    }
     /**
     * 获取创建房间门票和奖励
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create_system(){
        $config_menpiao=Db::table('config')->where('key','onegame')->find();
        $config_reward=Db::table('config')->where('key','one_game_reward')->find();
        $config_menpiao=$config_menpiao['value'];
        $config_reward=$config_reward['value'];
        $ticket=Db::table('game_ticket')->find($config_menpiao);
        $arr=[
            'reward'=>$config_reward,
            'menpiao'=>$ticket
        ];
        
        return _result(true,'success',$arr);
    }
    /**
     * 获取分类
     * @param $class_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getRoomClass($class_id){
        $where['id'] = $class_id;
        $where['status'] = 1;
        $where['deleted'] = 0;
        $roomclassify = Db::name('roomclassify')->where($where)->field('id,title,vip_type')->find();
        return $roomclassify;
    }
    

}