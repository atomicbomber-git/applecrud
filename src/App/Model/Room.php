<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use App\Model\Card;

class Room extends Model
{
	/* Obligatory variables for Eloquent Model subclasses */
    protected $table = 'ruangan';
    public $primaryKey = 'id';
    public $timestamps = false;

    /* A Room can have many Properties */
    public function properties()
    {
        return $this->hasMany("App\Model\Property", "ruangan_id");
    }

    public function delete() {

        foreach ($this->properties as $property) {
            $property->delete();
        }
        
        Card::find($this->card_id)->delete();

        return parent::delete();
    }

}