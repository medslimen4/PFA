<?php

namespace App\Http\Controllers\Admin;

use App\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyEventRequest;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use Gate;
use Illuminate\Http\Request;
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
        Event::create($request->all());

        return redirect()->route('admin.systemCalendar');
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
