<?php

namespace App\Services\Helper;

use App\Models\Config\SequenceFormat;
use App\Repositories\Config\SequenceRepository;
use Illuminate\Support\Facades\Validator;

class SequenceService
{
    protected $sequences;

    public function __construct(SequenceRepository $sequences)
    {
        $this->sequences = $sequences;
    }

    public function get($prefix, $date, $entity = '')
    {
        $data = [
            'prefix'   => $prefix,
            'date'     => $date,
            'entity'   => $entity
        ];
        $validation = Validator::make($data, [
            'prefix'    => 'required|max:20',
            'date'      => 'required|date',
            'entity'    => 'nullable',
        ]);

        if ($validation->fails()) {
            return ["success" => false, "message" => $validation->messages()];
        }

        $validated_data = $validation->validated();

        $format = $this->get_format($validated_data['prefix']);
        if (!empty($format['success']) && $format['success'] == false) {
            return $format;
        }
        if(!empty($format->monthly_reset))
        {
            $month = date("m", strtotime($validated_data['date']));
            $year = date("Y", strtotime($validated_data['date']));
        }
        else
        {
            $month = 0;
            $year = 0;
        }

        $seq_data = $this->sequences->findCounter(
            $format->sequence_name,
            $year,
            $month,
            $validated_data['entity']
        );

        if (is_null($seq_data)) {
            $insert_seq = array(
                "sequence_prefix" => $format->sequence_prefix,
                "entitycode" => $validated_data['entity'],
                "sequence_name" => $format->sequence_name,
                "year" => $year,
                "month" => $month,
                "sequence_length" => $format->sequence_length,
                "sequence_number" => 0,
                "increment" => 1,
                "min_value" => 1,
                "max_value" => 99999999999,
                "cur_value" => 2,
                "cycle" => 0,
                "is_active" => '1',
            );
            $this->sequences->create($insert_seq);
            $sequence = [
                "sequence_prefix" => $format->sequence_prefix,
                "entitycode" => $validated_data['entity'],
                "year" => ($year > 0) ? $year : null,
                "month" => ($month > 0) ? $month : null,
                "number" => str_pad('1', $format->sequence_length, "0", STR_PAD_LEFT)
            ];
        } else {
            $this->sequences->advanceCounter(
                $format->sequence_name,
                $year,
                $month,
                $seq_data->cur_value + 1
            );
            $sequence = [
                "sequence_prefix" => $seq_data->sequence_prefix,
                "entitycode" => $seq_data->entitycode,
                "year" => ($year > 0) ? $year : null,
                "month" => ($month > 0) ? $month : null,
                "number" => str_pad($seq_data->cur_value, $seq_data->sequence_length, $seq_data->sequence_number, STR_PAD_LEFT)
            ];
        }

        return $this->compose($format, $sequence);
    }

    /**
     * Glue the parts together in the order the format names them.
     *
     * A format slot that names nothing in $sequence contributes nothing, which
     * is how a format with fewer than five parts stays valid.
     */
    private function compose(SequenceFormat $format, array $sequence): string
    {
        $parts = '';

        for ($i = 1; $i <= 5; $i++) {
            $slot = $format->{"sequence_format{$i}"};
            $parts .= $sequence[$slot] ?? '';
        }

        return $parts;
    }

    private function get_format($prefix_name)
    {
        $seq_format = $this->sequences->findFormat($prefix_name);

        if (empty($seq_format)) {
            return ["success" => false, "message" => "Sequence Not Found."];
        }
        return $seq_format;
    }

    public function save(array $data, SequenceFormat $sequence = null)
    {
        // No users table in this service: the caller's identity arrives with
        // the request (see app/Helpers/helpers.php).
        $user_id = acting_user_email();
        $validation = Validator::make($data, [
            'sequence_format1' => 'required',
            'sequence_format2' => 'required',
            'sequence_format3' => 'required',
            'sequence_format4' => 'required',
            'sequence_format5' => 'required',
            'sequence_prefix' => 'required',
            'sequence_length' => 'required',
        ]);

        if ($validation->fails()) {
            return ["success" => false, "message" => $validation->messages()];
        }

        $validated_data = $validation->validated();

        if (!$sequence) {
            $validated_data['activated_at'] = now();
            $validated_data['activated_by'] = $user_id;
            $validated_data['created_by'] = $user_id;
        }

        $validated_data['updated_at'] = $sequence ? now() : null;
        $validated_data['updated_by'] = $sequence ? $user_id : null;

        if ($sequence) {
            return $this->sequences->saveFormat($sequence, $validated_data);
        }

        return $sequence;
    }

    public function update(SequenceFormat $sequence, array $data)
    {
        return $this->save($data, $sequence);
    }
}
