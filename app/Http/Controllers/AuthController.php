<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Show the registration form.
     */
    public function showRegistrationForm()
    {
        return view('pages.auth.signup', ['title' => 'Sign Up']);
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->fname.' '.$request->lname,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => 'active',
        ]);

        $user->assignRole(config('alh.default_role'));

        return redirect()->route('signin')->with('success', 'Registration successful! Please sign in.');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
