<?php

namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\Export;
use redis\RedisCluster;
use think\cache\driver\Redis;
use think\Db;
use think\Request;
use think\Route;
use app\common\entity\User;

class Room extends Admin {

    /**
     * @power 房间列表
     * @rank 5
     */
    public function roomlist(Request $request) {
        
       $type = $request->get('types');
       $map=[];
       if($type){
          $map['match_type'] = $type;
       }
       $list = Db::name('game_room')->where($map)->where(['deleted'=>0])->order('displayorder desc,status asc,id desc')->paginate(10);
        $items = $list->items();
        foreach ($items as $key=>&$item){
            $item['game_type'] = $this->getRoomclassify($item['game_type']);
            $item['match_type'] = $this->getRoomclassify($item['match_type']);
            $item['mobile_type'] = $this->getRoomclassify($item['mobile_type']);
            $item['roomgame_type'] = $this->getRoomclassify($item['roomgame_type']);
            $item['ticket_id'] = Db::name('game_ticket')->where('id',$item['ticket_id'])->value('ticketname');
            $item['enrolltime'] = date("Y-m-d H:i",$item['enrolltime']);
            $item['readytime'] = date("Y-m-d H:i",$item['readytime']);
            $item['starttime'] = date("Y-m-d H:i",$item['starttime']);
            $item['examinetime'] = !empty($item['examinetime']) ? date("Y-m-d H:i",$item['examinetime']) : "";
            $item['endtime'] = !empty($item['endtime']) ? date("Y-m-d H:i",$item['endtime']) : "";
            switch ($item['status']){
                case 0:
                    $item['statustext'] = "准备报名";
                    break;
                case 1:
                    $item['statustext'] = "开始报名";
                    break;
                case 2:
                    $item['statustext'] = "赛前准备";
                    break;
                case 3:
                    $item['statustext'] = "比赛开始";
                    break;
                case 4:
                    $item['statustext'] = "结果审核";
                    break;
                case 5:
                    $item['statustext'] = "比赛结束";
                    break;
            }
            $item['reward'] = json_decode($item['reward'],true);
            if($item['reward_type'] == 1){
                if(empty($item['reward'][0])){
                    $item['reward'][0]=1;
                }
                    
                $item['reward'] = "按参赛人数奖励：".$item['reward'][0].'元/人';
            }elseif ($item['reward_type'] == 2){
                $currency = $item['currency'] == 1 ? "元": "金币";
                $reward_msg = "按排名奖励：<br> ";
                foreach ($item['reward'] as $key=>$value){
                    if($key <= 10){
                        $reward_msg .= "第".($key)."名奖励：".$value.$currency."/人<br>";
                    }
                    if($key == 11){
                        $reward_msg .= "第11-20名奖励：".$value.$currency."/人<br>";
                    }
                    if($key == 12){
                        $reward_msg .= "第21-50名奖励：".$value.$currency."/人<br>";
                    }
                    if($key == 13){
                        $reward_msg .= "第51-100名奖励：".$value.$currency."/人<br>";
                    }
                }
                unset($value);
                $item['reward'] = $reward_msg;
            }elseif($item['reward_type']==3){
                if(!empty($item['reward'][0])){
                    $item['reward'] = "胜利者奖励：".$item['reward'][0].'元';
                }else{
                    $item['reward'] = '奖励设置错误';
                }
                
            }
            if($item['reward_type'] == 1){
                $item['currency'] = "现金";
            }else{
                $item['currency'] = $item['currency'] == 1 ? "现金" : "金币";
            }
            $item['reward_type'] = $item['reward_type'] == 1 ? "参赛人数" : "排名";
        }
        unset($item);
        $types=Db::table('roomclassify')->where('op_type',2)->select();
        
        return $this->render('list', [
            'list' => $list,
            'items' => $items,
            'types'=>$types
        ]);
    }

