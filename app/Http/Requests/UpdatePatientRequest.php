<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ustaw na false, jeśli potrzebujesz autoryzacji
    }

    public function rules(): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[A-ZĄĆĘŁŃÓŚŻŹ][a-ząćęłńóśżź]+(?:[-\s][A-ZĄĆĘŁŃÓŚŻŹ]?[a-ząćęłńóśżź]+)*$/u'
            ],
            'surname' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[A-ZĄĆĘŁŃÓŚŻŹ][a-ząćęłńóśżź]+(?:[-\s][A-ZĄĆĘŁŃÓŚŻŹ]?[a-ząćęłńóśżź]+)*$/u'
            ],
            'phone' => 'nullable|string|max:9|regex:/^[0-9]+$/',
            'email' => 'nullable|email|max:255|unique:users,email',

            // poprawione pola
            'wiek' => 'nullable|integer|min:1|max:120',
            'opis' => 'nullable|string|max:1000',
            'rodzaj_pacjenta' => 'nullable|in:Prywatny,Klub gimnastyki,AWF,WKS,Od Grzegorza,DK,DT',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Pole imię jest wymagane',
            'name.regex'       => 'Imię musi zaczynać się wielką literą',

            'surname.required' => 'Pole nazwisko jest wymagane',
            'surname.regex'    => 'Nazwisko musi zaczynać się wielką literą',

            'email.required'   => 'Pole e-mail jest wymagane',
            'email.email'      => 'Nieprawidłowy format e-mail',
            'email.unique'     => 'Pacjent o tym e-mailu już istnieje',

            'phone.required'   => 'Pole telefon jest wymagane',
            'phone.regex'      => 'Telefon może zawierać tylko cyfry',

            // 'wiek.required'    => 'Pole wiek jest wymagane',
                'wiek.integer'     => 'Wiek musi być liczbą',
                'wiek.min'         => 'Wiek musi być większy od zera',
                'wiek.max'         => 'Wiek nie może przekraczać 120 lat',

                'opis.max'         => 'Opis może mieć maksymalnie 1000 znaków',

            // 'rodzaj_pacjenta.required' => 'Pole rodzaj pacjenta jest wymagane',
            'rodzaj_pacjenta.in'       => 'Rodzaj pacjenta musi być jedną z dozwolonych wartości',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json(['errors' => $validator->errors()], 422);
        throw new ValidationException($validator, $response);
    }
}
