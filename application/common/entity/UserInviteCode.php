<?php
namespace app\common\entity;

use think\Model;

class UserInviteCode extends Model
{
    protected $table = 'user_invite_code';

    protected $autoWriteTimestamp = false;

    //生成邀请码
    public function makeCode($userId)
    {
        // 密码字符集，可任意添加你需要的字符
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        // $chars = '0123456789';
        $password = '';
        for ($i = 0; $i < 5; $i++) {
            $password .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $password . $userId;
    }

    public function saveCode($userId)
    {
        $model = new self();
        $model->user_id = $userId;
        $model->invite_code = rand(111111,999999);

        return $model->save();
    }

    public static function getUserIdByCode($code)
    {
        $data = self::where('invite_code', $code)->find();
        if (!$data) {
            return false;
        }
        return $data->user_id;
    }

    public static function getCodeByUserId($userId)
    {
        $data = self::where('user_id', $userId)->find();
        if (!$data) {
            return false;
        }
        return $data->invite_code;
    }
}