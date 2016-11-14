<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
	/* Obligatory variables for Eloquent Model subclasses */
    protected $table = 'gedung_dan_bangunan';
    public $primaryKey = 'no';
    public $timestamps = false;

    public function getBetonAttribute ($value)
    {
        if ($value === 0) {
            return "Tidak";
        }
        else if ($value === 1) {
            return "Beton";
        }
        else if ($value === 2) {
            return "-";
        }
    }

    public function getTingkatAttribute ($value)
    {
        if ($value === 0) {
            return "Tidak";
        }
        else if ($value === 1) {
            return "Tingkat";
        }
        else if ($value === 2) {
            return "-";
        }
    }

    public function getLuasAttribute ($value) {
        if ($value === 0.0) { return "-"; }
        return $value;
    }

    public function getLuasLantaiAttribute ($value) {
        if ($value === 0.0) { return "-"; }
        return $value;
    }
}