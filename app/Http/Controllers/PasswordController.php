<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('auth.password');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed',
                           'different:password_lama'],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Kata sandi berhasil diubah.');
    }
}