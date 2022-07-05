<?php

namespace App\Models;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'employee_id',
        'check_in',
        'check_out',
        'checked_in_by_id',
        'checked_out_by_id',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'check_in',
        'check_out',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
