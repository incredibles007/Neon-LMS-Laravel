<?php

use Illuminate\Database\Seeder;

class LocaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $locales = [
            [
                'name' => 'English',
                'short_name' => 'en',
                'display_type' => 'ltr',
                'is_default' => 1,
            ],

            [
                'name' => 'Spanish',
                'short_name' => 'es',
                'display_type' => 'ltr',
                'is_default' => 0,

            ],

            [
                'name' => 'French',
                'short_name' => 'fr',
                'display_type' => 'ltr',
                'is_default' => 0,

            ],
            [
                'name' => 'Arabic',
                'short_name' => 'ar',
                'display_type' => 'rtl',
                'is_default' => 0,

            ],
        ];

        foreach ($locales as $item) {
            \App\Locale::firstOrCreate([
                'name' => $item['name'],
                'short_name' => $item['short_name'],
                'display_type' => $item['display_type'],
                'is_default' => $item['is_default']]);
        }



    }
}
