<?php

namespace App\Http\Controllers\Admin;

use App\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyEventRequest;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use Carbon\Carbon;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;

class EventsController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('event_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $events = Event::withCount('events')
            ->get();

        return view('admin.events.index', compact('events'));
    }

    public function create()
    {
        abort_if(Gate::denies('event_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.events.create');
    }

    public function store(StoreEventRequest $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'end_time' => ['required', 'after:start_time'],
            'recurrence' => ['required', 'in:none,daily,weekly,monthly'],

        ]);

        // Check validation result
        if ($validator->fails()) {
            return redirect()->route('admin.events.create')
                ->withErrors($validator)
                ->withInput();
        }

        // Check salle and date availability
        if (!$this->isSalleAvailable($request->start_time, $request->end_time, $request->salle)) {
            return redirect()->route('admin.events.create')
                ->withErrors(['salle' => 'Salle is not available during this time.'])
                ->withInput();
        }
// Get selected recurrence
        $recurrence = $request->input('recurrence');

        // Check if the selected date is a holiday
        $selectedDate = $request->input('start_time');
        if ($recurrence !== 'none' && $this->isHoliday($selectedDate)) {
            return redirect()->route('admin.events.create')
                ->withErrors(['start_time' => 'Events on holidays are not allowed.'])
                ->withInput();
        }

        // Create events based on recurrence
        $startTime = Carbon::parse($selectedDate);
        $endTime = Carbon::parse($request->end_time);

        switch ($recurrence) {
            case 'none':
                // Create a single event without recurrence
                $this->createEvent($request, $startTime, $endTime, $recurrence);
                break;

            case 'daily':
                $this->createEvent($request, $startTime, $endTime,$recurrence);
                break;

            case 'weekly':
                while ($startTime->lte($endTime)) {
                    if (!$this->isHoliday($startTime)) {
                        $this->createEvent($request, $startTime, $endTime, $recurrence);
                    }
                    $startTime->addWeek();
                }
                break;

            case 'monthly':
                while ($startTime->lte($endTime)) {
                    if (!$this->isHoliday($startTime)) {
                        $this->createEvent($request, $startTime, $endTime, $recurrence);
                    }
                    $startTime->addMonth();
                }
                break;

            default:
                // Handle unsupported recurrence type
                return redirect()->route('admin.events.create')
                    ->withErrors(['recurrence' => 'Unsupported recurrence type.'])
                    ->withInput();
        }

        return redirect()->route('admin.systemCalendar');
    }
    private function createEvent($request, $startTime, $endTime, $recurrence)
    {
        Event::create([
            'name' => $request->name,
            'description' => $request->description,
            'salle'=> $request->salle,
            'start_time' => $startTime,
            'user_email'=>$request->user_email,
            'end_time' => $endTime,
            'recurrence' => $recurrence,
        ]);
    }
    public function edit(Event $event)
    {
        abort_if(Gate::denies('event_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $event->load('event')
            ->loadCount('events');

        return view('admin.events.edit', compact('event'));
    }

    public function update(UpdateEventRequest $request, Event $event)
    {
        $event->update($request->all());

        return redirect()->route('admin.systemCalendar');
    }
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

    private function isSalleAvailable($startTime, $endTime, $salle)
    {
        // Check if there are any events that overlap with the specified time range for the given salle and date
        $conflictingEvents = Event::where('salle', $salle)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($query) use ($startTime, $endTime) {
                    $query->where('start_time', '>=', $startTime)
                        ->where('start_time', '<', $endTime);
                })->orWhere(function ($query) use ($startTime, $endTime) {
                    $query->where('end_time', '>', $startTime)
                        ->where('end_time', '<=', $endTime);
                })->orWhere(function ($query) use ($startTime, $endTime) {
                    $query->where('start_time', '<=', $startTime)
                        ->where('end_time', '>=', $endTime);
                });
            })
            ->count();

        // If there are conflicting events, salle is not available
        return $conflictingEvents == 0;
    }

    public function show(Event $event)
    {
        abort_if(Gate::denies('event_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $event->load('event');

        return view('admin.events.show', compact('event'));
    }

    public function destroy(Event $event)
    {
        abort_if(Gate::denies('event_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $event->delete();

        return back();
    }
    public function massDestroy(MassDestroyEventRequest $request)
    {
        Event::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
    public function approve(Event $event)
    {

        $event->update(['states' => 1]); // Update the state to approved

        return redirect()->route('admin.events.index')->with('state_change', 'Event approved successfully');
    }
    public function refuse(Event $event)
    {

        $event->update(['states' => 0]); // Update the state to refused

        return redirect()->route('admin.events.index')->with('state_change', 'Event refused successfully');
    }
    public function deletedEvents()
    {
        $deletedEvents = Event::onlyTrashed()->get();

        $csvFileName = 'deleted_events_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $csvFilePath = "deleted_events\\{$csvFileName}";

        // Ensure the directory exists
        $directoryPath = storage_path("app\\deleted_events");
        if (!file_exists($directoryPath)) {
            mkdir($directoryPath, 0777, true);
        }

        // Save the CSV file using the Storage facade
        Storage::disk('local')->put($csvFilePath, '');

        $handle = fopen(storage_path("app\\{$csvFilePath}"), 'w');

        // Add CSV header
        fputcsv($handle, ['ID', 'Name', 'Description', 'Start Time', 'End Time', 'User Email', 'Deleted At']);

        foreach ($deletedEvents as $event) {
            fputcsv($handle, [$event->id, $event->name, $event->description, $event->start_time, $event->end_time, $event->user_email, $event->deleted_at]);
        }

        fclose($handle);

        // Create a downloadable response
        $response = response()->download(storage_path("app\\{$csvFilePath}"))->deleteFileAfterSend(true);

        return $response;
    }



}
