<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Land extends Model
{
	/* Obligatory variables for Eloquent Model subclasses */
    protected $table = 'tanah';
    public $primaryKey = 'no';
    public $timestamps = false;

    public function getTahunAttribute($year)
    {
        if ($year === 0) { return "-"; }
        return $year;
    }

    public function getLuasAttribute($value)
    {
        if ($value === 0.0) { return "-"; }
        return number_format($value, 2, ",",  "." );
    }

    public function getHargaAttribute($value)
    {
        return number_format($value, 2, ",",  "." );
    }
}