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

                $events[] = [
                    'title' => trim($source['prefix'] . " " . $model->{$source['field']}
                        . " " . $source['suffix']),
                    'start' => $crudFieldValue,
                    'end'   => $model->{$source['end_field']},
                    'url'   => route($source['route'], $model->id),
                    'state' => $model->states,
                ];
            }
        }

        return view('admin.calendar.calendar', compact('events'));
    }
}
