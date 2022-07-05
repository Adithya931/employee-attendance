<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\User;
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

$users = User::pluck('id');

$factory->define(Attendance::class, function (Faker $faker) use($users){
    $date = $faker->unique()->date();
    $end_time = $faker->time();
    $start_time = $faker->time('H:i:s', $end_time);
    return [
        'check_in'          => $date . " " . $start_time,
        'check_out'         => $date . " " . $end_time,
        'checked_in_by_id'  => $faker->randomElement($users),
        'checked_out_by_id' => $faker->randomElement($users),
    ];
});
