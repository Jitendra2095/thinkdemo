<?php
namespace app\admin\controller;

use app\common\entity\ManageUser;
use app\common\entity\Name;
use think\Controller;
use think\Db;
use think\Request;

class Login extends Controller
{
    public function index()
    {
        return $this->fetch('index');
    }

    /**
     * 登录处理
     */
    public function login(Request $request)
    {
        $service = new \app\admin\service\rbac\Users\Service();
        $result = $this->validate($request->post(), 'app\admin\validate\LoginForm');

        if (true !== $result) {
            return json()->data(['code' => 1, 'message' => $result]);
        }
        $key = $request->post('key');
        if($key != 'z#vHFzQXA@Sbm6H$h5coKjQGcu2g8**A'){
            // return json()->data(['code'=>1,'message'=>'秘钥错误']);
        }
        $validate = $service->doLogin($request->post('username'), $request->post('password'));
        if (true === $validate) {

            $power = new \app\admin\service\rbac\Power\Service();
            $power->delCache();
            $id = $service->getManageId();
            $left_uid = ManageUser::where('id',$id)->value('left_uid');
            if($left_uid){
                return json()->data(['toUrl' => url('User/index')]);
            }else{
                return json()->data(['toUrl' => url('index/index')]);
            }
        }else{
            return json()->data(['code'=>1,'message'=>$validate]);
        }
    }
}