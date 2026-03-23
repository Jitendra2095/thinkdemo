<?php
namespace app\common\service\Users;

use app\common\entity\User;
use app\common\entity\SafeAnswer;
use think\Request;
use think\Session;

class Service
{
    /**
     * 加密前缀
     */
    const PREFIX_KEY = "eco_member";

    /**
     * 加密函数
     */
    public function getPassword($password)
    {
        return md5(md5(self::PREFIX_KEY . $password));
    }

    /**
     * 验证密码
     */
    public function checkPassword($password, User $entity)
    {
        return $this->getPassword($password) === $entity->getPassword();
    }

    public function checkSafePassword($password, User $entity)
    {
        return $this->getPassword($password) === $entity->getSafePassword();
    }
    public function addUser($data)
    {

        $entity = new User();
        $request = Request::instance();
        $entity->mobile = $data['mobile'];
        $entity->pid = $data['pid'];
        $entity->password = $this->getPassword($data['password']);
//        $entity->trad_password = $this->getPassword($data['trad_password']);
        $entity->register_time = time();
        $entity->register_ip = $data['ip'] ?? $request->ip();
        $entity->status = User::STATUS_DEFAULT;
        $entity->nick_name = $data['nick_name']??$data['mobile'];
        if ($entity->save()) {
            return $entity->getId();
        }
        
        return false;
    }

    public function updateUser(User $user, $data)
    {
        if ($data['password']) {
            $user->password = $this->getPassword($data['password']);
        }
//        if ($data['trad_password']) {
//            $user->trad_password = $this->getPassword($data['trad_password']);
//        }
        $user->mobile = $data['mobile'] ;
        if($data['pid']) {
            $user->pid = $data['pid'];
        }
        $user->commission_level = $data['commission_level'];
        return $user->save();
    }
    /**
     * 修改密码
     */
    public function updatePwd(User $user, $data)
    {

        if ($data['password']) {
            $user->password = $this->getPassword($data['password']);
        }
        return $user->save();
    }

    /**
     * 检查用户名是否已存在
     */
    public function checkUser($name, $id = 0)
    {
        $entity = user::where('mobile', $name);
        if ($id) {
            $entity->where('id', '<>', $id);
        }
        return $entity->find() ? true : false;
    }
    /**
     * 检查上级是否已存在
     */
    public function checkHigher($name)
    {
        $entity = user::where('mobile', $name)->value('id');

        return $entity ? $entity : false;
    }
    /**
     * 检查交易地址是否已存在
     */
    public function checkAddress($name, $id = 0)
    {
        $entity = user::where('trade_address', $name);
        if ($id) {
            $entity->where('id', '<>', $id);
        }
        return $entity->find() ? true : false;
    }

    /**
     * 银行卡号 微信号 支付宝账号 唯一
     */
    public function checkMsg($type, $account, $id = '')
    {
        return \app\common\entity\User::where("$type", $account)->where('id', '<>', $id)->find();
    }

}