<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Islamabad',
                'address' => 'Chaudhry Plaza, 1st Floor, Main PWD Road, Police Foundation, Islamabad',
                'city' => 'Islamabad',
                'phones' => ['051-2723561-63'],
                'whatsapp' => '+923334198772',
                'whatsapp_link' => 'https://wa.me/923334198772',
                'sort_order' => 1,
            ],
            [
                'name' => 'Lahore — Shalamar',
                'address' => '1st Floor, Near Angoori Cinema, Shalamar Link Road, Lahore',
                'city' => 'Lahore',
                'phones' => ['042-36831098', '042-36851619'],
                'whatsapp' => '+923009435358',
                'whatsapp_link' => 'https://wa.me/923009435358',
                'sort_order' => 2,
            ],
            [
                'name' => 'Lahore — Ferozpur Road',
                'address' => 'Opp Royal Arcade Mobile Plaza, Main Ferozpur Road, Qanchi Ammar Sadhu, Lahore',
                'city' => 'Lahore',
                'phones' => ['042-35820123'],
                'whatsapp' => '+923036880082',
                'whatsapp_link' => 'https://wa.me/923036880082',
                'sort_order' => 3,
            ],
            [
                'name' => 'Lahore — Shadbagh',
                'address' => 'New Plaza, 2nd Floor, Main Shadbagh Road, Opposite Total Petrol Pump, Shadbagh, Lahore',
                'city' => 'Lahore',
                'phones' => ['042-37281100'],
                'whatsapp' => '+923009435358',
                'whatsapp_link' => 'https://wa.me/923009435358',
                'sort_order' => 4,
            ],
            [
                'name' => 'Lahore — Cantt',
                'address' => '2-B Zarar Shaheed Road, Guldasht Town, Lahore Cantt',
                'city' => 'Lahore',
                'phones' => ['042-36630283'],
                'whatsapp' => '+923013487070',
                'whatsapp_link' => 'https://wa.me/923013487070',
                'sort_order' => 5,
            ],
            [
                'name' => 'Jaranwala',
                'address' => 'West Canal Road, Near Haider Garden, Jaranwala',
                'city' => 'Jaranwala',
                'phones' => ['041-4311455'],
                'whatsapp' => '+923290854803',
                'whatsapp_link' => 'https://wa.me/923290854803',
                'sort_order' => 6,
            ],
        ];

        foreach ($branches as $branch) {
            Branch::firstOrCreate(
                ['name' => $branch['name']],
                array_merge($branch, ['is_active' => true]),
            );
        }
    }
}