    /**
     * 获取分类
     * @param $class_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getRoomclassify($class_id){
        $roomclassify = Db::name('roomclassify')->where('id',$class_id)->field('id,op_type,title')->find();
        return $roomclassify;
    }


    /**
     * @power 创建房间
     */
    public function create(Request $request) {
        $game_type = Db::name('roomclassify')->where(['op_type'=>1,'deleted'=>0])->field('id,op_type,title')->select();
        $match_type = Db::name('roomclassify')->where(['op_type'=>2,'deleted'=>0])->field('id,op_type,title')->select();
        $mobile_type = Db::name('roomclassify')->where(['op_type'=>3,'deleted'=>0])->field('id,op_type,title')->select();
        $roomgame_type = Db::name('roomclassify')->where(['op_type'=>4,'deleted'=>0])->field('id,op_type,title')->select();
        $ticket = Db::name('game_ticket')->where(['deleted'=>['=',0]])->field('*')->select();
        if(empty($game_type)){
            $this->error('请先添加游戏分类',url('/admin/roomclassify/index/render/game'),[],1);
        }elseif (empty($match_type)){
            $this->error('请先添加比赛类型分类',url('/admin/roomclassify/index/render/match'),[],1);
        }elseif (empty($mobile_type)){
            $this->error('请先添加端游类型分类',url('/admin/roomclassify/index/render/mobile'),[],1);
        }elseif (empty($roomgame_type)){
            $this->error('请先添加房间赛制分类',url('/admin/roomclassify/index/render/roomgame'),[],1);
        }elseif (empty($ticket)){
            $this->error('请先添加门票',url('/admin//room/ticket'),[],1);
        }
        return $this->render('edit', [
            'game_type' => $game_type,
            'match_type' => $match_type,
            'mobile_type' => $mobile_type,
            'ticket' => $ticket,
            'roomgame_type' => $roomgame_type,
        ]);
    }

    /**
     * 获取比赛类型
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getMatchType(Request $request){
        $post = $request->post();
        $game_type_id = $post['game_type_id'];
        if(empty($game_type_id)){
            return json(['code' => 400,'message'=>'请先选择游戏类型']);
        }
        $match_type = Db::name('roomclassify')->where('parent_id',$game_type_id)->field('id,op_type,title')->select();
        if(empty($match_type)){
            return json(['code' => 500,'message'=>'请先添加比赛类型分类']);
        }
        return json(['code' => 200,'data'=>$match_type]);
    }

    /**
     * @power 修改房间属性
     */
    public function edit($id) {
        $info = Db::name('game_room')->where('id',$id)->find();
        $info['reward'] = json_decode($info['reward'],true);
        $roomclassify = Db::name('roomclassify')->where('id',$info['match_type'])->field('id,op_type,title,parent_id')->find();
        $game_type = Db::name('roomclassify')->where(['op_type'=>1,'deleted'=>0])->field('id,op_type,title')->select();
        $match_type = Db::name('roomclassify')->where(['op_type'=>2,'deleted'=>0,'parent_id'=>$roomclassify['parent_id']])->field('id,op_type,title')->select();
        $mobile_type = Db::name('roomclassify')->where(['op_type'=>3,'deleted'=>0])->field('id,op_type,title')->select();
        $roomgame_type = Db::name('roomclassify')->where(['op_type'=>4,'deleted'=>0])->field('id,op_type,title')->select();
        $ticket = Db::name('game_ticket')->where(['deleted'=>['=',0]])->field('*')->find();
        $info['enrolltime'] = date("Y-m-d H:i",$info['enrolltime']);
        $info['readytime'] = date("Y-m-d H:i",$info['readytime']);
        $info['starttime'] = date("Y-m-d H:i",$info['starttime']);
        return $this->render('edit', [
            'info' => $info,
            'game_type' => $game_type,
            'match_type' => $match_type,
            'mobile_type' => $mobile_type,
            'ticket' => $ticket,
            'roomgame_type' => $roomgame_type,
        ]);
    }

    /**
     * @power 添加房间
     */
    public function save(Request $request) {
        $res = $this->validate($request->post(), 'app\admin\validate\Room');
        $post = $request->post();
//        $post['']
        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }
        $reward = [];

