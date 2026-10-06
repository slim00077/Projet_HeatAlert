<?php

namespace Database\Seeders;

use App\Models\Neighborhood;
use App\Models\HeatAlert;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class HeatAlertSeeder extends Seeder
{
    public function run(): void
    {
        // Define some realistic neighborhoods
        $neighborhoods = [
            Neighborhood::create(['name' => 'Centre-Ville', 'description' => 'Quartier historique avec beaucoup de béton et peu d\'arbres. Très exposé aux îlots de chaleur.']),
            Neighborhood::create(['name' => 'Les Oliviers', 'description' => 'Zone résidentielle avec des parcs et des zones ombragées.']),
            Neighborhood::create(['name' => 'Zone Industrielle', 'description' => 'Secteur industriel avec très peu de points d\'eau et des températures souvent extrêmes.']),
            Neighborhood::create(['name' => 'Le Lac', 'description' => 'Quartier bordant le lac, généralement plus frais grâce à la brise.']),
        ];

        // Active Alert - High severity
        HeatAlert::create([
            'neighborhood_id' => $neighborhoods[0]->id, // Centre-Ville
            'title' => 'Alerte Canicule Rouge - Vigilance Absolue',
            'description' => 'Températures dépassant les 42°C l\'après-midi. Ne sortez qu\'en cas d\'absolue nécessité et hydratez-vous.',
            'severity_level' => 'high',
            'start_date' => Carbon::now()->subDays(1),
            'end_date' => Carbon::now()->addDays(3),
        ]);

        // Active Alert - Medium severity
        HeatAlert::create([
            'neighborhood_id' => $neighborhoods[2]->id, // Zone Industrielle
            'title' => 'Vague de chaleur persistante',
            'description' => 'Risque de malaise pour les travailleurs en extérieur. Les températures ressenties atteignent 38°C.',
            'severity_level' => 'medium',
            'start_date' => Carbon::now()->subHours(5),
            'end_date' => Carbon::now()->addDays(2),
        ]);

        // Future Alert - Low severity
        HeatAlert::create([
            'neighborhood_id' => $neighborhoods[1]->id, // Les Oliviers
            'title' => 'Hausse des températures prévue ce week-end',
            'description' => 'Les températures atteindront 35°C. Les parcs restent ouverts en soirée pour se rafraîchir.',
            'severity_level' => 'low',
            'start_date' => Carbon::now()->addDays(2),
            'end_date' => Carbon::now()->addDays(5),
        ]);

        // Past Alert
        HeatAlert::create([
            'neighborhood_id' => $neighborhoods[0]->id, // Centre-Ville
            'title' => 'Alerte passée: Pic de chaleur de la semaine dernière',
            'description' => 'Cet épisode est maintenant terminé. Les températures sont revenues aux normales de saison.',
            'severity_level' => 'high',
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->subDays(7),
        ]);
        
        // Active Alert with no end date
        HeatAlert::create([
            'neighborhood_id' => $neighborhoods[3]->id, // Le Lac
            'title' => 'Températures chaudes persistantes',
            'description' => 'Même près du lac, l\'air reste lourd. Attention aux activités sportives.',
            'severity_level' => 'low',
            'start_date' => Carbon::now()->subDays(2),
            'end_date' => null,
        ]);
    }
}
