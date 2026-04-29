<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Departament;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class DepartamentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function paginationApartment(Request $request)
    {
        $departaments = Departament::with('owner')->paginate(15);
        return $this->returnSuccess(200, $departaments);
    }

    /**
     * Display a listing of the resource.
     */
    public function apartmentsByfind(Request $request)
    {
        $departaments = [];
        if ($request->find == 'available') {
            $departaments = Departament::where('user_id', null)->get();
        }
        if ($request->find == 'allWithUser') {
            $departaments = Departament::where('user_id', '!=', null)->get();
        }
        return $this->returnSuccess(200, $departaments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeApartment(Request $request)
    {
        //
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        Departament::create([
            'number' => $request->number,
            'address' => $request->address,
            'block' => $request->block,
            'area' => $request->area,
            'description' => $request->description,
            'floor' => $request->floor,
            'participation_percentage' => $request->participation_percentage,
        ]);

        return $this->returnSuccess(200, 'ok');
    }
    public function getApartmentById($id)
    {
        $apartments = Departament::with(["owner"])->find($id);

        if (!$apartments) {
            return $this->returnFail(400, "Apartamento no encontrado");
        }

        return $this->returnSuccess(200, $apartments);
    }

    public function assingApartment(Request $request)
    {
        //
        if ($request->user()->id == 1) {
            Departament::find($request->idApartament)->update([
                'user_id' => $request->user
            ]);
        }

        return $this->returnSuccess(200, 'ok');
    }

    public function getApartmentsByUser(Request $request)
    {
        $apartments = Departament::with(["owner"])->where("user_id", $request->user()->id)->get();

        if (!$apartments) {
            return $this->returnFail(400, "Apartamentos no encontrados");
        }

        return $this->returnSuccess(200, $apartments);
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateApartment(Request $request, $id)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        $apartment = Departament::find($id);
        if (!$apartment) return $this->returnFail(404, 'No encontrado');

        $apartment->update($request->all());
        return $this->returnSuccess(200, $apartment);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Departament $departament)
    {
        //
    }

    private function validateFieldsFromInput($inputs)
    {
        $rules = [
            'number'     => ['required', 'regex:/^[a-z 0-9 A-Z-À-ÿ .\-]+$/i'],
            'address'    => ['required', 'regex:/^[a-z 0-9 A-Z-À-ÿ .,# &]+$/i'],
            'block'      => ['regex:/^[a-z 0-9 A-Z À-ÿ .]+$/i'],
            'area'       => ['required', 'numeric'],
            'floor'      => ['required', 'numeric'],
            'description' =>  ['nullable','regex:/^[a-z a-z 0-9 A-Z-À-ÿ ., \-]+$/i'],
            'participation_percentage' => ['required', 'numeric'],
        ];
        $messages = [
            'number.required'   => 'El número de apartamento es requerido.',
            'number.regex'      => 'número de apartamento no valido',
            'address.required'  => 'Dirección es requerida.',
            'address.regex'     => 'Dirección no valida',
            'block.regex'       => 'Número de bloque no valido',
            'area.required'     => 'Area es requerida',
            'area.numeric'      => 'Area no valida',
            'floor.required'    => 'Número de piso es requerida',
            'floor.numeric'     => 'Número de piso no valido',
            'description.regex' => 'Nota no valida',
            'participation_percentage.required' => 'Porcentaje de participacion es requerido',
            'participation_percentage.numeric' => 'Porcentaje de participacion no valido',

        ];
         $validator = Validator::make($inputs, $rules, $messages)->errors();

        return $validator->all() ;
    }
}
