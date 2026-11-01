<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['username', 'mobile', 'email', 'password']);

        $user = new User($data);
        $user->name = $data['username'];
        // Self-registration can only ever create a student account.
        $user->account_type = User::TYPE_STUDENT;
        $user->status = User::STATUS_ACTIVE;
        $user->save();

        event(new Registered($user));

        return redirect()->route('login')->with('status', 'Account created. You can now log in.');
    }
}
