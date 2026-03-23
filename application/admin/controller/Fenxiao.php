<?php
namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\admin\service\rbac\Users\Service;
use app\common\entity\ManageUser;
use app\common\entity\Orders;
use app\common\entity\User;
use app\common\entity\UserProduct;
use think\Db;
use think\facade\Session;
use think\Request;

class Fenxiao extends Admin
{
	public function index(request $request){
		$mobile = $request->post('mobile');
		$id = $request->post('id');

		$where = [];
		if($mobile){
			$where = ['mobile',$mobile];
		}
		if($id){
			$where = ['id',$id];
		}
		if(!$id || !$mobile){

			$list = DB::table('user')->field('id,mobile,level,nick_name,register_time')->where('pid',0)->select();
		}else{
			$list = DB::table('user')->where($where)->field('id,mobile,level,nick_name,register_time')->select();

		}
		
		foreach ($list as $key => &$value) {
					$list[$key]['count'] = DB::table('user')->where('pid',$value['id'])->count();
					$value['register_time'] = date('Y-m-d H:i:s',$value['register_time']);
					$value['num_meber'] =array_sum($this->getChildsInfo($value['id']));
					$value['num_meber_num'] =array_sum($this->getChildsInfo_num($value['id']));

				}
		return $this->render('index3', [
                    'list' => $list,
        ]);

	}

	//查询下级
	public function index1(request $request){
		$id = $request->get('id');
		$where = [];
		if($id){
			$where['pid'] = $id;
		}
		
		$list = DB::table('user')->where($where)->field('id,mobile,level,pid,nick_name,register_time')->select();

		
		foreach ($list as $key => &$value) {
					$list[$key]['count'] = DB::table('user')->where('pid',$value['id'])->count();
					$value['register_time'] = date('Y-m-d H:i:s',$value['register_time']);

				}
		
         
		return json(['code'=>0,'message'=>'请求成功','list'=>$list]);

	}

	    #获取下级
    public function getChildsInfo($uid, $num = 0, &$childs = [], &$level = 0,&$count= 0)
    {
       
//        static $level = 0;
        if (isset($num)) {
            if ($level == $num) {
                // return $childs;
            }
        }
        $child = Db::table('user')->where('pid', $uid)->field('id,nick_name,mobile,level,pid')->select();
        $count = count($child);
        if ($child) {
            $level++;

            $childs[] = $count;
            foreach ($child as $v) {

                $this->getChildsInfo($v['id'], $num, $childs, $level,$count);
            }
        }

        return $childs;

    }

    	    #获取下级
    public function getChildsInfo_num($uid, $num = 0, &$childs = [], &$level = 0,&$count= 0)
    {
       
//        static $level = 0;
        if (isset($num)) {
            if ($level == $num) {
                // return $childs;
            }
        }
        $child = Db::table('user')->where('pid', $uid)->field('id,nick_name,mobile,level,pid')->select();
        $count = count($child);
        if ($child) {
            $level++;

            $childs[] = $count;
            foreach ($child as $v) {

                $this->getChildsInfo($v['id'], $num, $childs, $level,$count);
            }
        }

        return $childs;

    }



    // 判断每个级别有多少人
    public function myTeamTop($userid){
        $member = User::where('id',$userid)->field('id,level,pid,b_money')->find();
        if(empty($member)){
            return json(['code' => '-1', 'message' => '请登陆']);
        }
        $info = DB::table('upgrade')->field('id,name')->select();
        foreach ($info as $key => $value) {
            $ids = $this->myTeamList($userid,$value['id']);
            if(!empty($ids)){
                $ids = rtrim($ids,',');
                $ids = explode(',', $ids);
                $count = count($ids);
            }else{
                $count = 0;
            }

            $info[$key]['count'] = $count;
            $count = $this->getChildsInfo($userid);
        }

         return json(['code'=>'0','message'=>'操作成功','count'=>$count]);

    }
    
     public function myTeamList($id,$level,$ids=''){
        $member = User::where('pid','in',$id)->field('id,level,pid')->select()->toArray();
        if(!empty($member)){
            $ids1 = '';
            $ids2 = '';
            foreach ($member as $key => $value) {
                if($level == $value['level']){
                    $ids1 .= $value['id'].',';
                }
                $ids2 .=  $value['id'].',';
                
            }
            if(!empty($ids1)){
                $ids1 = rtrim($ids1,',');
                $ids .= $ids1.',';
               
            }
            if(!empty($ids2)){
                $ids2 = rtrim($ids2,',');
                return $this->myTeamList($ids2,$level,$ids);
            }
           
        }else{
            return $ids;
        }
        
    }









}