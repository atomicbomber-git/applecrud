<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
	/* Obligatory variables for Eloquent Model subclasses */
    protected $table = 'property';
    public $timestamps = false;

    public function delete()
    {
        /* Delete stored image */
        unlink("./public/images/property/$this->image");

        return parent::delete();
    }

    public function getTahunAttribute($year)
    {
        if ($year === 0) { return "-"; }
        return $year;
    }

    public function getJumlahAttribute ($value)
    {
        if ($value === 0) { return "-"; }
        return $value;
    }

    protected $fillable = [
        "ruangan_id",
        "nama",
        "model",
        "tahun",
        "kode",
        "jumlah",
        "keadaan",
        "keterangan",
        "image"
    ];
}