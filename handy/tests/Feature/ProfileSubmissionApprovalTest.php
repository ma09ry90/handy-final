<?php

use App\Models\User;
use App\Models\ArtisanProfile;
use App\Models\DeliveryProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses()->beforeEach(function () {
    Storage::fake('local');
    // Ensure roles exist
    \DB::table('roles')->insertOrIgnore([
        ['id' => config('roles.buyer'), 'name' => 'buyer'],
        ['id' => config('roles.artisan'), 'name' => 'artisan'],
        ['id' => config('roles.delivery'), 'name' => 'delivery'],
        ['id' => config('roles.admin'), 'name' => 'admin'],
    ]);
});

it('allows artisan to submit profile and admin to approve', function () {
    $password = 'password';
    $user = User::create([
        'name' => 'Artisan User',
        'email' => 'artisan+'.uniqid().'@example.test',
        'password' => Hash::make($password),
    ]);
    $user->role_id = config('roles.artisan');
    $user->save();

    $this->actingAs($user)
        ->post('/artisan-profiles', [
            'shop_name' => 'Test Shop',
            'identity_document' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
        ])
        ->assertStatus(201)
        ->assertJsonFragment(['message' => 'Profile submitted for review']);

    $profile = ArtisanProfile::where('user_id', $user->id)->first();
    expect($profile)->not->toBeNull();
    expect($profile->approval_status)->toBe('pending');

    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin+'.uniqid().'@example.test',
        'password' => Hash::make($password),
    ]);
    $admin->role_id = config('roles.admin');
    $admin->save();

    $this->actingAs($admin)
        ->post('/admin/profiles/artisan/'.$profile->id.'/approve')
        ->assertStatus(200)
        ->assertJsonFragment(['message' => 'Profile approved']);

    $profile->refresh();
    expect($profile->approval_status)->toBe('approved');
    expect($profile->approved_by)->toBe($admin->id);
});

it('allows delivery to submit profile and admin to reject with reason', function () {
    $password = 'password';
    $user = User::create([
        'name' => 'Delivery User',
        'email' => 'delivery+'.uniqid().'@example.test',
        'password' => Hash::make($password),
    ]);
    $user->role_id = config('roles.delivery');
    $user->save();

    $this->actingAs($user)
        ->post('/delivery-profiles', [
            'date_of_birth' => now()->subYears(25)->toDateString(),
            'national_id_number' => 'NID12345',
            'emergency_contact_name' => 'Jane',
            'emergency_contact_phone' => '0912345678',
            'national_id_document' => UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf'),
        ])
        ->assertStatus(201)
        ->assertJsonFragment(['message' => 'Delivery profile submitted for review']);

    $profile = DeliveryProfile::where('user_id', $user->id)->first();
    expect($profile)->not->toBeNull();
    expect($profile->approval_status)->toBe('pending');

    $admin = User::where('role_id', config('roles.admin'))->first();
    if (! $admin) {
        $admin = User::create([
            'name' => 'Admin2',
            'email' => 'admin2@example.test',
            'password' => Hash::make($password),
            'role_id' => config('roles.admin'),
        ]);
    }

    $this->actingAs($admin)
        ->post('/admin/profiles/delivery/'.$profile->id.'/reject', ['rejection_reason' => 'Documents invalid'])
        ->assertStatus(200)
        ->assertJsonFragment(['message' => 'Profile rejected']);

    $profile->refresh();
    expect($profile->approval_status)->toBe('rejected');
    expect($profile->rejection_reason)->toBe('Documents invalid');
});
