<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NoticeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $notices = Notice::with(['user'])
            ->where('type', 1)->orderBy('created_at', 'desc')->get();

        $announces = Notice::with(['user'])
            ->where('type', 2)->orderBy('created_at', 'desc');

        if ($request->only_my_posts == 'active') {
            $announces->where('user_id', $request->user()->id);
        } else {
            $this->applyPaysFilter($announces, $request);
        }

        return $this->returnSuccess(200, ['notices' => $notices, 'announces' => $announces->get()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
            'data_contact' => $this->dataContactByUser($request->user()),
            'user_id' => $request->user()->id,
            'views' => '[]',
            'status' => $request->user()->rol_id == 1 ? 2 : 1,
        ]);

        $this->uploadImages($notice, $request->file('img'));
        $this->sendNoticeNotification($notice);

        return $this->returnSuccess(200, 'ok');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $notice = Notice::with(['user'])->find($id);

        return $this->returnSuccess(200, $notice);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
        $announce = Notice::find($id);
        if (! $announce) {
            return $this->returnFail(404, 'Anuncion no encontrado');
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
        //
        try {
            // code...
            $notice = Notice::find($id);

            if ($notice->user_id == $request->user()->id) {
                $notice->delete();
            }
        } catch (Exception $th) {
            // throw $th;
            return $this->returnFail(500, $th->getMessage());
        }

        return $this->returnSuccess(200, 'OK');
    }

    public function setViewer(Request $request, $noticeId)
    {
        $notice = Notice::find($noticeId);
        $viewers = json_decode($notice->views, true);

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
        $notice = Notice::findOrFail($noticeId);
        $notice->update([
            'status' => $request->status,
        ]);

        return $this->returnSuccess(200, ['status' => $notice->status]);
    }

    private function sendNoticeNotification($notice)
    {
        $users = User::where('rol_id', '!=', 1)->where('status', 1)->get();

        try {
            foreach ($users as $user) {
                $user->notify(new RealtimeNotification(
                    title: 'Nuevo aviso publicado',
                    message: $notice->title,
                    url: '/client/notices',
                    meta: [
                        'notice_id' => $notice->id,
                        'icon' => 'campaign',
                    ]
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Fallo al enviar notificación de notice: '.$e->getMessage());
        }
    }

    private function validateFieldsFromInput($inputs)
    {
        $rules = [
            'title' => ['required', 'regex:/^[a-z 0-9 A-Z-À-ÿ .\-]+$/i'],
            'description' => ['required', 'regex:/^[a-z a-z 0-9 A-Z-À-ÿ !+?¡¿:., \- \r \n  &]+$/i'],
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
        if ($user->rol_id == 1) {
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

        return config('app.url')."/storage/images/post/{$rand}_{$fileName}.{$extension}";
    }
}
