<?php

namespace App\Models;

use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'employee_id',
        'name',
        'designation',
        'image',
        'is_active',
        'is_suspended',
        'gender',
        'faceId'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_active'    => 'boolean',
        'is_suspended' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['status'];

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function getStatusAttribute()
    {
        $attendance = $this->attendances()->whereDate('check_in', Carbon::today())->first();
        if (!$attendance)
            return "pending";

        if ($attendance->check_out)
            return "completed";

        return "on-duty";
    }
}
