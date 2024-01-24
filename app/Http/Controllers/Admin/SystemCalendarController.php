<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SystemCalendarController extends Controller
{
    public $sources = [
        [
            'model'      => '\\App\\Event',
            'date_field' => 'start_time',
            'end_field'  => 'end_time',
            'field'      => 'name',
            'prefix'     => '',
            'suffix'     => '',
            'route'      => 'admin.events.edit',
        ],
    ];
    private function isHoliday($date)
    {
        $tunisianPublicHolidays = [
            '2024-01-01' ,//"Lundi 1er janvier 2024 : Nouvel An (durant les vacances scolaires d’hiver)",
            '2023-12-17' ,//"Dimanche 17 décembre 2023 : Jour anniversaire de la Révolution tunisienne",
            '2024-03-20' ,//"Mercredi 20 mars 2024 : Fête de l’Indépendance de la Tunisie",
            '2024-04-09' ,//"Mardi 9 avril 2024 : Jour des Martyrs",
            '2024-05-01' ,//"Mercredi 1er mai 2024 : Fête du Travail",
            '2024-04-09' ,//"Mardi 9 Avril 2024 : Congés Aïd El Fitr (2 jours)",
            '2023-07-25' ,//"Mardi 25 juillet 2023 : Fête de la République",
            '2023-08-13' ,//"Dimanche 13 août 2023 : Fête de la femme",
            '2024-06-16' ,//"Dimanche 16 Juin 2024 : Aïd El Idha (2 jours)",
            '2024-07-07' ,//"Dimanche 7 Juillet 2024: Jour de l’An Hégire 1441 (Ras El Am El Hijri)",
            '2023-10-15' ,//"Dimanche 15 octobre 2023 : Fête de l’évacuation",
            '2023-09-27' ,//"Mercredi 27 septembre 2023 : Anniversaire du prophète Mohamed (Mouled-Mawlid)",
        ];

        $carbonDate = Carbon::parse($date);

        // Check if it's Sunday or a holiday
        return in_array(Carbon::parse($date)->format('Y-m-d'), $tunisianPublicHolidays);
    }

    public function index()
    {

        $events = [];
        $userEmail = Auth::user()->email; // Assuming you are using Laravel's authentication

        foreach ($this->sources as $source) {
            foreach ($source['model']::all() as $model) {
                $crudFieldValue = $model->getOriginal($source['date_field']);

                if (!$crudFieldValue) {
                    continue;
                }

                if ($model->states === 0 && $model->user_email !== $userEmail) {
                    continue;
                }
                $isHoliday = $this->isHoliday($crudFieldValue); // Check if the event date is a holiday
                if ($isHoliday) {
                    continue;
                }
                $events[] = [
                    'title' => trim($source['prefix'] . " " . $model->{$source['field']}
                        . " " . $source['suffix']),
                    'start' => $crudFieldValue,
                    'end'   => $model->{$source['end_field']},
                    'url'   => route($source['route'], $model->id),
                    'state' => $model->states,
                    'is_holiday' => $isHoliday, // Add a flag for holiday events
                ];
            }
        }

        return view('admin.calendar.calendar', compact('events'));
    }
}
