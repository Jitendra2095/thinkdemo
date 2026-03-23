<?php

namespace app\common\entity;


use think\Db;
use think\Model;
use traits\model\SoftDelete;


class User extends Model
{
//    use SoftDelete;   //开启了软删除
    const STATUS_DEFAULT = 1;
    const STATUS_FORBIDDED = -1;
    const AUTH_SUCCESS = 1;
    const AUTH_ERROR = -1;

    protected $createTime = 'register_time';
    protected $login_time = 'login_time';
    protected $categoryModel;
    /**
     * @var string 对应的数据表名
     */
    protected $table = 'user';
    protected $autoWriteTimestamp = true;

    //获取状态
    public function getStatus($status)
    {
        switch ($status) {
            case -1:
                return '禁用';
            case 1:
                return '正常';
            default:
                return '';
        }
    }


    public function getId()
    {
        return $this->id;
    }

    #获取用户名
    public function getUserName()
    {
        return $this->nick_name;
    }

    #获取用户名
    public function getNickName($id)
    {
        return $this->where('id', $id)->value('nick_name');
    }

    public function getCategoryModel()
    {
        if (!$this->categoryModel) {
            $this->categoryModel = new Category();
        }
        return $this->categoryModel;
    }
    /**
     * 查出上级
     * @param $mid
     * @param $result
     * @param int $i
     * @return mixed
     *
     */
    public function get_superiors($mid, &$result, $i = 4)
    {

        if ($i <= 0) {
            return $result;
        }
        $field = ['u.id,u.level,u.pid,u.star_level,l.one_level,l.two_level,l.three_level'];

        $superiors = $this
            ->alias('u')
            ->leftJoin('config_user_level l','u.level = l.id')
            ->where('u.id',$mid)
            ->field($field)
            ->find();

      $is_vip = $this
          ->field('id,star_level,level')
          ->where('id',$mid)
          ->find();
        if($is_vip) {
            if ($is_vip['star_level'] > 0 && $is_vip['level'] == 0) {
                $newConfig = ConfigUserLevelModel::where('id', 1)->find();
                $superiors['one_level'] = $newConfig['one_level'];
                $superiors['two_level'] = $newConfig['two_level'];
                $superiors['three_level'] = $newConfig['three_level'];
            }
        }
        if ($superiors) {
            $i--;
            if ($i != 3) {
                $result[] = $superiors;
            }
            $this->get_superiors($superiors['pid'], $result, $i);
        }
    }

    /**
     * 获取密码
     */
    public function getPassword()
    {
        return $this->password;
    }

    public function getSafePassword()
    {
        return $this->trad_password;
    }

    /**
     * 获取禁用时间
     */
    public function getForbiddenTime()
    {
        return $this->forbidden_time ? date('Y-m-d H:i:s', $this->forbidden_time) : 0;
    }

    /**
     * 获取VIP过期时间
     */
    public function getVipEndTime()
    {
        return $this->vip_endtime ? date('Y-m-d H:i:s', $this->vip_endtime) : 0;
    }

    /**
     * 判断是否被禁用
     */
    public function isForbiddened()
    {
        return $this->forbidden_time ? true : false;
    }

    /**
     * 获取注册时间
     */
    public function getRegisterTime()
    {
        return $this->register_time;
    }

    /**
     * 获取用户资料
     */
    public function getUserInfo($uid)
    {
        return $this->where('id', $uid)->find();
    }

    /**
     * 获取最后登录时间
     */
    public function getLoginTime()
    {
        return $this->login_time;
    }

    public function getLevel($level)
    {

        switch ($level) {
            case 0:
                return '普通会员';
            case 1:
                return 'VIP1';
            case 2:
                return 'VIP2';
            case 3:
                return 'VIP3';
            case 4:
                return 'VIP4';
            case 5:
                return 'VIP5';
            case 6:
                return 'VIP6';
        }
    }

    public static function checkMobile($mobile)
    {
        return self::where('mobile', $mobile)->find();
    }

    public static function checkName($name)
    {
        return self::where('nick_name', $name)->find();
    }

    //获取直推人数
    public function getChildTotal()
    {
        return self::where('pid', $this->getId())->count();
    }

    //获取团队关系
    public function getTeamShip($pid, $uid, &$res = '')
    {
        $childs = User::where('pid', $pid)->select();
        foreach ($childs as $v) {
            if ($v['id'] == $uid) {
                $res = true;
            }
            $this->getTeamShip($v['id'], $uid, $res);
        }
        return $res;
    }

    //获取团队的人数
    public function getChilds($memberId)
    {
        $childs = self::where('pid', $memberId)
            ->field('*')
            ->select();
        return $childs;
    }

    /**
     * 获取用户上级信息
     */
    public function getParentInfo()
    {
        if ($this->pid == 0) {
            return '';
        }
        $data = self::where('id', $this->pid)->find();

        return $data ? $data : '';
    }


