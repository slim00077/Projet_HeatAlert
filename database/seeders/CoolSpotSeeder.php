<?php

namespace Database\Seeders;

use App\Models\CoolSpot;
use App\Models\CoolSpotType;
use Illuminate\Database\Seeder;

class CoolSpotSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Parc'              => 'Espaces verts ombragés',
            'Bibliothèque'      => 'Lieux publics climatisés et calmes',
            'Centre commercial' => 'Grands espaces climatisés',
            'Mosquée'           => 'Lieux frais et ouverts au public',
            'Plage'             => 'Espaces au bord de la mer',
        ];

        $typeIds = [];
        foreach ($types as $name => $description) {
            $typeIds[$name] = CoolSpotType::updateOrCreate(
                ['name' => $name],
                ['description' => $description]
            )->id;
        }

        $spots = [
            ['Parc',              'Parc Belvédère',              'Avenue Mohamed V, Tunis',    '08:00 - 19:00', true, 36.8147, 10.1738],
            ['Parc',              'Parc Ennahli',                'Ennahli, Ariana',            '07:00 - 20:00', true, 36.8970, 10.1715],
            ['Bibliothèque',      'Bibliothèque Nationale',      'Boulevard 9 Avril, Tunis',   '09:00 - 17:00', true, 36.7968, 10.1752],
            ['Bibliothèque',      'Bibliothèque Municipale',     'Centre-ville, Tunis',        '08:30 - 16:30', false, 36.8002, 10.1810],
            ['Centre commercial', 'Tunis City',                  'Les Berges du Lac, Tunis',   '10:00 - 21:00', true, 36.8985, 10.1884],
            ['Centre commercial', 'Carrefour La Marsa',          'La Marsa, Tunis',            '09:00 - 21:00', true, 36.8806, 10.3242],
            ['Mosquée',           'Mosquée Zitouna',             'Médina de Tunis',            '06:00 - 21:00', false, 36.7986, 10.1719],
            ['Plage',             'Plage de La Marsa',           'La Marsa, Tunis',            '06:00 - 20:00', true, 36.8898, 10.3370],
        ];

        foreach ($spots as [$type, $name, $address, $hours, $accessible, $latitude, $longitude]) {
            CoolSpot::updateOrCreate(
                ['name' => $name],
                [
                    'cool_spot_type_id' => $typeIds[$type],
<<<<<<< Updated upstream
                    'address'           => $address,
                    'opening_hours'     => $hours,
                    'is_accessible'     => $accessible,
                    'is_active'         => true,
                    'description'       => "Point de fraîcheur : {$name}.",
=======
                    'address' => $address,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'opening_hours' => $hours,
                    'is_accessible' => $accessible,
                    'is_active' => true,
                    'description' => "Point de fraîcheur : {$name}.",
>>>>>>> Stashed changes
                ]
            );
        }
    }
}