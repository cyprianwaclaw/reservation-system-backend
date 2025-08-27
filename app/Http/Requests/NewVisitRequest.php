<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class NewVisitRequest extends FormRequest
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
     * @return array
     */
    public function rules(): array
    {
        return [
            'doctor_id'   => 'required|exists:doctors,id',
            'name'        => ['required', 'string', 'max:40', 'regex:/^[A-ZŁŚĆŻŹÓĘĄ][a-ząćęłńóśżź-]*$/u'],
            'surname'     => ['required', 'string', 'max:40', 'regex:/^[A-ZŁŚĆŻŹÓĘĄ][a-ząćęłńóśżź-]*$/u'],
            'phone'       => 'nullable|regex:/^[0-9]{9}$/',
            'email'       => 'required|email|max:255',
            'date'        => 'required|date',
            'start_time'  => 'required|date_format:H:i',
            'duration'    => 'required|integer|min:1|max:480', // max 8h
            'type'        => 'required',

            // dodatkowe pola
            'wiek' => 'nullable|integer|min:1|max:120',
            'opis' => 'nullable|string|max:1000',
            // 'rodzaj_pacjenta' => 'nullable|in:Prywatny,Klub gimnastyki,AWF,WKS,Od Grzegorza,DK,DT',
        ];
    }
    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'doctor_id.required' => 'Lekarz jest wymagany.',
            'doctor_id.exists'   => 'Wybrany lekarz nie istnieje.',

            'name.required' => 'Imię jest wymagane.',
            'name.string'   => 'Imię musi być ciągiem znaków.',
            'name.max'      => 'Imię nie może przekraczać 40 znaków.',
            'name.regex'    => 'Imię musi zaczynać się wielką literą i zawierać tylko litery.',

            'surname.required' => 'Nazwisko jest wymagane.',
            'surname.string'   => 'Nazwisko musi być ciągiem znaków.',
            'surname.max'      => 'Nazwisko nie może przekraczać 40 znaków.',
            'surname.regex'    => 'Nazwisko musi zaczynać się wielką literą i zawierać tylko litery.',

            'phone.regex'  => 'Numer telefonu musi zawierać dokładnie 9 cyfr.',
            // 'phone.unique' => 'Podany numer telefonu jest już używany.',

            'email.required' => 'Adres e-mail jest wymagany.',
            'email.email'    => 'Podany adres e-mail jest nieprawidłowy.',
            'email.max'      => 'Adres e-mail nie może przekraczać 255 znaków.',
            // 'email.unique'   => 'Podany adres e-mail jest już używany.',

            'date.required' => 'Data wizyty jest wymagana.',
            'date.date'     => 'Data wizyty musi być poprawną datą.',

            'start_time.required'    => 'Godzina rozpoczęcia jest wymagana.',
            'start_time.date_format' => 'Godzina rozpoczęcia musi być w formacie HH:MM.',

            'duration.required' => 'Czas trwania wizyty jest wymagany.',
            'duration.integer'  => 'Czas trwania musi być liczbą całkowitą.',
            'duration.min'      => 'Czas trwania wizyty nie może być krótszy niż 1 minuta.',
            'duration.max'      => 'Czas trwania wizyty nie może być dłuższy niż 480 minut.',

            'type.required' => 'Rodzaj usługi jest wymagany',

            // dodatkowe pola
            'wiek.integer'     => 'Wiek musi być liczbą',
            'wiek.min'         => 'Wiek musi być większy od zera',
            'wiek.max'         => 'Wiek nie może przekraczać 120 lat',

            'opis.max'         => 'Opis może mieć maksymalnie 1000 znaków',
        ];
    }

    // /**
    //  * Prepare the data for validation.
    //  */
    // protected function prepareForValidation(): void
    // {
    //     $this->merge([
    //         'name'    => $this->name ? ucfirst(strtolower($this->name)) : null,
    //         'surname' => $this->surname ? ucfirst(strtolower($this->surname)) : null,
    //     ]);
    // }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json(['errors' => $validator->errors()], 422);
        throw new ValidationException($validator, $response);
    }
}
