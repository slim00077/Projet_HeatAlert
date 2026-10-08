<?php

namespace Database\Seeders;

use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HeatAlertSeeder extends Seeder
{
    /**
     * Safe to run several times: existing neighborhoods and alerts are reused.
     */
    public function run(): void
    {
        $neighborhoods = collect([
            'Centre-Ville' => 'Quartier historique avec beaucoup de béton et peu d\'arbres. Très exposé aux îlots de chaleur.',
            'Les Oliviers' => 'Zone résidentielle avec des parcs et des zones ombragées.',
            'Zone Industrielle' => 'Secteur industriel avec très peu de points d\'eau et des températures souvent extrêmes.',
            'Le Lac' => 'Quartier bordant le lac, généralement plus frais grâce à la brise.',
            'La Marsa' => 'Quartier balnéaire où vivent de nombreuses personnes âgées.',
        ])->map(fn (string $description, string $name) => Neighborhood::firstOrCreate(['name' => $name], ['description' => $description]));

        $alerts = [
            ['Centre-Ville', 'Alerte Canicule Rouge - Vigilance Absolue', 'Residence Les Palmiers', 'Températures dépassant les 42°C l\'après-midi. Ne sortez qu\'en cas d\'absolue nécessité et hydratez-vous.', 'high', Carbon::now()->subDays(1), Carbon::now()->addDays(3)],
            ['Zone Industrielle', 'Vague de chaleur persistante', null, 'Risque de malaise pour les travailleurs en extérieur. Les températures ressenties atteignent 38°C.', 'medium', Carbon::now()->subHours(5), Carbon::now()->addDays(2)],
            ['La Marsa', 'Chaleur intense en bord de mer', 'Residence El Yasmine', 'Humidité élevée et 39°C ressentis. Vérifiez régulièrement l\'état des personnes âgées.', 'high', Carbon::now()->subHours(8), Carbon::now()->addDays(2)],
            ['Les Oliviers', 'Hausse des températures prévue ce week-end', null, 'Les températures atteindront 35°C. Les parcs restent ouverts en soirée pour se rafraîchir.', 'low', Carbon::now()->addDays(2), Carbon::now()->addDays(5)],
            ['Centre-Ville', 'Alerte passée: Pic de chaleur de la semaine dernière', null, 'Cet épisode est maintenant terminé. Les températures sont revenues aux normales de saison.', 'high', Carbon::now()->subDays(10), Carbon::now()->subDays(7)],
            ['Le Lac', 'Températures chaudes persistantes', null, 'Même près du lac, l\'air reste lourd. Attention aux activités sportives.', 'low', Carbon::now()->subDays(2), null],
        ];

        foreach ($alerts as [$neighborhood, $title, $residenceName, $description, $severity, $start, $end]) {
            HeatAlert::firstOrCreate(
                ['title' => $title],
                [
                    'neighborhood_id' => $neighborhoods[$neighborhood]->id,
                    'residence_name' => $residenceName,
                    'description' => $description,
                    'severity_level' => $severity,
                    'start_date' => $start,
                    'end_date' => $end,
                ],
            );
        }
    }
}
