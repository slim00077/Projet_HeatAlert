<?php

namespace Database\Seeders;

use App\Models\Advice;
use App\Models\AdviceCategory;
use Illuminate\Database\Seeder;

class AdviceSeeder extends Seeder
{
    public function run(): void
    {
        $adviceByCategory = [
            'Hydration' => [
                ['Drink water regularly', 'Drink water regularly throughout the day, even before you feel thirsty. Keep a bottle nearby and offer water to children, older adults and anyone who needs help staying hydrated.'],
                ['Choose water during hot weather', 'Water is a good everyday choice in hot weather. Follow advice from your healthcare professional if you have a condition that limits how much fluid you should drink.'],
            ],
            'Energy' => [
                ['Reduce electricity use during peak heat', 'Close curtains on sun-facing windows and postpone heat-producing tasks such as cooking or ironing until the cooler part of the day.'],
                ['Use electrical equipment safely', 'Avoid overloading sockets and extension leads. If a power cut affects medical equipment, use your planned backup arrangements and contact your care provider or local emergency service.'],
            ],
            'Equipment' => [
                ['Prepare a heatwave kit', 'Keep drinking water, a charged phone, necessary medicines, a torch and important contact details together so they are easy to reach.'],
                ['Keep your phone and power bank charged', 'Charge essential devices before very hot weather arrives. Use chargers in a dry, ventilated place and disconnect equipment if it becomes unusually hot.'],
            ],
            'Cooling and sun protection' => [
                ['Keep indoor spaces cooler', 'Shade sun-facing windows during the day. Open windows when outdoor air is cooler than the room and close them again as temperatures rise.'],
                ['Limit direct sun exposure', 'When outdoors, choose shade, wear light loose clothing and use a hat and sunscreen. Take regular breaks in a cool place.'],
            ],
            'Health and wellbeing' => [
                ['Check on people at higher risk', 'Contact older neighbours, people living alone and anyone with health needs during hot weather. Help them reach a cool place and water if needed.'],
                ['Know when to seek urgent help', 'Confusion, fainting or a very high temperature can be signs of a medical emergency. Call your local emergency number and follow the operator’s instructions.'],
            ],
        ];

        foreach ($adviceByCategory as $categoryName => $items) {
            $category = AdviceCategory::firstOrCreate(
                ['name' => $categoryName],
                ['description' => "Practical guidance about {$categoryName}."],
            );

            foreach ($items as [$title, $content]) {
                Advice::firstOrCreate(
                    ['advice_category_id' => $category->id, 'title' => $title],
                    ['summary' => $content, 'content' => $content],
                );
            }
        }
    }
}
