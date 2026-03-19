<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComunArea extends Model
{
    //
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
        "icon"
    ];
    public $appends  =   ['pay_label', 'format_rules'];

    public function getPayLabelAttribute(){
        return $this->price == 0 && $this->warranty_price == 0 ? 'Gratis' : 'Pago';
    }
    public function getFormatRulesAttribute(){
        return nl2br(htmlspecialchars($this->rules));
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
    


}
