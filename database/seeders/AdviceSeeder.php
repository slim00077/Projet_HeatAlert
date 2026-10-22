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
                ['Drink water regularly', 'Drink water regularly throughout the day, even before you feel thirsty. Keep a bottle nearby and offer water to children, older adults and anyone who needs help staying hydrated.', 'low'],
                ['Choose water during hot weather', 'Water is a good everyday choice in hot weather. Follow advice from your healthcare professional if you have a condition that limits how much fluid you should drink.', 'low'],
            ],
            'Energy' => [
                ['Reduce electricity use during peak heat', 'Close curtains on sun-facing windows and postpone heat-producing tasks such as cooking or ironing until the cooler part of the day.', 'medium'],
                ['Use electrical equipment safely', 'Avoid overloading sockets and extension leads. If a power cut affects medical equipment, use your planned backup arrangements and contact your care provider or local emergency service.', 'medium'],
            ],
            'Equipment' => [
                ['Prepare a heatwave kit', 'Keep drinking water, a charged phone, necessary medicines, a torch and important contact details together so they are easy to reach.', 'medium'],
                ['Keep your phone and power bank charged', 'Charge essential devices before very hot weather arrives. Use chargers in a dry, ventilated place and disconnect equipment if it becomes unusually hot.', 'medium'],
            ],
            'Cooling and sun protection' => [
                ['Keep indoor spaces cooler', 'Shade sun-facing windows during the day. Open windows when outdoor air is cooler than the room and close them again as temperatures rise.', 'low'],
                ['Limit direct sun exposure', 'When outdoors, choose shade, wear light loose clothing and use a hat and sunscreen. Take regular breaks in a cool place.', 'low'],
            ],
            'Health and wellbeing' => [
                ['Check on people at higher risk', 'Contact older neighbours, people living alone and anyone with health needs during hot weather. Help them reach a cool place and water if needed.', 'medium'],
                ['Know when to seek urgent help', 'Confusion, fainting or a very high temperature can be signs of a medical emergency. Call your local emergency number and follow the operator’s instructions.', 'high'],
            ],
        ];

        $imagesByTitle = [
            'Drink water regularly' => 'https://images.unsplash.com/photo-1582759959879-0091a6c5caf2?auto=format&fit=crop&w=1200&q=80',
            'Choose water during hot weather' => 'https://images.pexels.com/photos/8487216/pexels-photo-8487216.jpeg?auto=compress&dpr=1&h=750&w=1260',
            'Reduce electricity use during peak heat' => 'https://images.unsplash.com/photo-1768577908037-bbc3da95c1cf?auto=format&fit=crop&w=1200&q=80',
            'Use electrical equipment safely' => 'https://images.pexels.com/photos/15000981/pexels-photo-15000981/free-photo-of-photo-of-chargers-put-into-extension-electric-cord-on-a-table.jpeg?auto=compress&dpr=1&h=750&w=1260',
            'Prepare a heatwave kit' => 'https://images.pexels.com/photos/5125690/pexels-photo-5125690.jpeg?auto=compress&dpr=1&h=750&w=1260',
            'Keep your phone and power bank charged' => 'https://images.unsplash.com/photo-1585995603413-eb35b5f4a50b?ixlib=rb-4.1.0&q=85&fm=jpg',
            'Keep indoor spaces cooler' => 'https://images.pexels.com/photos/36197104/pexels-photo-36197104/free-photo-of-sunlight-filtering-through-window-blinds-indoors.jpeg?auto=compress&dpr=1&h=750&w=1260',
            'Limit direct sun exposure' => 'https://images.pexels.com/photos/8760436/pexels-photo-8760436.jpeg?auto=compress&dpr=1&h=750&w=1260',
            'Check on people at higher risk' => 'https://images.pexels.com/photos/29372727/pexels-photo-29372727/free-photo-of-elderly-caregiver-assisting-senior-woman-at-home.jpeg?auto=compress&dpr=1&h=750&w=1260',
            'Know when to seek urgent help' => 'https://images.pexels.com/photos/8413217/pexels-photo-8413217.jpeg?auto=compress&dpr=1&h=750&w=1260',
            'Protect Yourself During a Heatwave' => 'https://images.pexels.com/photos/31401648/pexels-photo-31401648/free-photo-of-woman-in-sun-hat-on-a-beach-in-summer.jpeg?auto=compress&dpr=1&h=750&w=1260',
        ];

        foreach ($adviceByCategory as $categoryName => $items) {
            $category = AdviceCategory::firstOrCreate(
                ['name' => $categoryName],
                ['description' => "Practical guidance about {$categoryName}."],
            );

            foreach ($items as [$title, $content, $riskLevel]) {
                Advice::firstOrCreate(
                    ['advice_category_id' => $category->id, 'title' => $title],
                    [
                        'summary' => $content,
                        'content' => $content,
                        'risk_level' => $riskLevel,
                        'is_approved' => true,
                        'moderation_status' => 'approved',
                    ],
                );
            }
        }

        foreach ($imagesByTitle as $title => $imagePath) {
            Advice::query()
                ->where('title', $title)
                ->whereNull('image_path')
                ->update(['image_path' => $imagePath]);
        }
    }
}
