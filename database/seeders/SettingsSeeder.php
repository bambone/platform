<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'contacts.phone' => '+7 (913) 060-86-89',
            'contacts.phone_alt' => '',
            'contacts.whatsapp' => '79130608689',
            'contacts.telegram' => 'motolevins',
            'contacts.email' => '',
            'contacts.address' => '',
            'contacts.hours' => '',
        ];

        foreach ($defaults as $key => $value) {
            Setting::set($key, $value);
        }
    }
}
