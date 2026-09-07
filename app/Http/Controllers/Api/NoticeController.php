<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\Rol;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

class NoticeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $notices = Notice::with(['user'])
            ->where('type', 1)->orderBy('created_at', 'desc')->get();

        $announces = Notice::with(['user'])
            ->where('type', 2)->orderBy('created_at', 'desc');

        if ($request->only_my_posts == 'active') {
            $announces->where('user_id', $request->user()->id);
        } else {
            $this->applyPaysFilter($announces, $request);
        }

        $notices = $notices->map(fn ($n) => $this->safeNotice($n));
        $announces = $announces->get()->map(fn ($n) => $this->safeNotice($n));

        return $this->returnSuccess(200, ['notices' => $notices, 'announces' => $announces]);
    }

    private function safeNotice($notice): array
    {
        $data = $notice->toArray();
        $data['author_name'] = $notice->user?->name ?? 'Anónimo';
        unset($data['user']);

        return $data;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->rol_id === Rol::ADMIN || $user->rol_id === Rol::SUPER_ADMIN;

        if ((int) $request->type === 1 && ! $isAdmin) {
            return $this->returnFail(403, 'Solo los administradores pueden crear noticias.');
        }

        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        $notice = Notice::create([
            'title' => $request->title,
            'description' => htmlspecialchars($request->description),
            'group' => $request->group,
            'category' => $request->category,
            'type' => $request->type,
            'data_contact' => $this->dataContactByUser($user),
            'user_id' => $user->id,
            'views' => '[]',
            'status' => $isAdmin ? 2 : 1,
        ]);

        $this->uploadImages($notice, $request->file('img'));
        $this->sendNoticeNotification($notice);

        return $this->returnSuccess(200, 'ok');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $notice = Notice::with(['user'])->find($id);
        if (! $notice) {
            return $this->returnFail(404, 'Aviso no encontrado');
        }

        $data = $notice->toArray();
        $data['author_name'] = $notice->user?->name ?? 'Anónimo';
        unset($data['user']);

        $authUser = $request->user();
        if ($notice->user_id !== $authUser->id && ! in_array($authUser->rol_id, [Rol::ADMIN])) {
            unset($data['views']);
        }

        return $this->returnSuccess(200, $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $isAdmin = $user->rol_id === Rol::ADMIN || $user->rol_id === Rol::SUPER_ADMIN;

        $announce = Notice::find($id);
        if (! $announce) {
            return $this->returnFail(404, 'Publicación no encontrada');
        }

        if ((int) $announce->type === 1 && ! $isAdmin) {
            return $this->returnFail(403, 'Solo los administradores pueden editar noticias.');
        }

        if ((int) $announce->type !== 1 && $announce->user_id !== $user->id && ! $isAdmin) {
            return $this->returnFail(403, 'No tienes permiso para editar esta publicación.');
        }

        $announce->update([
            'title' => $request->title,
            'description' => htmlspecialchars($request->description),
            'group' => $request->group,
            'category' => $request->category,
        ]);

        return $this->returnSuccess(200, $announce);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request, $id)
    {
        try {
            $user = $request->user();
            $isAdmin = $user->rol_id === Rol::ADMIN || $user->rol_id === Rol::SUPER_ADMIN;

            $notice = Notice::find($id);
            if (! $notice) {
                return $this->returnFail(404, 'Publicación no encontrada');
            }

            if ((int) $notice->type === 1 && ! $isAdmin) {
                return $this->returnFail(403, 'Solo los administradores pueden eliminar noticias.');
            }

            if ((int) $notice->type !== 1 && $notice->user_id !== $user->id && ! $isAdmin) {
                return $this->returnFail(403, 'No tienes permiso para eliminar esta publicación.');
            }

            $notice->delete();
        } catch (Exception $th) {
            return $this->returnFail(500, $th->getMessage());
        }

        return $this->returnSuccess(200, 'OK');
    }

    public function setViewer(Request $request, $noticeId)
    {
        $notice = Notice::find($noticeId);
        $viewers = json_decode($notice->views, true) ?? [];

        if (in_array($request->user()->id, $viewers)) {
            return $this->returnSuccess(200, 'ok');
        }

        array_push($viewers, $request->user()->id);
        $notice->update([
            'views' => json_encode($viewers),
        ]);

        return $this->returnSuccess(200, 'ok');
    }

    public function setNewStatus(Request $request, $noticeId)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'Solo el administrador puede cambiar el estado de noticias'], 403);
        }

        $request->validate(['status' => 'required|integer|in:0,1,2']);

        $notice = Notice::findOrFail($noticeId);
        $notice->update([
            'status' => $request->status,
        ]);

        return $this->returnSuccess(200, ['status' => $notice->status]);
    }

    private function sendNoticeNotification($notice)
    {
        $users = User::where('rol_id', '!=', Rol::ADMIN)->where('status', 1)->get();

        try {
            Notification::send($users, new RealtimeNotification(
                title: 'Nuevo aviso publicado',
                message: $notice->title,
                url: '/client/notice/view/'.$notice->id,
                meta: [
                    'notice_id' => $notice->id,
                    'icon' => 'eva-bell-outline',
                ]
            ));
        } catch (\Throwable $e) {
            Log::error('Fallo al enviar notificación de notice: '.$e->getMessage());
        }
    }

    private function validateFieldsFromInput($inputs)
    {
        $rules = [
            'title' => ['required', 'regex:/^[a-z 0-9 A-Z-À-ÿ .\-]+$/i'],
            'description' => ['required'],
            'group' => ['required', 'numeric'],
            'category' => ['required', 'numeric'],
            'type' => ['required', 'numeric'],
        ];
        $messages = [
            'title.required' => 'El titulo de la publicación es requerido.',
            'title.regex' => 'Titulo de la publicación no valido',
            'decription.required' => 'Descripción de la publicación es requerida.',
            'decription.regex' => 'Descripción no valida',
            'group.required' => 'La publicacón debe pertenecer a un grupo',
            'group.numeric' => 'Grupo valido',
            'category.required' => 'La publicacón debe pertenecer a una categoria',
            'category.numeric' => 'Categoria no valida',
            'type.numeric' => 'Tipo no valido',
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();

        return $validator->all();
    }

    private function dataContactByUser($user)
    {
        if ($user->rol_id === Rol::ADMIN) {
            return 'Admin';
        }

        return '{"name":"'.$user->name.'", "apartment":"'.$user->apartaments[0]->number.'"}';
    }

    private function applyPaysFilter($query, Request $request)
    {
        $VIEW_ALL_STATUS = 4;

        // Filtro por estado
        if ($request->filled('status') && intval($request->status) !== $VIEW_ALL_STATUS) {
            $query->where('status', intval($request->status));
        }

        // Filtro por grupo
        if ($request->filled('group')) {
            $query->where('group', intval($request->group));
        }

        // Filtro por category
        if ($request->filled('category')) {
            $query->where('category', intval($request->category));
        }

        // Filtro por usuario
        if ($request->filled('post_by')) {
            $query->where('user_id', intval($request->post_by));
        }

        // Filtro por rango de fechas
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        // // Ordenamiento
        // $validSortFields = ['created_at', 'pay_date', 'amount', 'status'];
        // $sortBy = in_array($request->get('sort_by'), $validSortFields)
        //     ? $request->get('sort_by') : 'created_at';
        // $sortDir = $request->get('sort_dir') === 'asc' ? 'asc' : 'desc';
        // $query->orderBy($sortBy, $sortDir);
    }

    private function uploadImages($notice, $images)
    {
        $path = '';
        $allImages = [];

        if ($images) {
            for ($i = 0; $i < count($images); $i++) {
                $path = $this->getFormatNameImage($notice, $images[$i]);
                $imagesPath = public_path('storage').'/images/post/';
                $images[$i]->move($imagesPath, $path);

                array_push($allImages, $path);
            }
        }

        $notice->update(['img' => json_encode($allImages)]);
    }

    private function getFormatNameImage($notice, $image)
    {
        $rand = rand(1000000, 9999999);
        $fileName = trim(str_replace(' ', '_', $notice->id));
        $extension = $image->extension();

        return config('app.url')."public/storage/images/post/{$rand}_{$fileName}.{$extension}";
    }
}
