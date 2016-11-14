<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Jenssegers\Date\Date;

class JIJ extends Model
{
	/* Obligatory variables for Eloquent Model subclasses */
    protected $table = 'jalan_irigasi_jaringan';
    public $primaryKey = 'no';
    public $timestamps = false;

    public function getTanggalAttribute ($value)
    {

        if ($value === '0000-00-00') {
            return '-';
        }
        
        $date = new Date($value);
        return $date->format('d/m/Y');
    
    }

    public function getPanjangAttribute ($value)
    {
        if ($value === 0.0) { return "-"; }
        return number_format($value, 2, ",",  "." );
    }

    public function getLebarAttribute ($value)
    {
        if ($value === 0.0) { return "-"; }
        return number_format($value, 2, ",",  "." );
    }

    public function getLuasAttribute ($value)
    {
        if ($value === 0.0) { return "-"; }
        return number_format($value, 2, ",",  "." );
    }

}