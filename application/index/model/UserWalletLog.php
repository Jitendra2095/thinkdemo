<?php

namespace app\index\model;

use app\common\entity\Config;
use app\common\entity\Log;
use app\common\entity\MyWallet;
use app\common\entity\Orders;
use app\common\entity\Allaward;
use app\common\entity\UserInviteCode;
use app\common\entity\UserLevelConfigModel;
use app\common\entity\UserProduct;
use app\common\service\Users\Cache;
use app\common\service\Users\Identity;
use app\common\service\Users\Service;
use think\Db;
use think\Request;
use app\common\entity\Category;
use app\common\entity\Areaconfig;
use app\common\entity\Cityconfig;
use app\common\entity\Fansconfig;
use app\common\entity\Masterconfig;
use app\common\model\UserCurrency;

class UserWalletLog {

    public function getUserWalletLog($uid,$page = 1,$psize = 10,$field = "*",$credit = ""){
        if(!empty($credit)){
            $where = [
                'uid'=>$uid,
                'credit'=>$credit,
            ];
        }else{
            $where = ['uid'=>$uid];
        }
        $UserWallet = Db::name('user_wallet_log')->where($where)->field($field)->order('id desc')->page($page,$psize)->select();
        foreach ($UserWallet as &$value){
            if($value['op_type'] == 1){
                $op_type = "消费";
            }elseif ($value['op_type'] == 2){
                $op_type = "充值";
            }
            $value['createtime'] = date('Y-m-d H:i',$value['createtime']);
            $value['change_money'] = $value['op_type'] == 1 ? "-".$value['change_money']: "+".$value['change_money'];
            $value['op_type_text'] = $op_type;
        }
        return $UserWallet;
    }

    public function setUserWalletLog($uid,$credit,$types,$op_type,$money,$remarks){
        if(is_array($remarks)){
            $from = $remarks['uid'];
            $remarks = $remarks['msg'];
        }
        $user_credit = Db::name('user_wallet')->where('uid', $uid)->value($credit);
        $after_change_money = 0;
        if($op_type == 1){
            $after_change_money = sprintf("%.2f",($user_credit - $money));
        }elseif ($op_type == 2){
            $after_change_money = sprintf("%.2f",($user_credit + $money));
        }
        $data = [
            'uid'=>$uid,
            'credit'=>$credit,
            'change_money'=>$money,
            'original_money'=>$user_credit,
            'after_change_money'=>$after_change_money,
            'types'=>$types,
            'op_type'=>$op_type,
            'remarks'=>$remarks,
            'createtime'=>time(),
        ];
        if(!empty($from)){
            $data['froms'] = $from;
        }
        $result = Db::name('user_wallet_log')->insert($data);
        return $result;
    }



}
