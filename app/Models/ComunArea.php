<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComunArea extends Model
{
    use SoftDeletes;
    
    protected $table = "comun_areas";

    protected $fillable = [
        "name",
        "capacity",
        "price",
        "warranty_price",
        "description",
        "max_time_reserve",
        "timeFrom",
        "timeTo",
        "rules",
        "icon",
        "max_cupo",
        "not_available_days",
        "type" // <-- IMPORTANTE: Asegúrate de agregar "type" al fillable para poder guardarlo
    ];

    // Agregamos 'type_label' a la lista de appends
    public $appends = ['pay_label', 'format_rules', 'type_label', 'type_label_large', 'type_color'];

    public function getPayLabelAttribute(){
        return $this->price == 0 && $this->warranty_price == 0 ? 'Gratis' : 'Pago';
    }

    public function getFormatRulesAttribute(){
        return nl2br(htmlspecialchars($this->rules));
    }

    // NUEVO ACCESOR: Crea el atributo "type_label" al vuelo
    public function getTypeLabelAttribute(){
        // Usamos la expresión match (disponible en PHP 8+) para mapear el número al texto
        return match((int) $this->type) {
            1 => 'Gratis',
            2 => 'Mixto',
            3 => 'De pago',
            4 => 'De pago lista de invitados',
            default => 'No definido',
        };
    }
    public function getTypeLabelLargeAttribute(){
        // Usamos la expresión match (disponible en PHP 8+) para mapear el número al texto
        return match((int) $this->type) {
            1 => 'Uso compartido',
            2 => 'Uso mixto (Compartido y Exclusivo)',
            3 => 'Uso exclusivo',
            4 => 'De pago lista de invitados',
            default => 'No definido',
        };
    }
    public function getTypeColorAttribute(){
        // Usamos la expresión match (disponible en PHP 8+) para mapear el número al texto
        return match((int) $this->type) {
            1 => 'blue-9',
            2 => 'deep-purple-10',
            3 => 'light-green-13',
            4 => 'De pago lista de invitados',
            default => 'No definido',
        };
    }

    public function bookings(){
        return $this->hasMany(Booking::class, "comun_area_id");
    }

    public function bookingsToValidate(){
        return $this->hasMany(Booking::class, "comun_area_id")->where('status', 2);
    }

    public function rulesArea()
    {
        return $this->hasMany(Rule::class, "comun_area_id");
    }
    
    public function schedules()
    {
        return $this->hasMany(ComunAreaSchedule::class, 'comun_area_id');
    }
}