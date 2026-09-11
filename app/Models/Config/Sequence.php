<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    use HasFactory;

    protected $table = 'mdt_sequences';
    protected $guarded = [];

    public static function rules()
    {
        $rules = [
            'prefix'    => 'required|max:20',
            'date'      => 'required|date',
            'entity'    => 'nullable',
        ];

        return $rules;
    }
}
