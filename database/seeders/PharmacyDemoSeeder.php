<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Seeder;

class PharmacyDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Este seeder es SOLO para pruebas locales; no ejecutarlo en staging.
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Seeder omitido: solo puede ejecutarse en local/testing.');
            return;
        }

        $promoter = User::query()
            ->where('role', UserRole::PROMOTER->value)
            ->where('is_active', true)
            ->first();

        if (! $promoter) {
            $this->command?->warn('Primero creá una promotora local para asignarle farmacias DEMO.');
            return;
        }

        $samples = [
            ['name' => 'DEMO - Farmacia Central', 'client_type' => 'FARMACIA INDEPENDIENTE', 'category' => 'AAA', 'neighborhood' => 'Centro', 'city' => 'MANAGUA', 'department' => 'MANAGUA', 'frequency' => 2],
            ['name' => 'DEMO - Farmacia Norte', 'client_type' => 'FARMACIA INDEPENDIENTE', 'category' => 'AA', 'neighborhood' => 'Zona Norte', 'city' => 'MANAGUA', 'department' => 'MANAGUA', 'frequency' => 1],
            ['name' => 'DEMO - Farmacia Sur', 'client_type' => 'CADENA', 'category' => 'A', 'neighborhood' => 'Zona Sur', 'city' => 'MANAGUA', 'department' => 'MANAGUA', 'frequency' => 2],
            ['name' => 'DEMO - Establecimiento Este', 'client_type' => 'MAYORISTA', 'category' => 'AAA', 'neighborhood' => 'Zona Este', 'city' => 'MANAGUA', 'department' => 'MANAGUA', 'frequency' => 4],
            ['name' => 'DEMO - Farmacia Oeste', 'client_type' => 'FARMACIA INDEPENDIENTE', 'category' => 'AA', 'neighborhood' => 'Zona Oeste', 'city' => 'MANAGUA', 'department' => 'MANAGUA', 'frequency' => 3],
        ];

        foreach ($samples as $sample) {
            $pharmacy = Pharmacy::query()->firstOrCreate(
                ['name' => $sample['name']],
                [...$sample, 'is_active' => true],
            );

            if (! $pharmacy->currentAssignment()->exists()) {
                $pharmacy->assignments()->create([
                    'promoter_id' => $promoter->id,
                    'assigned_at' => now(),
                ]);
            }
        }

        $this->command?->info('Farmacias DEMO creadas/asignadas (solo base local).');
    }
}
