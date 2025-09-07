<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class HeatRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'z' => ['required','integer','min:1','max:29'],
        ];
    }
}
