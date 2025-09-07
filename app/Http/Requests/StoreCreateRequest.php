<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required','string','max:150'],
            'lat'  => ['required','numeric','between:-90,90'],
            'lon'  => ['required','numeric','between:-180,180'],
        ];
    }
}
