<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
	/* Obligatory variables for Eloquent Model subclasses */
    protected $table = 'data_kartu';
    public $primaryKey = 'id';
    public $timestamps = false;
}