<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Profile/Edit', [
            'user' => Auth::user()->only(['id', 'name', 'email', 'theme']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'theme' => 'in:light,dark,system',
        ]);
        $user->update($data);
        return back()->with('success', 'Perfil atualizado.');
    }
}