        if($post['reward_type'] == 1){
            $reward = array_filter($post['reward']);
        }elseif ($post['reward_type'] == 2){
            if(!in_array($post['currency'],[1,2])){
                return json()->data(['code' => 1, 'message' => "请先选择奖励币种"]);
            }
            $reward = array_filter($post['reward']);
        }
        if(empty($reward)){
            return json()->data(['code' => 1, 'message' => "请填写奖励"]);
        }
        $post['room_url'] = str_replace(array("&amp;"), "&", $post['room_url']);
        $data = [
            'roomname'=>$post['roomname'],
            'image'=>$post['image'],
            'game_type'=>$post['game_type'],
            'match_type'=>$post['match_type'],
            'mobile_type'=>$post['mobile_type'],
            'ticket_id'=>$post['ticket_id'],
            'roomgame_type'=>$post['roomgame_type'],
            'reward_type'=>$post['reward_type'],
            'reward'=>json_encode($reward),
            'currency'=>empty($post['currency']) ? 1 : $post['currency'],
            'enrolltime'=>strtotime($post['enrolltime']),
            'readytime'=>strtotime($post['readytime']),
            'starttime'=>strtotime($post['starttime']),
            'room_url'=>$post['room_url'],
            'rule'=>$post['rule'],
            'displayorder'=>$post['displayorder'],
            'createtime'=> time(),
        ];
        $room_id = Db::table('game_room')->insertGetId($data);
//        $room_id = Db::insertGetId($data);
        if (!$room_id) {
            return json()->data(['code' => 1, 'message' => "创建失败"]);
        }
//        $RedisCluster = new RedisCluster();
//        $redis = $RedisCluster->getRedis();
//        $exists = $redis->exists(md5("room_id_ticket_".$room_id));
//        if($exists){
//            return json()->data(['code' => 1, 'message' => "该场比赛还未结束"]);
//        }
//        if($redis){
//            if($post['game_type'] == 1 && !$exists){
//                //储存redis
//                for ($i = 1;$i<=10;$i++){
//                    $redis->lpush(md5("room_id_ticket_".$room_id),$i);
//                }
//            }
//        }
//        Db::name('game_ticket')->where('id',$post['ticket_id'])->update(['room_id'=>$room_id]);
        //添加用户提醒
//        if($request->post('category')==1 && $request->post('status')==1){
//            User::update(['roomclassify'=>1],['roomclassify' => 0]);
//        }
        return json(['code' => 0, 'toUrl' => url('/admin/room/roomlist')]);
    }

    /**
     * @power 修改房间属性
     */
    public function update(Request $request, $id) {
        $res = $this->validate($request->post(), 'app\admin\validate\Room');
        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }
        $post = $request->post();
        $reward = [];
        if($post['reward_type'] == 1){
            $reward = array_filter($post['reward']);
        }elseif ($post['reward_type'] == 2){
            if(!in_array($post['currency'],[1,2])){
                return json()->data(['code' => 1, 'message' => "请先选择奖励币种"]);
            }
            $reward = array_filter($post['reward']);
        }elseif ($post['reward_type'] == 3){
            $reward = array_filter($post['reward']);
        }
        if(empty($reward)){
            return json()->data(['code' => 1, 'message' => "请填写奖励"]);
        }
        $post['room_url'] = str_replace(array("&amp;"), "&", $post['room_url']);
        $data = [
            'roomname'=>$post['roomname'],
            'image'=>$post['image'],
            'game_type'=>$post['game_type'],
            'match_type'=>$post['match_type'],
            'mobile_type'=>$post['mobile_type'],
            'roomgame_type'=>$post['roomgame_type'],
            'reward_type'=>$post['reward_type'],
            'reward'=>json_encode($reward),
            'currency'=>$post['currency'],
            'enrolltime'=>strtotime($post['enrolltime']),
            'readytime'=>strtotime($post['readytime']),
            'starttime'=>strtotime($post['starttime']),
            'room_url'=>$post['room_url'],
            'rule'=>$post['rule'],
            'displayorder'=>$post['displayorder'],
        ];
        $result = Db::table('game_room')->where('id',$id)->update($data);
        if (!$result) {
            return json()->data(['code' => 1, 'message' => "修改失败"]);
        }

        return json(['code' => 0, 'toUrl' => url('/admin/room/roomlist')]);
    }

    /**
     * 导出留言
     */
    public function exportMessage(Request $request) {
        $export = new Export();
        $entity = \app\common\entity\Message::field('m.*,u.mobile, u.nick_name')->alias('m');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'mobile':
                    $entity->where('u.mobile', $keyword);
                    break;
                case 'nick_name':
                    $entity->where('u.nick_name', $keyword);
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $list = $entity->leftJoin("user u", 'm.user_id = u.id')
            ->order('m.create_time', 'desc')
            ->select();
        $filename = '留言列表';
        $header = array('会员昵称', '会员账号', '内容', '提交时间');
        $index = array('nick_name', 'mobile', 'content', 'create_time');
        $export->createtable($list, $filename, $header, $index);
    }

    /**
     * @power 删除
     */
    public function delete(Request $request, $id) {
        $entity = $this->checkInfo($id);
        if(empty($entity)){
            return json()->data(['code' => 1, 'message' => "房间不存在"]);
        }
        $result = Db::name('game_room')->where('id',$id)->update(['deleted'=>1]);
        if(!$result){
            return json()->data(['code' => 1, 'message' => "删除失败"]);
        }
//        $RedisCluster = new RedisCluster();
//        $redis = $RedisCluster->getRedis();
//        $exists = $redis->exists(md5("room_id_ticket_".$id));
//        if($exists){
//            $redis->del(md5("room_id_ticket_".$id));
//        }
        return json(['code' => 0, 'message' => 'success']);
    }

    public function deleteticket($id){
        $entity = Db::name('game_ticket')->where(['id'=>$id,'deleted'=>0])->find();
        if(empty($entity)){
            return json()->data(['code' => 1, 'message' => "门票不存在"]);
        }
        $result = Db::name('game_ticket')->where('id',$id)->update(['deleted'=>1]);
        if(!$result){
            return json()->data(['code' => 1, 'message' => "删除失败"]);
        }
//        $room = Db::name('game_room')->where('ticket_id',$id)->field('id')->select();
//        $RedisCluster = new RedisCluster();
//        $redis = $RedisCluster->getRedis();
//        foreach ($room as $value){
//            $exists = $redis->exists(md5("room_id_ticket_".$value['id']));
//            if($exists){
//                $redis->del(md5("room_id_ticket_".$value['id']));
//            }
//        }
        return json(['code' => 0, 'message' => 'success']);
    }

    private function checkInfo($id) {
        $entity = Db::name('game_room')->where(['id'=>$id,'deleted'=>0])->find();
        return $entity;
    }

    /**
     * 门票商城
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function ticket(){
        $list = Db::name('game_ticket')
            ->alias('g')
            ->where('g.deleted',0)
            ->order('g.status desc,g.id desc')
            ->paginate(10);
        $items = $list->items();
        foreach ($items as &$value){
//            $game_type = $this->getRoomclassify($value['game_type']);
//            $match_type = $this->getRoomclassify($value['match_type']);
            if($value['status'] == 1){
                $value['statustext'] = "显示";
            }elseif ($value['status'] == 2){
                $value['statustext'] = "不显示";
            }
//            $value['game_type_text'] = $game_type['title'];
//            $value['match_type_text'] = $match_type['title'];
            $value['createtime'] = date("Y-m-d H:i",$value['createtime']);
        }
        return $this->render('ticket', [
            'list' => $list,
            'items' => $items,
        ]);
    }

    /**
     * 添加门票页面
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function addticket(){
        $game_type = Db::name('roomclassify')->where(['op_type'=>1,'deleted'=>0])->field('id,op_type,title')->select();
        $match_type = Db::name('roomclassify')->where(['op_type'=>2,'deleted'=>0])->field('id,op_type,title')->select();
        if(empty($game_type)){
            $this->error('请先添加游戏分类',url('/admin/roomclassify/index/render/game'),[],1);
        }
        return $this->render('editticket', [
            'game_type' => $game_type,
            'match_type' => $match_type,
        ]);
    }
    /**
     * 修改门票页面
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function editticket($id){
        $ticket = Db::name('game_ticket')->where(['id'=>$id,'deleted'=>0])->find();
        $ticket['commission'] = json_decode($ticket['commission'],true);
        return $this->render('editticket', [
            'info' => $ticket,
        ]);
    }

    public function updateticket(Request $request,$id){
        $post = $request->post();
        $res = $this->validate($post, 'app\admin\validate\Ticket');
        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }
        $ticket = Db::name('game_ticket')->where(['id'=>$id,'deleted'=>0])->find();
        if(empty($ticket)){
            return json()->data(['code' => 1, 'message' => "该门票不存在"]);
        }
        $data = [
            'ticketname'=>$post['ticketname'],
            'commission'=>json_encode($post['commission']),
//            'game_type'=>$post['game_type'],
//            'match_type'=>$post['match_type'],
            'ticket_type'=>$post['ticket_type'],
            'ticket_match_type'=>$post['ticket_match_type'],
            'price'=>$post['price'],
            'image'=>$post['image'],
            'vip_price'=>$post['vip_price'],
            'is_vip'=>$post['is_vip'],
            'content'=>$post['content'],
            'status'=>intval($post['status']),
            'displayorder'=>$post['displayorder'],
        ];
        $result = Db::name('game_ticket')->where('id',$id)->update($data);
        if (!$result) {
            return json()->data(['code' => 1, 'message' => "修改失败"]);
        }
        return json(['code' => 0, 'toUrl' => url('/admin/room/ticket')]);

    }


    /**
     * 添加门票
     * @param Request $request
     * @return \think\response\Json
     */
    public function saveticket(Request $request){
        $res = $this->validate($request->post(), 'app\admin\validate\Ticket');
        $post = $request->post();
//        $post['']
        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }
        $data = [
            'ticketname'=>$post['ticketname'],
            'commission'=>json_encode($post['commission']),
//            'game_type'=>$post['game_type'],
//            'match_type'=>$post['match_type'],
            'ticket_type'=>$post['ticket_type'],
            'ticket_match_type'=>$post['ticket_match_type'],
            'price'=>$post['price'],
            'image'=>$post['image'],
            'is_vip'=>$post['is_vip'],
            'vip_price'=>$post['vip_price'],
            'content'=>$post['content'],
            'status'=>intval($post['status']),
            'createtime'=>time(),
            'displayorder'=>$post['displayorder'],
        ];


        $result = Db::table('game_ticket')->insert($data);

        if (!$result) {
            return json()->data(['code' => 1, 'message' => "添加失败"]);
        }

        //添加用户提醒
