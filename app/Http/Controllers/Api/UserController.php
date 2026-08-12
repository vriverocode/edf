<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AirbnbRent;
use App\Models\Booking;
use App\Models\Departament;
use App\Models\Notice;
use App\Models\PeoplesXDepartaments;
use App\Models\Rol;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\RealtimeNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function getUserById(Request $request, $id)
    {
        $user = User::with(['units', 'availableComunAreas'])->find($id);
        if (! $user) {
            return $this->returnFail(404, 'Usuario no encontrado');
        }

        $authUser = $request->user();
        if ((int) $authUser->id !== (int) $id && ! in_array($authUser->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            $isOwnResident = PeoplesXDepartaments::where('user_id', $id)
                ->where(function ($q) use ($authUser) {
                    $q->whereHas('departament', fn ($sub) => $sub->where('user_id', $authUser->id))
                        ->orWhere('created_by', $authUser->id);
                })
                ->exists();
            if (! $isOwnResident) {
                return $this->returnFail(403, 'No autorizado');
            }
        }

        return $this->returnSuccess(200, $user);
    }

    public function store(Request $request)
    {

        $validated = $this->validateFieldsFromInput($request->all());

        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        $creatorRole = request()->user()->rol_id;
        $requestedRole = $request->rol_id ?? 2;
        if ($creatorRole !== Rol::ADMIN && $requestedRole <= $creatorRole) {
            return response()->json(['code' => 403, 'error' => 'No puedes crear usuarios con este rol'], 403);
        }

        if ((int) $request->idRol === Rol::INQUILINO
            && (int) $request->idApartament !== 0
            && $this->departmentHasActiveInquilino((int) $request->idApartament)) {
            return $this->returnFail(409, 'El departamento ya tiene un inquilino activo.');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'rol_id' => $request->idRol,
            // 'parentesco' => $request->parentesco ?? null,
            'active_time' => $request->active_time ?? null,
            'is_first_time' => 1,
        ]);
        $this->afteSaveUser($user, $request);

        return $this->returnSuccess(200, 'ok');
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->returnFail(404, 'Usuario no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username,'.$id],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$id],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        $data = $request->only(['name', 'username', 'email', 'phone']);
        $data = array_filter($data, fn ($v) => $v !== null && $v !== '');

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return $this->returnSuccess(200, 'ok');
    }

    /**
     * Crea usuarios temporales (Airbnb) o habitantes/familiares del propietario.
     *
     * @param string tipo - 'airbnb' para alquiler temporal, 'familiar' para familiares que viven con el propietario
     * @param string name - Nombre completo
     * @param string email - Correo electrónico (único)
     * @param int idApartament - ID del departamento
     * @param string active_time - (Solo Airbnb) Fecha de fin del alquiler (Y-m-d)
     * @param string parentesco - (Solo Familiar) Relación: padre, madre, hijo, hija, cónyuge, etc.
     * @param string phone - (Opcional) Teléfono
     * @param string password - (Opcional) Si no se envía, se genera automáticamente
     */
    public function storeResidentUser(Request $request)
    {
        $validated = $this->validateTemporaryOrResidentInput($request->all());

        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        // Iniciamos la transacción para asegurar integridad total
        DB::beginTransaction();

        try {
            if ($request->user()->rol_id === Rol::PROPIETARIO) {
                $departament = Departament::find($request->idApartament);
                if (! $departament || $departament->user_id !== $request->user()->id) {
                    throw new Exception('No tiene permiso para registrar usuarios en este departamento.');
                }
            }

            $tipo = $request->type;
            $isAirbnb = $tipo === 'airbnb';
            $isFamiliar = $tipo === 'familiar';
            $isInquilino = $tipo === 'inquilino';

            $rolId = Rol::FAMILIAR;
            if ($isAirbnb) {
                $rolId = Rol::AIRBNB;
            }
            if ($isInquilino) {
                $rolId = Rol::INQUILINO;
            }

            if ($isInquilino && $this->departmentHasActiveInquilino((int) $request->idApartament)) {
                throw new Exception('El departamento ya tiene un inquilino activo.');
            }

            // Preparación de credenciales
            $username = $request->username ?? Str::lower(Str::random(8)).'_'.time();
            $password = $request->password ?? Str::password(12);
            $activeTime = $isAirbnb ? strtotime($request->active_time) : null;
            $dateEnd = $isAirbnb ? strtotime($request->end_time) : null;

            // 1. Crear el Usuario principal (el que accede a la app)
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $username,
                'phone' => $request->phone ?? null,
                'password' => Hash::make($password),
                'rol_id' => $rolId,
                // 'parentesco' => $isFamiliar ? $request->parentesco : null,
                'status' => $isAirbnb ? 3 : 1,
                'active_time' => $isAirbnb ? date('Y-m-d', $activeTime) : null,
                'end_time' => $isAirbnb ? date('Y-m-d', $dateEnd) : null,

            ]);

            // 2. Vincular usuario al departamento
            if ($isInquilino) {
                $this->createInquilinoRelation($user, (int) $request->idApartament);
            } else {
                PeoplesXDepartaments::create([
                    'user_id' => $user->id,
                    'departament_id' => $request->idApartament,
                    'type' => $rolId,
                    'created_by' => $request->user()->id,
                ]);
            }

            if ($isAirbnb) {
                $airbnbData = $request->input('airbnb');

                // 3. Registrar la Renta en AirbnbRent
                $rent = AirbnbRent::create([
                    'departament_id' => $request->idApartament,
                    'assing_to' => $user->id,
                    'name_to' => $airbnbData['nameTo'],
                    'created_by' => $request->user()->id,
                    'quantity' => $airbnbData['quantity'],
                    'init_day' => date('Y-m-d', strtotime($airbnbData['init_time'])),
                    'end_date' => date('Y-m-d', strtotime($airbnbData['end_time'])),
                    'status' => 1,
                ]);

                // 4. Registrar a los acompañantes como Visitas (Type 3)
                if (isset($airbnbData['guests']) && is_array($airbnbData['guests'])) {
                    foreach ($airbnbData['guests'] as $index => $guest) {
                        // 1. Creamos el registro primero, dejando la foto en null por ahora
                        $visit = Visit::create([
                            'departament_id' => $request->idApartament,
                            'created_by' => $request->user()->id,
                            'fullname' => $guest['name'],
                            'dni' => $guest['document'],
                            'type' => 3,
                            'photo' => null, // Se actualizará después
                            'date' => date('Y-m-d', strtotime($airbnbData['init_time'])),
                            'hour' => date('H:i'),
                            'status' => 1,
                            'airbnb_rent_id' => $rent->id,
                        ]);

                        // 2. Extraemos el archivo usando notación de puntos de Laravel
                        $photoFile = $request->file("airbnb.guests.{$index}.photo");

                        // 3. Si el archivo existe y es válido, llamamos a la nueva función
                        if ($photoFile && $photoFile->isValid()) {
                            $this->uploadGuestPhoto($visit, $photoFile);
                        }
                    }
                }
            }
            DB::commit();

            return $this->returnSuccess(200, [
                'message' => $isAirbnb ? 'Airbnb y acompañantes registrados' : 'Residente registrado con éxito',
                'user_id' => $user->id,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en storeResidentUser: '.$e->getMessage());

            return $this->returnFail(500, 'No se pudo completar el registro: '.$e->getMessage());
        }
    }

    private function uploadGuestPhoto($visit, $photoFile)
    {
        $relativePath = '';
        $rand = rand(1000000, 9999999);
        $fileName = trim(str_replace(' ', '_', $visit->id));
        $extension = $photoFile->extension();

        $imageName = "{$rand}_{$fileName}.{$extension}";

        // Ruta relativa que se guardará en la base de datos
        $relativePath = "/images/airbnb/guest/{$imageName}";

        // Ruta física absoluta en el servidor donde se moverá
        $destinationPath = public_path().'/images/airbnb/guest/';

        // Movemos el archivo a la carpeta pública
        $photoFile->move($destinationPath, $relativePath);

        // Actualizamos el registro de la visita con la ruta y guardamos
        $visit->photo = $relativePath;
        $visit->save();
    }

    /**
     * Obtiene los residentes y usuarios Airbnb registrados por el usuario autenticado (propietario).
     *
     * @param  Request  $request  - Usa request->user()->id como created_by
     */
    public function getResident(Request $request)
    {
        $residents = PeoplesXDepartaments::with(['user.rol', 'departament'])
            ->where('created_by', $request->user()->id)
            ->whereIn('type', [Rol::FAMILIAR, Rol::AIRBNB, Rol::INQUILINO])
            ->whereHas('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($people) {
                $typeLabel = 'Familiar';
                if ($people->type === Rol::AIRBNB) {
                    $typeLabel = 'Airbnb';
                }
                if ($people->type === Rol::INQUILINO) {
                    $typeLabel = 'Inquilino';
                }

                return [
                    'id' => $people->id,
                    'type' => $people->type,
                    'type_label' => $typeLabel,
                    'user' => $people->user,
                    'departament' => $people->departament,
                    'active_time' => $people->user?->active_time,
                    // 'parentesco' => $people->user?->parentesco,
                ];
            });

        return $this->returnSuccess(200, $residents);
    }

    private function validateTemporaryOrResidentInput(array $inputs): array
    {
        $userId = $inputs['id'] ?? $inputs['user_id'] ?? null;

        $rules = [
            'type' => ['required', 'in:airbnb,familiar,inquilino'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at'),
            ],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($userId)->whereNull('deleted_at'),
            ],
            'idApartament' => ['required', 'integer', 'exists:departaments,id'],
        ];

        if (($inputs['type'] ?? '') === 'airbnb') {
            $rules['active_time'] = ['required', 'date'];
            $rules['airbnb.nameTo'] = ['required', 'string'];
            $rules['airbnb.quantity'] = ['required', 'integer', 'min:1'];
            $rules['airbnb.init_time'] = ['required', 'date'];
            $rules['airbnb.end_time'] = ['required', 'date', 'after_or_equal:airbnb.init_time'];

            // Validaciones para cada acompañante
            $rules['airbnb.guests'] = ['required', 'array'];
            $rules['airbnb.guests.*.name'] = ['required', 'string'];
            $rules['airbnb.guests.*.document'] = ['required', 'string'];
            $rules['airbnb.guests.*.photo'] = ['nullable', 'image', 'max:4096']; // Max 4MB
        } elseif (($inputs['type'] ?? '') === 'familiar') {
            // $rules['parentesco'] = ['required', 'string'];
        }
        $messages = [
            'name.required' => 'El nombre es requerido',
            'email.required' => 'El email es requerido',
            'email.unique' => 'El email ya se encuetra registrado',
            'username.required' => 'El nombre de usuario es requerido',
            'username.unique' => 'El nombre de usuario ya se encuentra registrado',
            'idApartament.required' => 'El departamento es requerido',
            'active_time.required' => 'La fecha de inicio es requerida',
            'airbnb.nameTo.required' => 'El nombre del titular es requerido',
            'airbnb.quantity.required' => 'La cantidad de acompañantes es requerida',
            'airbnb.init_time.required' => 'La fecha de inicio es requerida',
            'airbnb.end_time.required' => 'La fecha de fin es requerida',
            'airbnb.end_time.after_or_equal' => 'La fecha de fin debe ser mayor o igual a la fecha de inicio',
            'airbnb.guests.required' => 'Los acompañantes son requeridos',
            'airbnb.guests.*.name.required' => 'El nombre del acompañante es requerido',
            'airbnb.guests.*.document.required' => 'El documento del acompañante es requerido',
            'airbnb.guests.*.photo.required' => 'La foto del acompañante es requerida',
        ];
        $validator = Validator::make($inputs, $rules, $messages);

        return $validator->fails() ? $validator->errors()->all() : [];
    }

    private function afteSaveUser($user, $request)
    {
        if ($request->user()->rol_id != Rol::ADMIN || (int) $request->idApartament == 0) {
            return;
        }

        if ((int) $request->idRol === Rol::INQUILINO) {
            $this->createInquilinoRelation($user, (int) $request->idApartament);

            return;
        }

        Departament::find($request->idApartament)->update([
            'user_id' => $user->id,
        ]);
    }

    private function departmentHasActiveInquilino(int $departamentId): bool
    {
        return PeoplesXDepartaments::where('departament_id', $departamentId)
            ->where('type', Rol::INQUILINO)
            ->whereHas('user', fn ($q) => $q->where('status', '!=', 3)->whereNull('deleted_at'))
            ->exists();
    }

    private function createInquilinoRelation(User $user, int $departamentId): void
    {
        $departament = Departament::find($departamentId);

        PeoplesXDepartaments::create([
            'user_id' => $user->id,
            'departament_id' => $departamentId,
            'type' => Rol::INQUILINO,
            'created_by' => $departament->user_id,
        ]);
    }

    public function getOwners(Request $request)
    {
        $query = User::with(['units', 'rol']);

        if ($request->filled('rol')) {
            $query->where('rol_id', $request->integer('rol'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('units', fn ($u) => $u->where('number', 'like', "%{$search}%"));
            });
        }

        $query->orderBy('name', 'asc');

        if ($request->boolean('paginate', true)) {
            $perPage = $request->integer('per_page', 15);
            $owners = $query->paginate($perPage);
        } else {
            $owners = $query->get();
        }

        return $this->returnSuccess(200, $owners);
    }

    public function getOwnersWithoutApartment()
    {
        $owners = User::with('rol')
            ->where('rol_id', Rol::PROPIETARIO)
            // ->whereDoesntHave('apartaments')
            ->orderBy('name', 'asc')
            ->get();

        return $this->returnSuccess(200, $owners);
    }

    public function getUsersOptions()
    {
        $users = User::select('id', 'name')
            ->orderBy('name', 'asc')
            ->whereIn('rol_id', [Rol::PROPIETARIO, Rol::FAMILIAR, Rol::AIRBNB, Rol::INQUILINO])
            ->get();

        return $this->returnSuccess(200, $users);
    }

    public function getCountPendingsForAdmin()
    {
        $user = request()->user();
        if ($user->rol_id != Rol::ADMIN && $user->rol_id != Rol::SUPER_ADMIN) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $noticeTypeForAnnunce = 2;
        $waitStatus = [
            'reserve' => 2,
            'announces' => 1,
        ];
        $reservesPendings = Booking::where('status', $waitStatus['reserve'])->get();
        $announcesPendings = Notice::where('status', $waitStatus['announces'])
            ->where('type', $noticeTypeForAnnunce)->get();

        return $this->returnSuccess(200, [
            'reserves' => $reservesPendings,
            'announces' => $announcesPendings,
        ]);
    }

    public function getAllUserWithPublish()
    {
        $users = User::whereHas('announces')->get();

        return $this->returnSuccess(200, $users);
    }

    private function validateFieldsFromInput($inputs)
    {
        $rules = [
            'name' => ['required', 'regex:/^[a-zA-Z-À-ÿ .]+$/i'],
            'email' => ['required', 'email', 'unique:users'],
            'username' => ['required', 'unique:users', 'regex:/^[a-zA-Z-À-ÿ0-9 .]+$/i'],
            'password' => ['required', 'min:8'],

        ];
        $messages = [
            'name.required' => 'El nombre es requerido.',
            'name.regex' => 'Nombre no valido',
            'email.required' => 'El email es requerido.',
            'email.unique' => 'El email ya esta registrado.',
            'email.email' => 'Email no valido',
            'username.required' => 'Nombre de usuario es requerido',
            'username.unique' => 'Nombre de usuario ya esta registrado.',
            'username.regex' => 'Nombre de usuario no valido',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener un minimo de 8 caracteres',
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();

        return $validator->all();
    }

    public function saveTokenMovile(Request $request)
    {
        try {
            $request->validate(['token' => 'required|string']);

            // Asumiendo que usas Auth
            $user = $request->user();
            $user->update(['device_token' => $request->token]);
        } catch (Exception $th) {
            return $this->returnFail(400, $th->getMessage());
            // throw $th;
        }

        return $this->returnSuccess(200, 'ok');
    }

    public function pruebaRealtimeNotification()
    {
        $user = User::find(3);
        try {
            $user->notify(new RealtimeNotification(
                title: 'Notificación de prueba',
                message: 'Esta es una notificación en tiempo real de prueba.',
                url: '/',
                meta: [
                    'icon' => 'eva-bell-outline',
                ]
            ));

            return $this->returnSuccess(200, 'Notificación enviada');
        } catch (Exception $e) {
            return $this->returnFail(500, $e->getMessage());
        }
    }

    public function setAvailableComunAreaToReserve(Request $request, $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->returnFail(404, 'Usuario no encontrado');
        }

        $authUser = $request->user();
        if ($authUser->rol_id === Rol::PROPIETARIO || $authUser->rol_id === Rol::PARCIAL) {
            $isOwnResident = PeoplesXDepartaments::where('user_id', $id)
                ->where(function ($q) use ($authUser) {
                    $q->whereHas('departament', fn ($sub) => $sub->where('user_id', $authUser->id))
                        ->orWhere('created_by', $authUser->id);
                })
                ->exists();
            if (! $isOwnResident) {
                return $this->returnFail(403, 'No tienes permisos para configurar las áreas de este usuario');
            }
        }

        $validator = Validator::make($request->all(), [
            'comun_area_ids' => ['required', 'array'],
            'comun_area_ids.*' => ['integer', 'exists:comun_areas,id'],
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        try {
            $areaIds = collect($request->comun_area_ids)->filter()->values()->all();
            $user->availableComunAreas()->sync($areaIds, ['created_by' => $request->user()->id]);
        } catch (Exception $e) {
            return $this->returnFail(500, $e->getMessage());
        }

        return $this->returnSuccess(200, 'Áreas comunes actualizadas');
    }

    /**
     * Completa la configuración de primer inicio de sesión.
     * Actualiza contraseña, teléfono y desactiva la bandera is_first_time.
     */
    public function completeFirstTime(Request $request)
    {
        $user = $request->user();

        if (! $user->is_first_time) {
            return $this->returnFail(409, 'Este usuario ya completó su configuración inicial.');
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['required', 'string', 'min:7'],
        ], [
            'email.required' => 'El email es requerido.',
            'email.email' => 'El email no es válido.',
            'email.unique' => 'El email ya está registrado.',
            'password.required' => 'La contraseña es requerida.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'phone.required' => 'El número de teléfono es requerido.',
            'phone.min' => 'El número de teléfono debe tener al menos 7 dígitos.',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $user->update([
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'email' => $request->email,
                'is_first_time' => 0,
            ]);

            return $this->returnSuccess(200, 'Configuración completada exitosamente.');
        } catch (Exception $e) {
            Log::error('Error en completeFirstTime: '.$e->getMessage());

            return $this->returnFail(500, 'Error al actualizar los datos.');
        }
    }

    /**
     * Actualiza los datos de contacto del usuario autenticado (email, teléfono, contraseña).
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:7'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'El correo electrónico es requerido.',
            'email.email' => 'Ingrese un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está en uso.',
            'phone.required' => 'El número de teléfono es requerido.',
            'phone.min' => 'El número de teléfono debe tener al menos 7 dígitos.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        try {
            $data = [
                'email' => $request->email,
                'phone' => $request->phone,
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            return $this->returnSuccess(200, 'Datos actualizados correctamente.');
        } catch (Exception $e) {
            Log::error('Error en updateProfile: '.$e->getMessage());

            return $this->returnFail(500, 'Error al actualizar los datos.');
        }
    }

    /**
     * Actualiza los datos de un residente/familiar/airbnb creado por el propietario autenticado.
     */
    public function updateResident(Request $request, int $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->returnFail(404, 'Usuario no encontrado');
        }
        // Verificar que el usuario autenticado es el creador de este residente
        $isCreator = PeoplesXDepartaments::where('user_id', $id)
            ->where('created_by', $request->user()->id)
            ->exists();

        if (! $isCreator) {
            return $this->returnFail(403, 'No tiene permiso para editar este usuario.');
        }

        if ($this->validateIdEmailUsed($request, $user)) {
            return $this->returnFail(400, 'Correo electrónico ya esta en uso');
        }
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string'],
            // 'parentesco' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8'],
        ], [
            'name.required' => 'El nombre es requerido.',
            'email.required' => 'El c es requerido.',
            'email.email' => 'El email no es válido.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $data = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                // 'parentesco' => $request->parentesco,
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            return $this->returnSuccess(200, 'Usuario actualizado con éxito.');
        } catch (Exception $e) {
            Log::error('Error en updateResident: '.$e->getMessage());

            return $this->returnFail(500, 'Error al actualizar el usuario.');
        }
    }

    /**
     * Obtiene las reservas de un residente creado por el propietario autenticado.
     */
    public function getResidentBookings(Request $request, int $userId)
    {
        // Verificar que el usuario autenticado es el creador de este residente
        $isCreator = PeoplesXDepartaments::where('user_id', $userId)
            ->where('created_by', $request->user()->id)
            ->exists();

        if (! $isCreator) {
            return $this->returnFail(403, 'No tiene permiso para ver las reservas de este usuario.');
        }

        $bookings = Booking::with('comunArea', 'pay')
            ->where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->get();

        return $this->returnSuccess(200, $bookings);
    }

    public function destroy(int $id, Request $request)
    {
        $authUser = request()->user();
        $isAdmin = $authUser->rol_id === Rol::ADMIN;

        // El propietario solo puede eliminar usuarios que él mismo creó
        $isCreator = PeoplesXDepartaments::where('user_id', $id)
            ->where('created_by', $authUser->id)
            ->exists();

        if (! $isAdmin && ! $isCreator) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $user = User::find($id);
        if (! $user) {
            return $this->returnFail(404, 'Usuario no encontrado');
        }

        if ($user->rol_id === Rol::ADMIN) {
            return $this->returnFail(400, 'No se puede eliminar un usuario administrador');
        }

        // Cancelar reservas pendientes (mantener el registro)
        Booking::where('user_id', $id)
            ->whereIn('status', [Booking::STATUS_PENDING_PAY, Booking::STATUS_PENDING_APPROVAL, Booking::STATUS_SUCCESS])
            ->update(['status' => Booking::STATUS_CANCELLED]);

        $user->delete();

        return $this->returnSuccess(200, 'Usuario eliminado con éxito');
    }

    private function validateIdEmailUsed($request, $user)
    {
        $userToValidate = User::where('email', $request->email)->first();

        return $userToValidate->id !== $user->id;
    }
}
