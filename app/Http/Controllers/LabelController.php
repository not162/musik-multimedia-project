<?php

namespace App\Http\Controllers;

use App\Models\RecordLabel;

class LabelController extends Controller
{
    public function index()
    {
        $labels = RecordLabel::withCount(['songs', 'albums'])
            ->orderByDesc('is_featured')
            ->latest()
            ->paginate(18);

        return view('labels.index', compact('labels'));
    }

    public function show(RecordLabel $label)
    {
        $songs  = $label->songs()->with(['artist', 'album', 'genre'])->orderByDesc('play_count')->get();
        $albums = $label->albums()->with('artist')->latest()->get();

        return view('labels.show', compact('label', 'songs', 'albums'));
    }
}
