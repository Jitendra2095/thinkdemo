<?php
namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\ManageUser;
use think\Controller;
use think\Request;

class Admin extends Controller
{

    public function _initialize() {
        parent::_initialize();
        $this->checked();  // 先父类，再检查登录/权限
    }
    public function checked()
    {
        $service = new \app\admin\service\rbac\Users\Service();

        if (!$service->getManageId()) {

            $this->redirect('login/index');
        }
        //判断权限
        $service = new \app\admin\service\rbac\Users\Service();

        if (!$service->checkAuth()) {
            $request = Request::instance();
            if ($request->isAjax()) {
                echo json_encode(['code'=>1,'message'=>'没有权限操作，请联系管理员3']);
                die;
            } else {

                $this->error('没有权限操作，请联系管理员4');
            }
        }
    }
    protected function initialize()
    {   

        if (php_sapi_name() != 'cli') {
            //判断用户是否登录
            $service = new \app\admin\service\rbac\Users\Service();
            if (!$service->getManageId()) {
                $this->redirect('login/index');
            }
            //判断权限
            $service = new \app\admin\service\rbac\Users\Service();
            if (!$service->checkAuth()) {
                if (Request::isAjax()) {
                    throw new AdminException('没有权限操作，请联系管理员1');
                } else {
                    $this->error('没有权限操作，请联系管理员2');
                }
            }
        }

    }

    public function render($template, $data = [])
    {
        $service = new \app\admin\service\rbac\Users\Service();
        $data['manage'] = $service->getManageInfo();
        $data['menus'] = $this->baseParams();

        return $this->fetch($template, $data);

    }

    private function baseParams()
    {

        $service = new \app\admin\service\rbac\Power\Service();
        $menus = $service->getMenus();

        return $menus;
    }
    protected function getNext($left_uid)
    {
        $next_id = [];
        $next_id[] = $left_uid;
        if($left_uid){
            $entry = new \app\common\entity\User();
            $arr = $entry->getChildsInfoNoLevel($left_uid);
            foreach ($arr as $v)
            {
                $next_id[] = $v['id'];
            }
        }
        return $next_id;
    }
}