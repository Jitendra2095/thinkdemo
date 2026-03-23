<?php
namespace app\common\entity;

use think\Model;

class ManageGroup extends Model
{
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $table = 'manage_group';

    protected $autoWriteTimestamp = true;

    public function getId()
    {
        return $this->id;
    }

    public function getGroupName()
    {
        return $this->group_name;
    }

    public function getAuthIds()
    {
        return $this->auth_ids;
    }

    public function getCreateTime()
    {
        return $this->create_time;
    }

    public function isDefault()
    {
        return $this->is_default;
    }
    public function getAllUser($id)
    {
        return ManageUserGroup::alias('mug')
            ->field('mu.manage_name')
            ->leftJoin('manage_user mu','mug.user_id = mu.id')
            ->leftJoin('manage_group mg','mug.group_id = mg.id')
            ->where('mg.id',$id)
            ->select();
    }
}