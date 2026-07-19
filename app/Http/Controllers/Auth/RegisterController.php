<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    /**
     * Show the registration wizard.
     */
    public function showRegistrationForm(): Response
    {
        return Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Handle the multi-step registration submission.
     */
    public function register(Request $request): RedirectResponse
    {
        $request->validate([
            // Step 1: Account Info
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'mobile_number' => ['required', 'string', 'max:20', 'unique:users,mobile_number'],

            // Step 2: Identity & Personal Info
            'full_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'string', 'in:Male,Female,Other'],
            'ethnicity_or_religion' => ['required', 'string', 'max:100'],
            'nic_number' => ['required', 'string', 'max:50', 'unique:user_profiles,nic_number'],
            'driving_license_number' => ['nullable', 'string', 'max:50'],
            'passport_number' => ['nullable', 'string', 'max:50'],

            // Step 3: Addresses & Documents
            'current_address' => ['required', 'string', 'max:1000'],
            'permanent_address' => ['required', 'string', 'max:1000'],
            'birth_certificate' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png', 'max:10240'], // 10MB limit
            'nic_front' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png', 'max:10240'],
            'nic_front' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png', 'max:10240'],
            'nic_back' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png', 'max:10240'],
        ]);

        // Wrap registration in a database transaction to guarantee atomicity
        DB::transaction(function () use ($request) {
            // Create user
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'mobile_number' => $request->mobile_number,
            ]);

            // Save files securely in private local disk
            $birthCertPath = $request->file('birth_certificate')->store('private/documents/' . $user->id);
            $nicFrontPath = $request->file('nic_front')->store('private/documents/' . $user->id);
            $nicBackPath = $request->file('nic_back')->store('private/documents/' . $user->id);

            // Create user profile
            $user->profile()->create([
                'full_name' => $request->full_name,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'ethnicity_or_religion' => $request->ethnicity_or_religion,
                'nic_number' => $request->nic_number,
                'driving_license_number' => $request->driving_license_number,
                'passport_number' => $request->passport_number,
                'current_address' => $request->current_address,
                'permanent_address' => $request->permanent_address,
                'birth_certificate_path' => $birthCertPath,
                'nic_front_path' => $nicFrontPath,
                'nic_back_path' => $nicBackPath,
            ]);

            // Automatically bootstrap the default Cash Wallet
            $user->accounts()->create([
                'name' => 'Cash Wallet',
                'type' => 'cash_wallet',
                'balance' => 0.00,
                'currency' => 'LKR',
            ]);

            // Authenticate the registered user
            Auth::login($user);
        });

        return redirect()->route('dashboard');
    }
}
