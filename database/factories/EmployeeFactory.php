<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Employee;
use App\Models\Attendance;
use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(Employee::class, function (Faker $faker) {
    return [
        'employee_id'  => 'EMP',
        'name'         => $faker->name,
        'designation'  => 'employee',
        'image'        => '/url',
        'is_active'    => true,
        'is_suspended' => false,
    ];
});

$factory->afterCreating(Employee::class, function ($employee, $faker) {
    $employee->employee_id = 'EMP' . $employee->id;
    $employee->save();
    // $employee->attendances()->createMany(factory(Attendance::class, 10)->make()->toArray());
});
