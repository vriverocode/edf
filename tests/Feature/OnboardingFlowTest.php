<?php

use App\Enums\RegistrationStatus;
use App\Jobs\SendOnboardingMagicLinkJob;
use App\Mail\ResidentOnboardingMail;
use App\Mail\ResidentWelcomeMail;
use App\Models\AccountActivationToken;
use App\Models\Departament;
use App\Models\Rol;
use App\Models\Urbanismo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

it('crea una cuenta pendiente sin comunidad y devuelve un token de registro', function () {
    $email = 'nuevo-'.Str::lower(Str::random(8)).'@example.com';

    $response = $this->postJson('/api/register', [
        'nombre' => 'Nuevo Residente',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.user.email', $email)
        ->assertJsonStructure(['data' => ['registration_token', 'expires_at', 'user']]);

    $this->assertDatabaseHas('users', [
        'email' => $email,
        'urbanismo_id' => null,
        'estado_registro' => 'pendiente',
        'rol_id' => Rol::PROPIETARIO,
    ]);

    $user = User::where('email', $email)->first();
    $token = AccountActivationToken::where('user_id', $user->id)
        ->where('purpose', 'registration')
        ->first();

    expect($token)->not->toBeNull()
        ->and($token->expires_at->isFuture())->toBeTrue();
});

it('vincula la comunidad con un código válido y deja la cuenta en cuarentena', function () {
    $urbanismo = makeUrbanismo();
    $email = 'nuevo-'.Str::lower(Str::random(8)).'@example.com';

    $register = $this->postJson('/api/register', [
        'nombre' => 'Nuevo Residente',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(201);

    $registrationToken = $register->json('data.registration_token');

    $response = $this->postJson('/api/register/community', [
        'registration_token' => $registrationToken,
        'codigo_invitacion' => $urbanismo->codigo_invitacion,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.estado_registro', 'cuarentena')
        ->assertJsonPath('data.urbanismo.id', $urbanismo->id);

    $this->assertDatabaseHas('users', [
        'email' => $email,
        'urbanismo_id' => $urbanismo->id,
        'estado_registro' => 'cuarentena',
    ]);

    $user = User::where('email', $email)->first();

    $this->assertNotNull(
        AccountActivationToken::where('user_id', $user->id)
            ->where('purpose', 'registration')
            ->value('used_at')
    );
});

it('rechaza un código de invitación inexistente en el paso 2', function () {
    $email = 'invalido-'.Str::lower(Str::random(8)).'@example.com';

    $register = $this->postJson('/api/register', [
        'nombre' => 'Nuevo Residente',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(201);

    $this->postJson('/api/register/community', [
        'registration_token' => $register->json('data.registration_token'),
        'codigo_invitacion' => 'NOEXISTE',
    ])->assertStatus(422);

    $this->assertDatabaseHas('users', [
        'email' => $email,
        'urbanismo_id' => null,
        'estado_registro' => 'pendiente',
    ]);
});

it('permite reanudar el registro con el mismo correo y contraseña', function () {
    $email = 'reanudar-'.Str::lower(Str::random(8)).'@example.com';

    $payload = [
        'nombre' => 'Nuevo Residente',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $first = $this->postJson('/api/register', $payload)->assertStatus(201);
    $second = $this->postJson('/api/register', $payload)->assertStatus(201);

    expect($second->json('data.registration_token'))->not->toBe($first->json('data.registration_token'));

    $user = User::where('email', $email)->first();

    expect(AccountActivationToken::where('user_id', $user->id)
        ->where('purpose', 'registration')
        ->count())->toBe(1);
});

it('rechaza reanudar el registro si la contraseña no coincide', function () {
    $email = 'reanudar-'.Str::lower(Str::random(8)).'@example.com';

    $this->postJson('/api/register', [
        'nombre' => 'Nuevo Residente',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(201);

    $this->postJson('/api/register', [
        'nombre' => 'Nuevo Residente',
        'email' => $email,
        'password' => 'otraPassword123',
        'password_confirmation' => 'otraPassword123',
    ])->assertStatus(422);
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

it('importa residentes activos con credenciales y unidad vinculada', function () {
    Mail::fake();

    $urbanismo = makeUrbanismo();
    $admin = makeUser($urbanismo, RegistrationStatus::Activo, Rol::ADMIN);

    Sanctum::actingAs($admin);

    $email = 'importado-'.Str::lower(Str::random(8)).'@example.com';
    $unit = 'TST-'.Str::upper(Str::random(6));
    $suffix = Str::lower(Str::random(8));
    $apellido = 'Pérez'.$suffix;
    $username = 'jperez'.$suffix;
    $password = $username.'2020**';
    $path = tempnam(sys_get_temp_dir(), 'residents');
    file_put_contents(
        $path,
        "nombre,apellido,email,telefono,dpt/casa/predio\nJuan,{$apellido},{$email},999888777,{$unit}\n"
    );

    $file = new UploadedFile($path, 'residents.csv', 'text/csv', null, true);

    $this->post("/api/urbanismos/{$urbanismo->id}/residents/import", ['file' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.skipped', 0)
        ->assertJsonPath('data.credentials.0.username', $username);

    $user = User::where('email', $email)->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Juan '.$apellido)
        ->and($user->username)->toBe($username)
        ->and($user->phone)->toBe('999888777')
        ->and($user->unidad)->toBe($unit)
        ->and($user->estado_registro)->toBe(RegistrationStatus::Activo)
        ->and($user->is_first_time)->toBe(1)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check($password, $user->password))->toBeTrue();

    $departament = Departament::where('number', $unit)->first();

    expect($departament)->not->toBeNull()
        ->and($departament->user_id)->toBe($user->id);

    Mail::assertSent(ResidentWelcomeMail::class, function (ResidentWelcomeMail $mail) use ($email, $username, $password) {
        return $mail->hasTo($email)
            && $mail->username === $username
            && $mail->password === $password;
    });
});

it('amplía el username cuando ya existe', function () {
    Mail::fake();

    $urbanismo = makeUrbanismo();
    $admin = makeUser($urbanismo, RegistrationStatus::Activo, Rol::ADMIN);
    $suffix = Str::lower(Str::random(6));
    $base = 'jperez'.$suffix;

    foreach ([$base, 'juperez'.$suffix] as $username) {
        makeUser($urbanismo, RegistrationStatus::Activo)->forceFill(['username' => $username])->save();
    }

    Sanctum::actingAs($admin);

    $email = 'colision-'.Str::lower(Str::random(8)).'@example.com';
    $unit = 'TST-'.Str::upper(Str::random(6));
    $path = tempnam(sys_get_temp_dir(), 'residents');
    file_put_contents(
        $path,
        "nombre,apellido,email,telefono,dpt/casa/predio\nJuan{$suffix},Perez{$suffix},{$email},999888777,{$unit}\n"
    );

    $file = new UploadedFile($path, 'residents.csv', 'text/csv', null, true);

    $this->post("/api/urbanismos/{$urbanismo->id}/residents/import", ['file' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.credentials.0.username', 'juaperez'.$suffix);
});

it('rechaza el excel si falta una columna obligatoria', function () {
    $urbanismo = makeUrbanismo();
    $admin = makeUser($urbanismo, RegistrationStatus::Activo, Rol::ADMIN);

    Sanctum::actingAs($admin);

    $path = tempnam(sys_get_temp_dir(), 'residents');
    file_put_contents($path, "nombre,email,telefono,unidad\nJuan,juan@example.com,999888777,101\n");

    $file = new UploadedFile($path, 'residents.csv', 'text/csv', null, true);

    $response = $this->post(
        "/api/urbanismos/{$urbanismo->id}/residents/import",
        ['file' => $file],
        ['Accept' => 'application/json']
    );

    $response->assertStatus(422);
    expect($response->json('error'))->toContain('apellido');
});

it('omite residentes con correo duplicado', function () {
    Mail::fake();

    $urbanismo = makeUrbanismo();
    $admin = makeUser($urbanismo, RegistrationStatus::Activo, Rol::ADMIN);

    Sanctum::actingAs($admin);

    $email = 'duplicado-'.Str::lower(Str::random(8)).'@example.com';
    $unit = 'TST-'.Str::upper(Str::random(6));
    $path = tempnam(sys_get_temp_dir(), 'residents');
    file_put_contents(
        $path,
        "nombre,apellido,email,telefono,dpt/casa/predio\n"
        ."Ana,Torres,{$email},999888777,{$unit}\n"
        ."Ana,Torres,{$email},999888777,{$unit}-B\n"
    );

    $file = new UploadedFile($path, 'residents.csv', 'text/csv', null, true);

    $this->post("/api/urbanismos/{$urbanismo->id}/residents/import", ['file' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.skipped', 1);

    expect(User::where('email', $email)->count())->toBe(1);
});

it('reporta error si la unidad ya pertenece a otro propietario', function () {
    Mail::fake();

    $urbanismo = makeUrbanismo();
    $admin = makeUser($urbanismo, RegistrationStatus::Activo, Rol::ADMIN);
    $owner = makeUser($urbanismo, RegistrationStatus::Activo);
    $unit = 'TST-'.Str::upper(Str::random(6));

    Departament::create([
        'number' => $unit,
        'type' => Departament::TYPE_DEPARTAMENTO,
        'description' => 'Ocupable',
        'area' => 0,
        'floor' => 'N/A',
        'user_id' => $owner->id,
    ]);

    Sanctum::actingAs($admin);

    $email = 'ocupada-'.Str::lower(Str::random(8)).'@example.com';
    $path = tempnam(sys_get_temp_dir(), 'residents');
    file_put_contents(
        $path,
        "nombre,apellido,email,telefono,dpt/casa/predio\nLuis,Ramos,{$email},999888778,{$unit}\n"
    );

    $file = new UploadedFile($path, 'residents.csv', 'text/csv', null, true);

    $response = $this->post(
        "/api/urbanismos/{$urbanismo->id}/residents/import",
        ['file' => $file],
        ['Accept' => 'application/json']
    );

    $response->assertOk()
        ->assertJsonPath('data.created', 0);

    expect($response->json('data.errors.0.errores.0'))->toContain('ya está asignada')
        ->and(User::where('email', $email)->exists())->toBeFalse();
});

it('descarga la plantilla de importación de residentes', function () {
    $urbanismo = makeUrbanismo();
    $admin = makeUser($urbanismo, RegistrationStatus::Activo, Rol::ADMIN);

    Sanctum::actingAs($admin);

    $this->get("/api/urbanismos/{$urbanismo->id}/residents/import/template")
        ->assertOk()
        ->assertDownload('plantilla-residentes.xlsx');
});

it('permite completar el primer ingreso conservando el mismo email', function () {
    $urbanismo = makeUrbanismo();
    $user = makeUser($urbanismo, RegistrationStatus::Activo);

    $user->forceFill(['is_first_time' => 1])->save();

    Sanctum::actingAs($user);

    $this->postJson('/api/users/complete-first-time', [
        'email' => $user->email,
        'password' => 'nuevaPassword123',
        'password_confirmation' => 'nuevaPassword123',
        'phone' => '999888777',
    ])->assertOk();

    expect($user->fresh()->is_first_time)->toBe(0);
});
