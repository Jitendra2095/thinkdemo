<?php

namespace app\index\controller;

use app\common\entity\User;
use app\common\service\Users\Identity;
use app\index\model\SiteAuth;
use think\Controller;
use think\Request;
use think\Session;

class Base extends Controller
{

    public $userId;
    public $userInfo;

    public function _initialize()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
        header('Access-Control-Allow-Headers:x-requested-with,content-type');
        $this->checkSite();
        $this->checkLogin();
//        $this->checkToken();
        parent::_initialize();
    }

    //判断是否登录
    public function checkLogin()
    {
        $identity = new Identity();
        $userId = $identity->getUserId();
//        dump($_SERVER['REQUEST_URI']);
//        die;
        if ($_SERVER['REQUEST_URI'] != '/index/Index/index' &&
            $_SERVER['REQUEST_URI'] != '/index/Upload/uploadImg' &&
            $_SERVER['REQUEST_URI'] != '/index/Mywallet/thaw' &&
            $_SERVER['REQUEST_URI'] != '/index/Article/articleDetail'&&
            $_SERVER['REQUEST_URI'] != '/friendship/public/index.php/index/Article/articleDetail'&&
            $_SERVER['REQUEST_URI'] != '/index/Article/articleList'
        ) {
            if (!$userId) {
                echo json_encode(['code' => 500, 'msg' => '请先登录','data'=>[]]);
                die;
            }
        }
        if ($userId) {
            $this->userId = $userId;
            $userInfo = User::where('id', $userId)->find();
            if ($_SERVER['REQUEST_URI'] != '/index/Index/index' &&
                $_SERVER['REQUEST_URI'] != '/index/Upload/uploadImg' &&
                $_SERVER['REQUEST_URI'] != '/index/Mywallet/is_login' &&
                $_SERVER['REQUEST_URI'] != '/index/Access/recharge'&&
                $_SERVER['REQUEST_URI'] != '/index/Article/articleDetail'&&
                $_SERVER['REQUEST_URI'] != '/index.php/index/Article/articleDetail'&&
                $_SERVER['REQUEST_URI'] != '/index/Article/articleList'
            ) {
                if ($userInfo->status == -1) {
                    echo json_encode(['code' => 500, 'msg' => '请先登录','data'=>[]]);
                    die;
                }
            }
            $this->userInfo = $identity->getUserInfo();
            $info = User::where('id', $userId)->find();
            Session::set('username', $this->userInfo->nick_name);
            if ($info['login_time'] != session_id()) {
                echo json_encode(['code' => 500, 'msg' => '请先登录','data'=>[]]);
                die;
            }

        }
    }

    //检查站点
    public function checkSite()
    {
        $switch = SiteAuth::checkSite();
        if ($switch !== true) {
            echo json_encode(['code' => 500, 'msg' => $switch,'data'=>[]]);
        }
    }
    //检查token
    public function checkToken()
    {

        $request = Request::instance();
        $token = $request->param('token');
        if(!$token){
            echo json_encode(['code' => 500, 'msg' => 'token错误','data'=>[]]);
            die;
        }
        $res = checkToken($token);
        if(!$res){
            echo json_encode(['code' => 500, 'msg' => 'token错误','data'=>[]]);
            die;
        }
    }
    /**
     * ajax 返回
     * @param type $data
     * @param type $info
     * @param type $status
     * @return type
     */
    public function ajaxreturn($data, $info, $status = false)
    {
        return json([
            'status' => $status,
            'info' => $info,
            'data' => $data
        ]);
    }

    /**
     * 空操作
     * @return [type] [description]
     */
    public function _empty()
    {
        return json(api_template_data(400, "当前操作不存在：" . $this->request->action() ));
    }

}