    #获取下级
    public function getChildsInfo($uid, $num = 0)
    {
        static $childs = [];
        static $level = 0;
        $my = User::where('id', $uid)->field('id,nick_name as name,level,pid as pId')->find();
        if (isset($num)) {
            if ($level == $num) {
                return $my;
            }
        }
        $child = User::where('pid', $uid)->field('id,nick_name as name,level,pid as pId')->select();
        if ($child) {
            if ($my['pId'] == 0) {
                $childs[] = $my;
            }
            foreach ($child as $v) {
                $childs[] = $v;
                $this->getChildsInfo($v['id'], $num);
            }
        }
        return $childs;
    }

    #获取下级
    public function getChildsInfo1($uid, $num = 0, &$childs = [], &$level = 0)
    {
        $ids = [];
        if ($num) {
            if ($level == $num) {
                return $childs;
            }
        }
        $child = Db::table('user')
            ->alias('u')
            ->leftJoin('my_wallet w','u.id = w.uid')
            ->leftJoin('config_user_level l','l.id = u.level')
            ->whereIn('pid', $uid)
            ->field('u.id,u.pid,u.mobile,u.level,u.register_time,w.number,l.level_name')
            ->select();
        if ($child) {
            $level++;
            $childs[$level] = $child;
            foreach ($child as $v) {
                array_push($ids, $v['id']);
            }
            $ids = array_unique($ids);

            return $this->getChildsInfo1($ids, $num, $childs, $level);
        }

        return $childs;

    }

    #获取下级
    public function getChildsInfoNoLevel($uid, $num = 0, &$childs = [], &$level = 0)
    {
        $ids = [];
        if ($num) {
            if ($level == $num) {
                return $childs;
            }
        }
        $child = Db::table('user')->whereIn('pid', $uid)->field('id,pid')->select();
        if ($child) {
            $level++;
//            $childs[$level] = $child;
            foreach ($child as $v) {
                array_push($childs, $v);

                array_push($ids, $v['id']);
            }
            $ids = array_unique($ids);

            return $this->getChildsInfoNoLevel($ids, $num, $childs, $level);
        }

        return $childs;

    }

    #获取下级数量
    public function getChildsInfoNum($uid, $num = 0, &$childs = [], &$level = 0, &$total = 0)
    {
        $ids = [];
        if ($num) {
            if ($level == $num) {
                return $total;
            }
        }
        $child = Db::table('user')->whereIn('pid', $uid)->field('id,pid')->select();
        if ($child) {
            $level++;
            $childs[$level] = $child;
            foreach ($child as $v) {
                array_push($ids, $v['id']);
            }
            $ids = array_unique($ids);
            $total += count($ids);

            return $this->getChildsInfoNum($ids, $num, $childs, $level, $total);
        }

        return $total;

    }
    #获取下级有效数量
    public function getChildsRealNum($uid, $num = 0, &$childs = [], &$level = 0, &$total = 0)
    {
        $ids = [];
        $real = [];
        if ($num) {
            if ($level == $num) {
                return $total;
            }
        }
        $child = Db::table('user')->whereIn('pid', $uid)->field('id,pid,star_level')->select();
        if ($child) {
            $level++;
            $childs[$level] = $child;
            foreach ($child as $v) {
                array_push($ids, $v['id']);
                if($v['star_level'] > 0){
                    array_push($real, $v['id']);
                }
            }
            $ids = array_unique($ids);
            $total += count($real);

            return $this->getChildsRealNum($ids, $num, $childs, $level, $total);
        }

        return $total;

    }
    #获取下级
    public function getAllChildsInfo($uid)
    {
        static $childs = [];
        static $level = 0;
        $my = User::alias('u')
            ->leftJoin('user_level_config ulc','ulc.id = u.level')
            ->where('u.id', $uid)
            ->field('u.id,avatar,nick_name,level,pid,mobile,register_time,level_name')
            ->find();
        if (isset($num)) {
            if ($level == $num) {
                return $my;
            }
        }
        $child = User::alias('u')
            ->leftJoin('user_level_config ulc','ulc.id = u.level')
            ->where('u.pid', $uid)
            ->field('u.id,avatar,nick_name,level,pid,mobile,register_time,level_name')
            ->select();
        if ($child) {
            if ($my['pid'] == 0) {
                $childs[] = $my;
            }
            foreach ($child as $v) {
                $childs[] = $v;
                $this->getAllChildsInfo($v['id']);
            }
        }
        return $childs;
    }

    public function getParents($uid, $num, &$parent = array(), &$level = 0)
    {
        // static $parent = [];
        // static $level = 0;
        $userInfo = User::where('id', $uid)->find();

        $pid = $userInfo['pid'];
        $level++;
        if ($level > $num) {
            return $parent;
        }
        if ($pid == '0') {
            return $parent;
        }
        $parent["$level"] = $pid;
        $this->getParents($pid, $num, $parent, $level);
        return $parent;

    }

