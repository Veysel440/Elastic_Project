<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSetAreaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'coordinates'          => ['required','array','min:4'],
            'coordinates.*'        => ['required','array','size:2'],
            'coordinates.*.0'      => ['required','numeric','between:-180,180'], // lon
            'coordinates.*.1'      => ['required','numeric','between:-90,90'],   // lat
        ];
    }
}