//        if($request->post('category')==1 && $request->post('status')==1){
//            User::update(['roomclassify'=>1],['roomclassify' => 0]);
//        }
        return json(['code' => 0, 'toUrl' => url('/admin/room/ticket')]);
    }

    /**
     * 参赛人员
     * @param $id
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function participate($id){
        $list = Db::name('game_room')
            ->alias('r')
            ->Join('user_participate_game_list l ','r.id = l.room_id')
            ->Join('user u ','l.uid = u.id')
            ->field('r.roomname,r.id,u.mobile,u.nick_name,u.id as uid,l.createtime,l.id as lid,l.status,l.game_type,r.status as rstatus')
            ->where(['l.room_id'=>$id,'l.status'=>['>=',0]])
            ->order('status asc,id desc')
            ->paginate(10);
        $items = $list->items();
        foreach ($items as &$value){
            $value['game_name'] = Db::name('user_game_account')->where(['uid'=>$value['uid'],'game_type'=>$value['game_type']])->value('game_name');
        }
        return $this->render('participate', [
            'list' => $list,
            'items' => $items,
        ]);
    }

    /**
     * 退票
     * @param $lid
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     *
     */
    public function cancel($lid){
        $user_participate_game_list = Db::name('user_participate_game_list')->where('id',$lid)->find();
        if(empty($user_participate_game_list)){
            return json()->data(['code' => 1, 'message' => "该用户没有参加比赛"]);
        }
        $room = Db::name('game_room')->where('id',$user_participate_game_list['room_id'])->field('status,id')->find();
        if($room['status'] > 1){
            return json()->data(['code' => 1, 'message' => "比赛已开始，无法退票"]);
        }
        Db::startTrans();
        $result = Db::name('user_game_ticket')->where('id',$user_participate_game_list['ticket_id'])->update(['status'=>0]);
        if(!$result){
            Db::rollback();
            return json()->data(['code' => 1, 'message' => "退票失败"]);
        }
        $result = Db::name('user_participate_game_list')->where('id',$user_participate_game_list['id'])->update(['status'=>-1]);
        if(!$result){
            Db::rollback();
            return json()->data(['code' => 1, 'message' => "退票失败"]);
        }
        Db::commit();
        return json(['code' => 0,'message'=>"退票成功" ,'toUrl' => url('/admin/room/participate',['id'=>$room['id']])]);

    }
    /**
     * 填写奖励页面
     * @param $lid
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function write_reward($lid){
        $list = Db::name('user_participate_game_list')
            ->alias('l')
            ->join('game_room r ',' l.room_id = r.id')
            ->field('l.*,r.roomname,r.red_win_img,r.blue_win_img,r.new_game_type')
            ->where('l.id',$lid)
            ->find();
        $roomclassify = $this->getRoomclassify($list['game_type']);
        $list['game_type'] = $roomclassify['title'];
        if(empty($list)){
            $this->error('该会员没有参加比赛');
        }
        return $this->render('write_reward', [
            'list' => $list,
        ]);
    }

    /**
     * 填写奖励
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function savereward(Request $request){
        $res = $this->validate($request->post(), 'app\admin\validate\UserParticipateGameList');
        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }
        $post = $request->post();
        $is_update = $post['is_update'];
        $data = [
            'bonus'=>$post['bonus'],
            'goldcoin'=>$post['goldcoin'],
            'ranking'=>$post['ranking'],
            'winorlose'=>$post['winorlose'],
            'eliminate_num'=>$post['eliminate_num'],
            'win_num'=>$post['win_num'],
            'status'=>1,
        ];
        $user_participate_game_list = Db::name('user_participate_game_list')->where(['id'=>$post['id'],'status'=>['>=',0]])->find();
        if(empty($user_participate_game_list)){
            return json()->data(['code' => 1, 'message' => "该用户没有参加比赛"]);
        }
        $room_info=Db::table('game_room')->find($user_participate_game_list['room_id']);
        if(!$is_update){
            $bounty_list = [
                'uid'=>$user_participate_game_list['uid'],
                'game_type'=>$user_participate_game_list['game_type'],
                'bonus'=>$post['bonus'],
                'createtime'=>time(),
            ];
            Db::name('bounty_list')->insert($bounty_list);
            $user_model = new \app\index\model\User();
            if($post['goldcoin'] > 0){
            	$user_model->setUserWallet($user_participate_game_list['uid'],'credit2',1,2,$post['goldcoin'],$room_info['roomname'].'金币奖励');
            }	
            if($post['bonus'] > 0){
            	$user_model->setUserWallet($user_participate_game_list['uid'],'bonus',1,2,$post['bonus'],$room_info['roomname'].'奖金奖励');
            }
            
        }


//        Db::name('bounty_list')->where(['uid'=>$user_participate_game_list['uid'],'game_type'=>$user_participate_game_list['game_type']])->setInc('bonus',$post['bonus']);
        $result = Db::table('user_participate_game_list')->where('id',$post['id'])->update($data);
        if (!$result) {
            return json()->data(['code' => 1, 'message' => "添加失败"]);
        }
        //添加用户提醒
//        if($request->post('category')==1 && $request->post('status')==1){
//            User::update(['roomclassify'=>1],['roomclassify' => 0]);
//        }
        return json(['code' => 0, 'toUrl' => url('/admin/room/participate',['id'=>$post['room_id']])]);
    }

    /**
     * 折扣卷列表
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function discount(){
        $list = Db::name('discount_list')
            ->where('deleted',0)
            ->order('id desc')
            ->paginate(10);
        $items = $list->items();
        foreach ($items as &$value){
            $value['createtime'] = date("Y-m-d H:i",$value['createtime']);
        }
        return $this->render('discount', [
            'list' => $list,
            'items' => $items,
        ]);
    }

    public function creatediscount(){
        return $this->render('editdiscount', [
        ]);
    }

    public function savediscount(Request $request){
        $post = $request->post();
        if(empty($post['title'])){
            return json()->data(['code' => 1, 'message' => "请填写折扣卷名称"]);
        }
        if(empty($post['discount'])){
            return json()->data(['code' => 1, 'message' => "请填写折扣"]);
        }
        $data = $post;
        $data['createtime'] = time();
        Db::name('discount_list')->insert($data);
        return json(['code' => 0, 'toUrl' => url('/admin/room/discount')]);
    }


    public function updatediscount(Request $request,$id){
        $post = $request->post();
        if(empty($post['title'])){
            return json()->data(['code' => 1, 'message' => "请填写折扣卷名称"]);
        }
        if(empty($post['discount'])){
            return json()->data(['code' => 1, 'message' => "请填写折扣"]);
        }
        Db::name('discount_list')->where('id',$id)->update($post);
        return json(['code' => 0, 'toUrl' => url('/admin/room/discount')]);
    }

    public function deletediscount($id){
        $discount_list = Db::name('discount_list')->where('id',$id)->find();
        if(empty($discount_list)){
            return json()->data(['code' => 1, 'message' => "找不到对象"]);
        }
        Db::name('discount_list')->where('id',$id)->update(['deleted'=>1]);
        return json(['code' => 0, 'toUrl' => url('/admin/room/discount')]);
    }

    public function editdiscount($id){
        $discount_list = Db::name('discount_list')->where(['id'=>$id])->find();
        return $this->render('editdiscount', [
            'info' => $discount_list,
        ]);
    }


    /**
     * 获取房间状态
     * @param $id
     * @param $status
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function roomStatus($id,$status){
        $entity = $this->checkInfo($id);
        if($status == 4){
            $statustext = "结算";
        }else if($status == 5){
            $statustext = "结束";
        }
        if(empty($entity)){
            return json()->data(['code' => 1, 'message' => "房间不存在"]);
        }
        if($entity['status'] >= $status){
            return json()->data(['code' => 1, 'message' => "房间已".$statustext]);
        }
        $data = ['status'=>$status];
        if($status == 5){
            $data['endtime'] = time();
        }
        $result = Db::name('game_room')->where('id',$id)->update($data);

        if(!$result){
            return json()->data(['code' => 1, 'message' => $statustext."失败"]);
        }

        return json(['code' => 0, 'message' => 'success']);
    }



    /**
     * 视频添加
     */
    public function videoadd()
    {
        $info = Video::find();
        return $this->render('videoadd',[
            'info' => $info,
        ]);
    }
    /**
     * 视频保存
     */
    public function videoSave(Request $request)
    {
        $photo = $request->post('photo');
        $add_data = [
            'src' => $photo,
            'create_time' => time(),
        ];
        if(!$photo) return json(['code' => 1, 'message' => '请选择视频']);
        $list = Video::select();
        foreach ($list as $v){

            if( file_exists('.'.$v['src'])){
                unlink('.'.$v['src']);
            }
            Video::where('id',$v['id'])->delete();
        }

        $res = Video::insert($add_data);
        if($res){
            return json(['code' => 0, 'message' => '添加成功']);
        }
        return json(['code' => 1, 'message' => '添加失败']);
    }
    #内容管理|图片列表
    public function image(){
        $list = Db::table('spread_image')->select();
        return $this->render('imagelist',[
            'list' => $list
        ]);
    }

    #内容管理|图片编辑
    public function imageedit(Request $request){
        $id = $request->param('id');
        $list = Db::table('spread_image')->where('id',$id)->find();

        return $this->render('imageedit',[
            'info' => $list
        ]);
    }

    #图片修改
    public function updimage(Request $request)
    {
        $id = $request->param('id');
        $title = $request->post('title');

        $photo = $request->post('photo');
        $sort = $request->post('sort');

        $data = [
            'pic' => $photo,
            'sort' => $sort,
            'title' => $title,
            'update_time' => time()
        ];

        $updphoto = Db::table('spread_image')->where('id',$id)->update($data);
        if ($updphoto){

            return json(['code' => 0, 'message' => '修改成功','toUrl'=>url('image')]);

        }

        return json(['code' => 1, 'message' => '修改失败']);

    }

    #内容管理|图片添加
    public function imageadd(){
        return $this->render('imageedit');
    }

    #图片添加
    public function saveimage(Request $request){

        $photo = $request->post('photo');
        $title = $request->post('title');
        $sort = $request->post('sort');

        $data = [
            'pic' => $photo,
            'title' => $title,
            'sort' => $sort,
            'create_time' => time()
        ];

        $insphoto = Db::table('spread_image')->insert($data);

        if ($insphoto){

            return json(['code' => 0, 'message' => '添加成功','toUrl' => url('image')]);

        }

        return json(['code' => 1, 'message' => '添加失败']);

    }

    #图片删除
    public function imagedel(Request $request){

        $uid = $request->param('id');

        $del = Db::table('spread_image')->where('id',$uid)->delete();

        if ($del){

            return json(['code' => 0, 'message' => '删除成功']);

        }

        return json(['code' => 1, 'message' => '删除失败']);

    }

    public function getOptype($render){
        $optype = 0;
        switch ($render){
            case "game":
                $optype = 1;
                break;
            case "match":
                $optype = 2;
                break;
            case "mobile":
                $optype = 3;
                break;
            case "roomgame":
                $optype = 4;
                break;
        }
        return $optype;
    }



}
