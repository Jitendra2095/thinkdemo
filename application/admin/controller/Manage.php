<?php
namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\ManageGroup;
use app\common\entity\ManageUser;
use app\common\entity\ManageUserGroup;
use think\Db;
use think\Request;


class Manage extends Admin
{
    /**
     * @power 权限管理|用户管理
     * @rank 1
     */
    public function index(Request $request)
    {
        $entity = ManageUser::field('*');
        if ($keyword = $request->get('keyword')) {
            $entity->where('manage_name', $keyword);
        }

        return $this->render('index', [
            'list' => $entity->paginate(15)
        ]);
    }

    /**
     * @power 权限管理|用户管理@添加用户
     */
    public function create()
    {

        return $this->render('edit', [
            'groups' => ManageGroup::all()
        ]);
    }

    /**
     * @power 权限管理|用户管理@编辑用户
     */
    public function edit($id)
    {
        $entity = ManageUser::where('id', $id)->find();
        if (!$entity) {
            return json(['code' => 1 ,'message' => '用户对象不存在']);
        }
        if ($entity->isDefault()) {
            return json(['code' => 1 ,'message' => '默认用户不能编辑']);
        }

        return $this->render('edit', [
            'info' => $entity,
            'groupIds' => ManageUserGroup::getGroupsByUserId($id),
            'groups' => ManageGroup::all()
        ]);
    }

    /**
     * @power 权限管理|用户管理@添加用户
     */
    public function save(Request $request)
    {
        $result = $this->validate($request->post(), 'app\admin\validate\ManageForm');

        if (true !== $result) {
            return json()->data(['code' => 1, 'message' => $result]);
        }

        $service = new \app\admin\service\rbac\Users\Service();
        if ($service->checkName($request->post('name'))) {
            return json(['code' => 1 ,'message' => '用户名已被注册,请重新填写']);
        }

        $groupIds = $request->post()['groupIds'];

        Db::startTrans();
        try {
            $entity = new ManageUser();
            $entity->manage_name = $request->post('name');
            $entity->password_salt = $service->getPasswordSalt();
            $entity->password = $service->getPassword($request->post('password'), $entity->getPasswordSalt());

            if (!$entity->save()) {
                return json(['code' => 1 ,'message' => '保存失败']);
            }

            foreach ($groupIds as $groupId) {
                $result = ManageUserGroup::addInfo($entity->getId(), $groupId);
                if (!$result) {
                    return json(['code' => 1 ,'message' => '保存失败']);
                }
            }
            Db::commit();

            return json(['code' => 0, 'toUrl' => url('/admin/manage')]);
        } catch (\Exception $e) {
            Db::rollback();
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * @power 权限管理|用户管理@编辑用户
     * @method GET
     */
    public function update(Request $request, $id)
    {
        $entity = $this->checkInfo($id);

        $result = $this->validate($request->post(), 'app\admin\validate\ManageEditForm');

        if (true !== $result) {
            return json()->data(['code' => 1, 'message' => $result]);
        }

        $service = new \app\admin\service\rbac\Users\Service();
        if ($service->checkName($request->input('name'), $id)) {
            return json(['code' => 1 ,'message' => '用户名已被注册,请重新填写']);
        }

        $groupIds = $request->post()['groupIds'];

        Db::startTrans();
        try {
            $entity->manage_name = $request->post('name');
            $left_uid = $request->post('left_uid');
            if(isset($left_uid) && $left_uid){
                $entity->left_uid = $request->post('left_uid');
            }
            if ($password = $request->post('password')) {
                $entity->password = $service->getPassword($password, $entity->getPasswordSalt());
            }

            if ($entity->save() === false) {
                return json(['code' => 1 ,'message' => '保存失败']);
            }
            ManageUserGroup::where('user_id', $entity->getId())->delete();
            foreach ($groupIds as $groupId) {
                $result = ManageUserGroup::addInfo($entity->getId(), $groupId);
                if (!$result) {
                    return json(['code' => 1 ,'message' => '保存失败']);
                }
            }

            Db::commit();

            return json(['toUrl' => url('/admin/manage')]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 1 ,'message' => $e->getMessage()]);
        }

    }

    /**
     * @power 权限管理|用户管理@禁用用户
     */
    public function delete($id)
    {
        $entity = $this->checkInfo($id);

        $entity->forbidden_time = time();

        if (!$entity->save()) {

            return json(['code' => 1 ,'message' => '禁用失败']);
        }

        return json(['code' => 0, 'message' => 'success']);
    }
    /**
     * @power 权限管理|用户管理@删除用户
     */
    public function delUser($id)
    {
        $entity = $this->checkInfo($id);
        $res = $entity->delete();
        if (!$res) {
            return json(['code' => 1 ,'message' => '删除失败']);
        }

        return json(['code' => 0, 'message' => 'success']);
    }
    /**
     * @power 权限管理|用户管理@解禁用户
     * @method POST
     */
    public function unforbidden($id)
    {
        $entity = $this->checkInfo($id);

        $entity->forbidden_time = 0;

        if (!$entity->save()) {
            return json(['code' => 1 ,'message' => '解禁失败']);
        }

        return json(['code' => 0, 'message' => 'success']);
    }

    private function checkInfo($id)
    {
        $entity = ManageUser::where('id', $id)->find();
        if (!$entity) {
            return json(['code' => 1 ,'message' => '对象不存在']);
        }
        if ($entity->isDefault()) {
            return json(['code' => 1 ,'message' => '默认用户不能编辑']);
        }

        return $entity;
    }
}