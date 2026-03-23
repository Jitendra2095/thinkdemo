<?php

namespace app\common\entity;

use think\Db;
use think\Exception;
use think\Model;

class MyWallet extends Model {


    /**
     * @var string 对应的数据表名
     */
    protected $table = 'my_wallet';

    protected $createTime = 'create_time';

    protected $autoWriteTimestamp = false;

    //充值
    public function RechargeLog($query,$data)
    {
        $oldInfo = $this->where('uid',$data['uid'])->find();
        Db::startTrans();
        try {
            $edit_data['bond'] = $data['num'] + $oldInfo['bond'];
            $edit_data['update_time']  = time();
            $query->where('uid',$data['uid'])->update($edit_data);
            $create_data = [
                'uid' => $data['uid'],
                'number' => $data['num'],
                'old' => $oldInfo['number'],
                'new' => $oldInfo['number'] + $data['num'],
                'remark' => $data['remark'],
                'types' => 1,
                'status' => 1,
                'money_type' => 1,
                'create_time' => time(),
            ];
            MyWalletLog::insert($create_data);
            $star_level = ConfigTeamLevelModel::where('assure_money',$data['num'])
                ->value('id');
            if($star_level){
                User::where('id',$data['uid'])->update([
                    'star_level' => $star_level,
                ]);
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
    }
    //提现 添加记录
    public function takeMoney($query,$data)
    {
        $oldInfo = $this->where('uid',$data['uid'])->find();
        Db::startTrans();
        try {
            $edit_data['number'] = $oldInfo['number'] - $data['num'];
            $old = $oldInfo['number'];
            $edit_data['update_time']  = time();
            $query->where('uid',$data['uid'])->update($edit_data);
            $create_data = [
                'uid' => $data['uid'],
                'number' => $data['num'],
                'old' => $old,
                'new' => $old - $data['num'],
                'remark' => $data['remark'],
                'types' => 2,
                'status' => 2,
                'money_type' => 1,
                'create_time' => time(),
            ];
            MyWalletLog::insert($create_data);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
    }
    // 提现 拒绝退还金额
    public function refuseWithdrawal($query,$data)
    {
        $oldInfo = $this->where('uid',$data['uid'])->find();
        Db::startTrans();
        try {
            $edit_data['number'] = $oldInfo['number'] + $data['num'];
            $old = $oldInfo['number'];
            $edit_data['update_time']  = time();
            $query->where('uid',$data['uid'])->update($edit_data);

            $create_data = [
                'uid' => $data['uid'],
                'number' => $data['num'],
                'old' => $old,
                'new' => $old + $data['num'],
                'remark' => $data['remark'],
                'types' => 3,
                'status' => 1,
                'money_type' => 2,
                'create_time' => time(),
            ];
            MyWalletLog::insert($create_data);

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
    }
    // 转账
    public function transfer($query,$data)
    {

        $myInfo = $this->where('uid',$data['uid'])->find();
        Db::startTrans();
        try {

            if($data['types'] == 1){
                //兑换兑出余额
                $my_edit['bond'] = $myInfo['bond'] - $data['num'];
                $my_old = $myInfo['bond'];
                $money_type = 1;
            }else{
                $my_edit['agent'] = $myInfo['agent'] - $data['num'];
                $my_old = $myInfo['agent'];
                $money_type = 3;
            }
            $to_old = $myInfo['number'];
            $my_edit['update_time']  = time();
            $my_edit['number']  =  $myInfo['number'] + $data['num'];
            $query->where('uid',$data['uid'])->update($my_edit);

            //兑换兑出流水详细
            $my_data = [
                'uid' => $data['uid'],
                'from' => $data['uid'],
                'number' => $data['num'],
                'old' => $my_old,
                'new' => $my_old - $data['num'],
                'remark' => $data['my_remark'],
                'types' => 4,
                'status' => 2,
                'money_type' => $money_type,
                'create_time' => time(),
            ];
            $to_data = [
                'uid' => $data['uid'],
                'from' => $data['uid'],
                'number' => $data['num'],
                'old' => $to_old,
                'new' => $to_old + $data['num'],
                'remark' => $data['to_remark'],
                'types' => 4,
                'status' => 1,
                'money_type' => 2,
                'create_time' => time(),
            ];

            MyWalletLog::insert($my_data);
            MyWalletLog::insert($to_data);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
    }

   /**
    * 任务结算佣金
    */
   public function taskMoney($query,$data)
   {
       $oldInfo = $this->where('uid',$data['uid'])->find();
       // dump($oldInfo);die;
       Db::startTrans();
       try {
           $edit_data['number'] = $oldInfo['number'] + $data['number'];
           $old_number = $oldInfo['number'];
           $edit_data['update_time']  = time();
           $res = $query->where('uid',$data['uid'])->update($edit_data);
           if (!$res) {
               throw new Exception();
           }
           $create_data = [
               'uid' => $data['uid'],
               'number' => $data['number'],
               'old' => $old_number,
               'new' => $old_number + $data['number'],
               'remark' => '任务佣金增加增加余额',
               'types' => 5,
               'status' => 1,
               'money_type' => 2,
               'create_time' => time(),
           ];
           $res = MyWalletLog::insert($create_data);
           if (!$res) {
               throw new Exception();
           }
           Db::commit();
           return true;
       } catch (\Exception $e) {
           Db::rollback();
           return false;
       }
   }

    /**
     * 购买保证金套餐扣除余额
     */
    public function lootVipMoney($query,$data)
    {
        $oldInfo = $this->where('uid',$data['uid'])->find();
        Db::startTrans();
        try {
            $edit_data['number'] = $oldInfo['number'] - $data['num'];
            $edit_data['bond'] = $oldInfo['bond'] + $data['num'];
            $old_number = $oldInfo['number'];
            $old_bond = $oldInfo['bond'];
            $edit_data['update_time']  = time();
            $query->where('uid',$data['uid'])->update($edit_data);
            $create_data = [
                'uid' => $data['uid'],
                'number' => $data['num'],
                'old' => $old_number,
                'new' => $old_number - $data['num'],
                'remark' => $data['remark'],
                'types' => 8,
                'status' => 2,
                'money_type' => 2,
                'create_time' => time(),
            ];
            MyWalletLog::insert($create_data);
            // $create_data = [
            //     'uid' => $data['uid'],
            //     'number' => $data['num'],
            //     'old' => $old_bond,
            //     'new' => $old_bond + $data['num'],
            //     'remark' => $data['remark'],
            //     'types' => 4,
            //     'status' => 1,
            //     'money_type' => 1,
            //     'create_time' => time(),
            // ];
            // MyWalletLog::insert($create_data);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
    }
    /**
     * 下级佣金结算
     */
    public function retailStore($query,$data)
    {
        $oldInfo = $this->where('uid',$data['uid'])->find();
        Db::startTrans();
        try {
            $edit_data['number'] = $oldInfo['number'] + $data['number'];
            $old_number = $oldInfo['number'];
            $edit_data['update_time']  = time();
            $res = $query->where('uid',$data['uid'])->update($edit_data);
            if (!$res) {
                throw new Exception();
            }
            $create_data = [
                'uid' => $data['uid'],
                'number' => $data['number'],
                'old' => $old_number,
                'new' => $old_number + $data['number'],
                'remark' => '下级任务佣金',
                'types' => 6,
                'status' => 1,
                'money_type' => 2,
                'create_time' => time(),
            ];
            $res = MyWalletLog::insert($create_data);
            if (!$res) {
                throw new Exception();
            }
            Db::commit();
//            return true;
        } catch (\Exception $e) {
            Db::rollback();
//            return false;
        }
    }


}