    public function getParentsNb($uid, &$parent = array(), &$level = 0)
    {
        // static $parent = [];
        // static $level = 0;
        $userInfo = User::where('id', $uid)->field('id,pid')->find();

        $pid = $userInfo['pid'];

        if ($pid == '0') {
            $userInfo['levels'] = $level;
            $parent[] = $userInfo;
            return $parent;
        }
        if ($level == '0') {
            $level++;
        } else {

            $userInfo['levels'] = $level;
            $parent[] = $userInfo;
            $level++;
        }
        $this->getParentsNb($pid, $parent, $level);
        return $parent;
    }

    #获取上级ID
    public function getParentsId($uid, $num, $area, &$parent = array(), &$level = 0, &$time = 0)
    {
        $res = Match::where('uid', $uid)->where('area', $area)->where('status', 1)->find();
        $userInfo = User::where('id', $uid)->field('id,mobile,level,pid,status')->find();
        if ($res) {
            $level++;
        }
        $time++;
        if ($res) {
            $parent[$level] = $userInfo;
        }
        $pid = $userInfo['pid'];
        if ($level >= $num || $time > 1000) {

            return $parent;
        }
        return $this->getParentsId($pid, $num, $area, $parent, $level, $time);
    }

    /**
     * 获取下级有效人数
     * $child   下级数组
     * $num   查到第几级
     */
    public function getChildsNum($child, $num, &$level = 0, &$count = 1)
    {
        $childs = [];
        foreach ($child as $key => $val) {

            if ($val['status'] == 1) {

                $childs[] = $val;
            }

        }
        $count1 = count($childs);
        $count = $count + $count1;
        $level++;
        if ($level >= $num) {
            return $count;
        }
        foreach ($child as $v) {

            $cc = User::field('id,status')->where('pid', $v['id'])->select();

            $this->getChildsNum($cc, $num, $level, $count);
        }
        return $count;

    }

    #获取上几级
    public function getNumParents($uid, $num, &$parent = array(), &$level = 0)
    {

        $userInfo = User::where('id', $uid)->find();

        $pid = $userInfo['pid'];
        $level++;
        if ($level > $num) {
            return $parent;
        }
        if ($pid == '0') {
            return $parent;
        }
        $parent["$level"] = $pid;
        $this->getNumParents($pid, $num, $parent, $level);
        return $parent;

    }

    /**
     * 获取无限层团队人数
     * $child   下级数组
     * $num   查到第几级
     */
    public function getTeamNum($child, &$count = 1)
    {
        $count1 = count($child);
        $count = $count + $count1;
        foreach ($child as $v) {
            $cc = User::field('id,status')
                ->where('pid', $v['id'])
                ->select();
            $this->getTeamNum($cc, $count);
        }
        return $count;
    }




    public function isHasNickName($uid, $nick_name)
    {

        return User::where('id', '<>', $uid)->where('nick_name', $nick_name)->find();

    }

    public function getChildsInfoNumAct1($uid, $num = 0, &$childs = [], &$level = 0, &$total = 0)
    {
        $ids = [];
        if ($num != 0) {
            if ($level == $num) {
                return $total;
            }
        }
        $child = Db('user')
            ->whereIn('pid', $uid)
            ->where('level', '>', 0)
            ->field('id,pid')
            ->select();
        if ($child) {
            $level++;
            $childs[$level] = $child;
            foreach ($child as $v) {
                array_push($ids, $v['id']);
            }
            $ids = array_unique($ids);
            $total += count($ids);

            return $this->getChildsInfoNumAct1($ids, $num, $childs, $level, $total);
        }

        return $total;

    }
    public function getChildsInfoNumAct($uid, $num = 0, &$childs = [], &$level = 0, &$total = 0)
    {
        $ids = [];
        if ($num != 0) {
            if ($level == $num) {
                return $total;
            }
        }
        $child = Db('user')
            ->whereIn('pid', $uid)
            ->field('id,pid')
            ->select();
        if ($child) {
            $level++;
            $childs[$level] = $child;
            foreach ($child as $v) {
                array_push($ids, $v['id']);
            }
            $ids = array_unique($ids);
            $total += count($ids);

            return $this->getChildsInfoNumAct($ids, $num, $childs, $level, $total);
        }

        return $total;

    }
    //生成代理码
    public function makeShareCode($userId)
    {
        // 密码字符集，可任意添加你需要的字符
        $date = date('YmdHis');
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' . $userId . $date;
        // $chars = '0123456789';
        $password = '';
        for ($i = 0; $i < 8; $i++) {
            $password .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        $is_has = UserInviteCode::where('invite_code', $password)->find();
        if ($is_has) {
            $this->makeShareCode($userId);
        }
        return $password;
    }


    /**
     * 寻找最上级
     */
    public static function findStart($uid,$level=0)
    {
        $pid = self::where('id',$uid)->value('pid');
        $data = [
            'pid' => $uid,
            'level' => $level,
        ];
        if($pid == 0){
            return $data;
        }
        $level++;
        return self::findStart($pid,$level);
    }
}
