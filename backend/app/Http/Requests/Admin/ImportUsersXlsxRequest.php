<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportUsersXlsxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.create');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'], // 10MB max
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Файл обязателен для загрузки',
            'file.file' => 'Загруженный файл не является файлом',
            'file.mimes' => 'Файл должен быть в формате XLSX или XLS',
            'file.max' => 'Размер файла не должен превышать 10MB',
        ];
    }
}


