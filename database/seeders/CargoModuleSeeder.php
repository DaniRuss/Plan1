<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LogisticsCompany;

class CargoModuleSeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            ['name' => 'ESLSE (Ethiopian Shipping & Logistics)', 'contact_person' => 'Abebe Bikila', 'email' => 'contact@eslse.et'],
            ['name' => 'Djibouti Ports & Free Zones Authority', 'contact_person' => 'Omar Guelleh', 'email' => 'info@dpfza.dj'],
            ['name' => 'PACC Global Logistics', 'contact_person' => 'Sarah Chen', 'email' => 'operations@pacc.com'],
            ['name' => 'Bolloré Transport & Logistics', 'contact_person' => 'Jean Dupont', 'email' => 'africa.ops@bollore.com'],
        ];

        foreach ($companies as $company) {
            LogisticsCompany::firstOrCreate(['name' => $company['name']], $company);
        }
    }
}
