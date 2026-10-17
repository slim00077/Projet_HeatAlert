<?php

namespace Database\Seeders;

use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Database\Seeder;

class HeatAlertSeeder extends Seeder
{
    /**
     * Safe to run several times: rows are matched on neighborhood name / alert title.
     * Names match the neighborhoods typed on the demo residences so residents get notified.
     */
    public function run(): void
    {
        // Older seeds used a hyphenated name that did not match the residences.
        Neighborhood::where('name', 'Centre-Ville')->update(['name' => 'Centre Ville']);

        $neighborhoods = collect([
            ['Centre Ville', 'Quartier historique avec beaucoup de béton et peu d\'arbres. Très exposé aux îlots de chaleur.', 36.8002, 10.1858, 48000, 11500],
            ['La Marsa', 'Quartier côtier avec une population âgée importante.', 36.8782, 10.3247, 92000, 21000],
            ['Ariana', 'Zone résidentielle dense avec peu d\'espaces ombragés.', 36.8665, 10.1647, 114000, 19000],
            ['Ben Arous', 'Grand ensemble résidentiel et industriel.', 36.7531, 10.2189, 88000, 14000],
            ['Les Oliviers', 'Zone résidentielle avec des parcs et des zones ombragées.', 36.8420, 10.2150, 21000, 3600],
            ['Zone Industrielle', 'Secteur industriel avec très peu de points d\'eau et des températures souvent extrêmes.', 36.7700, 10.2500, 15000, 1200],
            ['Le Lac', 'Quartier bordant le lac, généralement plus frais grâce à la brise.', 36.8325, 10.2290, 30000, 4200],
        ])->mapWithKeys(fn (array $data) => [
            $data[0] => Neighborhood::updateOrCreate(
                ['name' => $data[0]],
                [
                    'description' => $data[1],
                    'latitude' => $data[2],
                    'longitude' => $data[3],
                    'population' => $data[4],
                    'vulnerable_population' => $data[5],
                ],
            ),
        ]);

        $alerts = [
            ['Centre Ville', 'Alerte Canicule Rouge - Vigilance Absolue', 'Températures dépassant les 42°C l\'après-midi. Ne sortez qu\'en cas d\'absolue nécessité et hydratez-vous.', 'high', 42.0, now()->subDay(), now()->addDays(3)],
            ['Zone Industrielle', 'Vague de chaleur persistante', 'Risque de malaise pour les travailleurs en extérieur. Les températures ressenties atteignent 38°C.', 'medium', 38.0, now()->subHours(5), now()->addDays(2)],
            ['Centre Ville', 'Alerte passée: Pic de chaleur de la semaine dernière', 'Cet épisode est maintenant terminé. Les températures sont revenues aux normales de saison.', 'high', 41.0, now()->subDays(10), now()->subDays(7)],
            ['Le Lac', 'Températures chaudes persistantes', 'Même près du lac, l\'air reste lourd. Attention aux activités sportives.', 'low', 33.0, now()->subDays(2), null],
            ['La Marsa', 'Chaleur intense sur le littoral', 'Forte chaleur humide : surveillez les personnes âgées de votre entourage.', 'medium', 37.0, now()->subHours(2), now()->addDays(2)],
            ['Ben Arous', 'Canicule annoncée ce week-end', 'Un pic de chaleur est attendu à partir de demain. Préparez-vous.', 'high', 41.5, now()->addDay(), now()->addDays(4)],
            ['Ariana', 'Épisode de chaleur du mois dernier', 'Épisode terminé.', 'medium', 36.0, now()->subDays(35), now()->subDays(32)],
        ];

        foreach ($alerts as [$neighborhood, $title, $description, $severity, $temperature, $start, $end]) {
            HeatAlert::firstOrCreate(
                ['neighborhood_id' => $neighborhoods[$neighborhood]->id, 'title' => $title],
                [
                    'description' => $description,
                    'severity_level' => $severity,
                    'temperature' => $temperature,
                    'start_date' => $start,
                    'end_date' => $end,
                ],
            );
        }
    }
}
