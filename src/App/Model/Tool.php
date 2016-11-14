<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
	/* Obligatory variables for Eloquent Model subclasses */
    protected $table = 'peralatan_dan_mesin';
    public $primaryKey = 'no';
    public $timestamps = false;

    public function getTahunAttribute ($value)
    {

        if ($value === 0) return "-";
        return $value;

    }
}