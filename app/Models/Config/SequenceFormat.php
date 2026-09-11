<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SequenceFormat extends Model
{
    use HasFactory;

    protected $table = 'mdt_sequences_format';
    protected $guarded = [];

    public static function rules($id = null)
    {
        $rules = [
            'sequence_format1' => 'required',
            'sequence_format2' => 'required',
            'sequence_format3' => 'required',
            'sequence_format4' => 'required',
            'sequence_format5' => 'required',
            'sequence_prefix' => 'required',
            'sequence_length' => 'required',
        ];
        return $rules;
    }
}
