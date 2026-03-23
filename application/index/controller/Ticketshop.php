<?php


namespace app\index\controller;

use app\common\entity\Config;
use app\common\PHPMailer\Exception;
use redis\RedisCluster;
use think\cache\driver\Redis;
use app\index\model\User;
use app\index\model\Game;
use think\Db;
use think\Image;
use think\Request;

class Ticketshop extends Base
{
    /**
     * 获取门票列表
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ticketList(Request $request){
        $uid = $this->userId;
        $post = $request->post();
        $page = max(0,intval(isset($post['page']) ? $post['page'] : 1));
        $psize = 10;
        $game_ticket = Db::name('game_ticket')->where(['status'=>1,'deleted'=>0])->order('displayorder desc,id desc')->page($page,$psize)->select();
        $user_model = new User();
        $user_wallets = $user_model->getUserWallets($uid);
        return _result(true,'success',['list'=>$game_ticket,'credit1'=>$user_wallets['credit1'],'credit2'=>$user_wallets['credit2']]);
    }

    /**
     * 获取门票详情
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ticketDetail(Request $request){
        $post = $request->post();
        $ticket_id = $post['ticket_id'];
        $game_ticket = Db::name('game_ticket')->where(['id'=>$ticket_id,'status'=>1,'deleted'=>0])->find();
        $game_ticket['content'] = htmlspecialchars_decode($game_ticket['content']);
        if(empty($game_ticket)){
            return _result(false,'该门票已下架');
        }
        return _result(true,'success',$game_ticket);
    }

    /**
     * 购买门票
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function purchaseTicket(Request $request){
        Db::startTrans();
        try {
            $uid = $this->userId;
            $post = $request->post();
            $user_discount_id = intval($post['user_discount_id']);
            $ticket_id = intval($post['ticket_id']);
            $is_use_discount = intval($post['is_use_discount']);
            $game_ticket = Db::name('game_ticket')->where(['id'=>$ticket_id,'status'=>1,'deleted'=>0])->find();

            if(empty($game_ticket)){
                throw new Exception('该门票已售空');
            }

            $commission = json_decode($game_ticket['commission'],true);

//            $user_game_ticket = Db::name('user_game_ticket')->where(['uid'=>$uid,'ticket_id'=>$game_ticket['id'],'room_id'=>$game_ticket['room_id']]);
//            if(!empty($user_game_ticket)){
//                throw new Exception('您已购买该门票');
//            }
            $user_model = new User();
//            $game_model = new Game();
//            $room = $game_model->getRoom($game_ticket['room_id']);
//            if(empty($room)){
//                throw new Exception('该赛事不存在或已开始');
//            }
            $userCredits = $user_model->getUserWallets($uid);
            $credit = $game_ticket['ticket_type'] == 1 ? "credit1" : "credit2";
            $userVip = $user_model->getUserVip($uid);
            $ticket_price = $game_ticket['price'];
            if($userVip){
                $ticket_price = $game_ticket['vip_price'];
            }else{
                if($game_ticket['is_vip'] == 1){
                    throw new Exception('该门票需要VIP才能购买');
                }
            }
            if($ticket_price > $userCredits[$credit]){
                throw new Exception('您的账户余额不足');
            }
            if($is_use_discount){
                $user_discount = Db::name('user_discount_list')->where(['id'=>$user_discount_id,'uid'=>$uid,'status'=>0])->find();
                if(empty($user_discount)){
                    throw new Exception('该优惠券不存在或已使用');
                }
                $discount_price = sprintf("%.2f",($ticket_price * $user_discount['discount']) / 100);
                $ticket_price = $ticket_price - $discount_price;
                $result = Db::name('user_discount_list')->where('id',$user_discount['id'])->update(['status'=>1]);
                if(!$result){
                    throw new Exception('优惠券使用失败');
                }
            }
            if($ticket_price < 0){
                $ticket_price = 0;
            }

            //发放佣金
            $remarks = ['msg'=>"推广佣金",'uid'=>$uid];

            if(!empty($commission)){
                $agents = [];
                $user_model->getUserAgents($uid,$agents,3);
                if(!empty($agents)){
                    foreach ($agents as $key =>$value){
                        if(sprintf("%.2f",$commission[$key]) > 0){
                            $result = $user_model->setUserWallet($value['id'],"commission",5,2,sprintf("%.2f",$commission[$key]),$remarks);
                            if($result['code'] == 400){
                                throw new Exception($result['msg']);
                                break;
                            }
                        }
                    }
                    unset($value);
                }
            }
//            $RedisCluster = new RedisCluster();
//            $redis = $RedisCluster->getRedis();
//            $exists = $redis->exists(md5("room_id_ticket_".$game_ticket['room_id']));
//            if($exists){
//                $redis_ticket = $redis->rPop(md5("room_id_ticket_".$game_ticket['room_id']));
//                if(!$redis_ticket){
//                    throw new Exception('该门票已卖完');
//                }
//            }
//            $game_type = $game_model->getRoomClassify($room['game_type']);
//            $match_type = $game_model->getRoomClassify($room['match_type']);
//            $result = $user_model->setUserCredit($uid,$credit,1,$ticket_price,$game_ticket['ticketname'],$game_ticket['id']);
            if($ticket_price > 0){
                $result = $user_model->setUserWallet($uid,$credit,2,1,$ticket_price,$game_ticket['ticketname']);
                if($result['code'] == 400){
                    throw new Exception($result['msg']);
                }
            }
            $time = time();

            $data = [
                'uid'=>$uid,
//                'room_id'=>$room['id'],
                'ticket_id'=>$game_ticket['id'],
                'price'=>$game_ticket['price'],
                'realprice'=>$ticket_price,
//                'price'=>$game_ticket['price'],
//                'game_type'=>$room['game_type'],
//                'match_type'=>$room['match_type'],
                'createtime'=>$time,
                'orvertime'=>strtotime(date("Y-m-d",$time + (86400 * 30))." 00:00:00"),
            ];
            $result = Db::name('user_game_ticket')->insert($data);
            if(!$result){
                throw new Exception('购买失败');
            }
            Db::commit();
            return _result(true,'购买成功');
        }catch (Exception $e){
            Db::rollback();
            return _result(false,$e->getMessage());
        }

    }


    private function getRoomClassify($class_id)
    {
        $where['id'] = $class_id;
        $where['status'] = 1;
        $where['deleted'] = 0;
        $roomclassify = Db::name('roomclassify')->where($where)->field('id,title')->find();
        return $roomclassify;
    }


}