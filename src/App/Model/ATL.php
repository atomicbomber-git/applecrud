<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ATL extends Model
{
	/* Obligatory variables for Eloquent Model subclasses */
    protected $table = 'aset_tetap_lainnya';
    public $primaryKey = 'no';
    public $timestamps = false;

    public function getTahunAttribute ($value)
    {
        if ($value === 0) return "-";
        return $value;
    }

    public function getJumlahAttribute ($value)
    {
        if ($value === 0) { return "-"; }
        return $value;
    }
}