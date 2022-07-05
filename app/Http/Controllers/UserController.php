<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function user()
    {
        $user = User::find(Auth::id());
        $roles = $user->getRoleNames();
        $user = $user->makeHidden(['roles'])->toArray();
        $user['roles'] = $roles;
        return collect($user);
    }
}
