<?php

namespace App\Support;

trait PromoterValidationMessages
{
    protected function messages(): array
    {
        return [
            'required' => 'Complete el campo :attribute.',
            'not_regex' => 'Ingrese únicamente el nombre del establecimiento; el departamento se muestra por separado.',
            'string' => 'Ingrese un valor válido para :attribute.',
            'email' => 'Ingrese un correo electrónico válido.',
            'integer' => 'Ingrese un número entero en :attribute.',
            'max.string' => 'Utilice como máximo :max caracteres en :attribute.',
            'min.string' => 'Utilice al menos :min caracteres en :attribute.',
            'between.numeric' => 'Ingrese un valor entre :min y :max en :attribute.',
            'min.numeric' => 'Seleccione un establecimiento válido.',
            'in' => 'Seleccione una opción válida para :attribute.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre', 'client_type' => 'tipo de establecimiento',
            'category' => 'categoría', 'neighborhood' => 'barrio / zona',
            'address' => 'dirección', 'city' => 'municipio', 'department' => 'departamento',
            'frequency' => 'frecuencia', 'dependents' => 'dependientes', 'shifts' => 'turnos',
            'phone' => 'teléfono', 'email' => 'correo',
            'altaName' => 'nombre', 'altaClientType' => 'tipo de establecimiento',
            'altaCategory' => 'categoría', 'altaNeighborhood' => 'barrio / zona',
            'altaAddress' => 'dirección', 'altaCity' => 'municipio',
            'altaDepartment' => 'departamento', 'altaPhone' => 'teléfono', 'altaEmail' => 'correo',
            'reason' => 'motivo', 'altaReason' => 'motivo', 'pharmacyId' => 'establecimiento', 'bajaReason' => 'motivo',
        ];
    }
}
