<?php
namespace app\index\validate;

use app\common\entity\UserInviteCode;
use app\index\model\SendCode;
use app\index\model\SendMail;
use think\Validate;

class RegisterForm extends Validate
{
    protected $rule = [
//        'invite_code' => 'checkInvite|require',
        // 'nick_name' => 'require',
        'mobile' => 'require|regex:^1\d{10}$|checkMobile',
//         'code' => 'require',
        'password' => 'require|min:6',
//        're_password' => 'require|confirm:password',
//        'trad_password' => 'require|min:6',
//        're_trad_password' => 'require|confirm:trad_password',
//        '__token__' => 'token',
//        'check' => 'require',
//        'center_id' => 'require',
        // 'safe_password' => 'require|confirm:password'
    ];

    protected $message = [
//        'invite_code.require' => '邀请码不能为空',
        // 'nick_name.require' => '昵称不能为空',
        'mobile.require' => '账号不能为空',
        'mobile.regex' => '账号格式不正确',
//         'code.require' => '验证码不能为空',
        'password.require' => '登录密码不能为空',
//        'trad_password.require' => '交易密码不能为空',
        'password.min' => '登录密码至少为6位',
//        'trad_password.min' => '交易密码至少为6位',
//        'check' => '用户协议必须勾选',
//        'center_id' => '运营中心未选择',
        // 'safe_password.confirm' => '两次密码不一样'
        // 'safe_password.require' => '交易密码不能为空',
        // 'safe_password.min' => '交易密码至少为6位'
    ];

    public function checkInvite($value)
    {
        //判断邀请码是否存在
        if (!UserInviteCode::getUserIdByCode($value)&&$value) {
            return '邀请码不存在';
        }
        return true;
    }

    public function checkMobile($value, $rule, $data = [])
    {
        if (\app\common\entity\User::checkMobile($value)) {
            return '此账号已被注册，请重新填写';
        }
        return true;
    }

    public function checkCode($value, $mobile)
    {
        $sendCode = new SendCode($mobile, 'register');
        if (!$sendCode->checkCode($value)) {
            return false;
        }
        return true;
    }
    public function checkChange($value, $mobile)
    {
        $sendCode = new SendCode($mobile, 'change-password');
        if (!$sendCode->checkCode($value)) {
            return false;
        }
        return true;
    }


}