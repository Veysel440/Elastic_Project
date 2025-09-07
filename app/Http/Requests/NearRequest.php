<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class NearRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lat'        => ['required','numeric','between:-90,90'],
            'lon'        => ['required','numeric','between:-180,180'],
            'radius_km'  => ['sometimes','numeric','min:0.1','max:100'],
            'limit'      => ['sometimes','integer','min:1','max:50'],
        ];
    }
}
