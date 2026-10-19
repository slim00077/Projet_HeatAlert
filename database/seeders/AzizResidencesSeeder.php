<?php

namespace Database\Seeders;

use App\Models\Residence;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AzizResidencesSeeder extends Seeder
{
    /**
     * Gives the resident "aziz" three residences with pictures.
     */
    public function run(): void
    {
        $aziz = User::where('name', 'aziz')->first() ?? User::factory()->create([
            'name' => 'aziz',
            'email' => 'aziz@heatalert.test',
        ]);

        $residences = [
            ['Residence Les Palmiers', '12 Avenue Habib Bourguiba', 'Centre Ville', 'Bright six-floor building with a shared rooftop terrace.'],
            ['Residence El Yasmine', '45 Rue de la Marsa', 'La Marsa', 'Seaside residence with palm trees and a quiet courtyard.'],
            ['Residence Ennasr Park', '230 Avenue Ennasr', 'Ben Arous', 'Modern complex close to public transport and green areas.'],
        ];

        foreach ($residences as $index => [$name, $address, $neighborhood, $description]) {
            $file = 'residences/aziz-'.($index + 1).'.svg';
            Storage::disk('public')->put($file, File::get(public_path('images/residence-'.($index + 1).'.svg')));

            Residence::updateOrCreate(
                ['user_id' => $aziz->id, 'name' => $name],
                [
                    'address' => $address,
                    'neighborhood' => $neighborhood,
                    'description' => $description,
                    'media_path' => $file,
                    'media_type' => 'image',
                ],
            );
        }
    }
}
