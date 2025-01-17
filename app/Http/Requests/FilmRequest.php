<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilmRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'              => 'required|string',
            'origin_name'       => 'required|string',
            'slug'              => 'required|string',
            'server'            => 'required|string',
            'description'       => 'required|string',
            'quality'           => 'required|string',
            'trailer_url'       => 'required|string',
            'time'              => 'required|string',
            'episode_current'   => 'required|numeric',
            'episode_total'     => 'required|numeric',
            'year'              => 'required|numeric',
            'type_id'           => 'required|numeric',
            'status_id'         => 'required|numeric',
        ];
    }
}
