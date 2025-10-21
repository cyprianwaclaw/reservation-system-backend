<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-ZĄĆĘŁŃÓŚŻŹ][a-ząćęłńóśżź]+(?:[-\s][A-ZĄĆĘŁŃÓŚŻŹ]?[a-ząćęłńóśżź]+)*$/u'
            ],
            'surname' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-ZĄĆĘŁŃÓŚŻŹ][a-ząćęłńóśżź]+([-\s][A-ZĄĆĘŁŃÓŚŻŹ][a-ząćęłńóśżź]+)*$/u'
            ],
            'phone' => ['nullable', 'regex:/^\d{9}$/', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],

            'wiek' => 'nullable|integer|min:1|max:120',
            'opis' => 'nullable|string|max:1000',
            'rodzaj_pacjenta' => 'nullable|in:Prywatny,Klub gimnastyki,AWF,WKS,Od Grzegorza,Od Asi,DK,DT',

            'city_code' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^[0-9]{2}-[0-9]{3}$/'
            ],
            'city' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[A-ZĄĆĘŁŃÓŚŻŹ][a-ząćęłńóśżź]+(?:[-\s][A-ZĄĆĘŁŃÓŚŻŹ]?[a-ząćęłńóśżź]+)*$/u'
            ],
            'street' => ['nullable', 'string', 'max:255'],
            'pesel' => ['nullable', 'digits:11', 'regex:/^[0-9]{11}$/']
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->phone) {
            $this->merge([
                'phone' => preg_replace('/\D/', '', $this->phone),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Pole imię jest wymagane',
            'name.regex'       => 'Imię musi zaczynać się wielką literą',
            'surname.required' => 'Pole nazwisko jest wymagane',
            'surname.regex'    => 'Nazwisko musi zaczynać się wielką literą',

            'phone.regex'      => 'Telefon musi zawierać dokładnie 9 cyfr',
            'phone.unique'     => 'Podany numer telefonu jest już używany',

            'email.email'      => 'Nieprawidłowy format e-mail',
            'email.unique'     => 'Podany adres e-mail jest już używany',

            'wiek.integer'     => 'Wiek musi być liczbą',
            'wiek.min'         => 'Wiek musi być większy od zera',
            'wiek.max'         => 'Wiek nie może przekraczać 120 lat',

            'opis.max'         => 'Opis może mieć maksymalnie 1000 znaków',
            'rodzaj_pacjenta.in' => 'Rodzaj pacjenta musi być jedną z dozwolonych wartości',

            'city_code.regex'  => 'Kod pocztowy musi być w formacie 00-000',
            'city.regex'       => 'Miasto musi zaczynać się wielką literą',
            'pesel.digits'     => 'PESEL musi składać się z 11 cyfr',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $response = response()->json(['errors' => $validator->errors()], 422);
        throw new ValidationException($validator, $response);
    }
}
