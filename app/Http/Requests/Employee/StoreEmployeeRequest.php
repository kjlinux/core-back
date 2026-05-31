<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'site_id' => ['required', 'exists:sites,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string'],
            'position' => ['required', 'string'],
            // employee_number n'est PAS accepte : le matricule est toujours genere
            // cote serveur (MatriculeGenerator) pour garantir l'unicite par entreprise.
            'hire_date' => ['required', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'string'],
            'payment_mode' => ['nullable', 'string', 'in:monthly,hourly,daily,weekly,forfait'],
            // base_salary obligatoire dès qu'un mode de rémunération est choisi
            // (évite une fiche de paie à 0 silencieuse) et strictement positif.
            'base_salary' => ['nullable', 'integer', 'min:1', 'required_with:payment_mode'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre compte.',
        ];
    }
}
