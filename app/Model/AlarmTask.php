<?php

declare(strict_types=1);

namespace App\Model;

class AlarmTask extends Model
{
    public $timestamps = false;

    protected $table = 'alarm_task';

    protected $fillable = [
        'name',
        'pinyin',
        'token',
        'secret',
        'department_id',
        'flag_save_db',
        'enable_workflow',
        'enable_filter',
        'enable_compress',
        'enable_upgrade',
        'enable_recovery',
        'status',
        'created_by',
        'created_at',
        'updated_at',
        'props',
    ];

    protected $casts = [
        'props' => 'array',
    ];

    public function config()
    {
        return $this->hasOne(AlarmTaskConfig::class, 'task_id', 'id');
    }

    public static function getSyncTasks()
    {
        return AlarmTask::with('config')
            ->select(
                'id',
                'name',
                'flag_save_db',
                'enable_workflow',
                'enable_filter',
                'enable_compress',
                'enable_upgrade',
                'enable_recovery',
                'status'
            )->get()
            ->keyBy('id')
            ->toArray();
    }
}
