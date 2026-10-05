<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('role')->orderBy('name')->get();

        return view('back.users.index', compact('users'));
    }
}
