<?php

namespace app\common\service\Users;

use app\common\entity\User;
use think\Cache;
use think\Session;

class Identity {

    const SESSION_NAME = 'flow_box_member';
    const CACHE_NAME = 'flow_box_member_%s';
    const CACHE_TTS = 3600;

    public function getUserInfo($userId = 0) {
        $userId = $userId ? $userId : $this->getUserId();
        $userInfo = Cache::remember($this->getCacheName($userId), function () use ($userId) {
                    $user = User::where('id', $userId)->find();
                    return json_encode([
                        'user_id' => $userId,
                        'status' => $user->status,
                        'nick_name' => $user->nick_name,
                        'avatar' => $user->avatar,
                        'level' => $user->level,

                    ]);
                }, self::CACHE_TTS);

        return json_decode($userInfo);
    }

    public function delCache() {
        Session::delete(self::SESSION_NAME);
    }

    public function saveSession(User $user) {
        Session::set(self::SESSION_NAME, [
            'id' => $user->getId(),
            'mobile' => $user->mobile,
        ]);
    }

    public function getUserId() {
        $info = Session::get(self::SESSION_NAME);
        return $info['id'] ? $info['id'] : 0;
    }

    public function getUserMobile() {
        $info = Session::get(self::SESSION_NAME);
        return $info['mobile'] ? $info['mobile'] : '';
    }

    public function getCacheName($userId) {
        return sprintf(self::CACHE_NAME, $userId);
    }

    /**
     * 退出登录
     */
    public function logout() {
        $this->delCache($this->getUserId());
        Session::delete(self::SESSION_NAME);
    }

}
