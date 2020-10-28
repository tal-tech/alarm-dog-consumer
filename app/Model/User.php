<?php

declare(strict_types=1);

namespace App\Model;

class User extends Model
{
    public $timestamps = false;

    protected $table = 'user';

    protected $fillable = [
        'uid',
        'username',
        'pinyin',
        'user',
        'email',
        'department',
        'phone',
        'wechatid',
        'role',
        'created_at',
        'updated_at',
    ];

    protected $hidden = ['id'];

    /**
     * 查询用于同步的用户信息.
     *
     * @return array
     */
    public static function getSyncUsers()
    {
        return User::select('uid', 'username', 'email', 'phone')->get()->keyBy('uid')->toArray();
    }
}
