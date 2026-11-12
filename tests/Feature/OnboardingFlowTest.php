<?php

use App\Enums\RegistrationStatus;
use App\Jobs\SendOnboardingMagicLinkJob;
use App\Mail\ResidentOnboardingMail;
use App\Models\Rol;
use App\Models\Urbanismo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $now = now();

    DB::table('roles')->insertOrIgnore([
        ['id' => Rol::ADMIN, 'name' => 'Admin', 'type' => Rol::ADMIN, 'created_at' => $now, 'updated_at' => $now],
        ['id' => Rol::PROPIETARIO, 'name' => 'Propietario', 'type' => Rol::PROPIETARIO, 'created_at' => $now, 'updated_at' => $now],
    ]);
});

function makeUrbanismo(): Urbanismo
{
    return Urbanismo::create([
        'nombre' => 'Urbanismo Test',
        'ciudad' => 'Lima',
        'direccion' => 'Av. Test 123',
    ]);
}

function makeUser(Urbanismo $urbanismo, RegistrationStatus $status, int $rolId = Rol::PROPIETARIO): User
{
    $email = 'user-'.Str::lower(Str::random(8)).'@example.com';

    return User::create([
        'name' => 'Usuario Test',
        'email' => $email,
        'username' => Str::lower(Str::random(12)),
        'password' => 'password123',
        'rol_id' => $rolId,
        'urbanismo_id' => $urbanismo->id,
        'unidad' => 'Torre A - Apto 4',
        'estado_registro' => $status,
        'status' => 1,
    ]);
}

it('registra un residente en cuarentena con un código válido', function () {
    $urbanismo = makeUrbanismo();
    $email = 'nuevo-'.Str::lower(Str::random(8)).'@example.com';

    $response = $this->postJson('/api/register', [
        'nombre' => 'Nuevo Residente',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'unidad' => 'Torre A - Apto 4',
        'codigo_invitacion' => $urbanismo->codigo_invitacion,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.estado_registro', 'cuarentena');

    $this->assertDatabaseHas('users', [
        'email' => $email,
        'urbanismo_id' => $urbanismo->id,
        'estado_registro' => 'cuarentena',
    ]);
});

it('rechaza un código de invitación inexistente', function () {
    $response = $this->postJson('/api/register', [
        'nombre' => 'Nuevo Residente',
        'email' => 'invalido-'.Str::lower(Str::random(8)).'@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'unidad' => 'Torre A - Apto 4',
        'codigo_invitacion' => 'NOEXISTE',
    ]);

    $response->assertStatus(422);
});

it('bloquea el login de un usuario en cuarentena', function () {
    $urbanismo = makeUrbanismo();
    $user = makeUser($urbanismo, RegistrationStatus::Cuarentena);

    $response = $this->postJson('/api/login', [
        'username' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertStatus(403);
});

it('activa la cuenta y permite login con el magic link', function () {
    $urbanismo = makeUrbanismo();
    $user = makeUser($urbanismo, RegistrationStatus::Pendiente);

    $plainToken = Str::random(64);
    $user->activationTokens()->create([
        'token_hash' => hash('sha256', $plainToken),
        'expires_at' => now()->addDay(),
    ]);

    $response = $this->postJson('/api/onboarding/complete', [
        'token' => $plainToken,
        'email' => $user->email,
        'password' => 'nuevaPassword123',
        'password_confirmation' => 'nuevaPassword123',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.estado_registro', 'activo')
        ->assertJsonStructure(['data' => ['token']]);

    $this->assertSame(RegistrationStatus::Activo, $user->fresh()->estado_registro);
    $this->assertNotNull($user->fresh()->email_verified_at);
    $this->assertNotNull($user->activationTokens()->first()->used_at);

    $this->postJson('/api/login', [
        'username' => $user->email,
        'password' => 'nuevaPassword123',
    ])->assertOk();
});

it('aprueba un usuario en cuarentena', function () {
    $urbanismo = makeUrbanismo();
    $admin = makeUser($urbanismo, RegistrationStatus::Activo, Rol::ADMIN);
    $resident = makeUser($urbanismo, RegistrationStatus::Cuarentena);

    Sanctum::actingAs($admin);

    $this->postJson("/api/urbanismos/{$urbanismo->id}/users/{$resident->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.user.estado_registro', 'activo');

    $this->assertSame(RegistrationStatus::Activo, $resident->fresh()->estado_registro);
});

it('genera el token hasheado y envía el correo de activación', function () {
    Mail::fake();

    $urbanismo = makeUrbanismo();
    $user = makeUser($urbanismo, RegistrationStatus::Pendiente);

    (new SendOnboardingMagicLinkJob($user->id))->handle();

    $token = $user->activationTokens()->first();

    expect($token)->not->toBeNull()
        ->and($token->expires_at->isFuture())->toBeTrue();

    Mail::assertSent(ResidentOnboardingMail::class, function (ResidentOnboardingMail $mail) use ($user, $token) {
        parse_str((string) parse_url($mail->activationUrl, PHP_URL_QUERY), $query);

        return $mail->hasTo($user->email)
            && str_contains($mail->activationUrl, '/activar-cuenta?')
            && hash('sha256', $query['token'] ?? '') === $token->token_hash;
    });
});

it('importa residentes y despacha el magic link', function () {
    Queue::fake();

    $urbanismo = makeUrbanismo();
    $admin = makeUser($urbanismo, RegistrationStatus::Activo, Rol::ADMIN);

    Sanctum::actingAs($admin);

    $email = 'importado-'.Str::lower(Str::random(8)).'@example.com';
    $path = tempnam(sys_get_temp_dir(), 'residents');
    file_put_contents($path, "nombre,email,unidad,dni,telefono\nImportado Uno,{$email},Torre B - Apto 1,12345678,999888777\n");

    $file = new UploadedFile($path, 'residents.csv', 'text/csv', null, true);

    $this->post("/api/urbanismos/{$urbanismo->id}/residents/import", ['file' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.skipped', 0);

    $this->assertDatabaseHas('users', [
        'email' => $email,
        'urbanismo_id' => $urbanismo->id,
        'estado_registro' => 'pendiente',
    ]);

    Queue::assertPushed(SendOnboardingMagicLinkJob::class);
});
