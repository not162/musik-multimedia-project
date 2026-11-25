<?php
namespace App\Http\Controllers;

class HistoryController extends Controller
{
    public function index()
    {
        $items = auth()->user()->playHistories()->with('song.artist', 'song.album')
            ->whereHas('song')->latest('played_at')->paginate(30);
        return view('library.history', compact('items'));
    }

    public function clear()
    {
        auth()->user()->playHistories()->delete();
        return back()->with('ok', 'Riwayat dihapus.');
    }
}
