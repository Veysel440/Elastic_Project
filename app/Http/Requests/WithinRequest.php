<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WithinRequest extends FormRequest {
    public function rules(): array {
        return [
            'min_lat' => ['required','numeric','between:-90,90'],
            'min_lon' => ['required','numeric','between:-180,180'],
            'max_lat' => ['required','numeric','between:-90,90'],
            'max_lon' => ['required','numeric','between:-180,180'],
            'limit'   => ['sometimes','integer','min:1','max:500'],
        ];
    }
}
