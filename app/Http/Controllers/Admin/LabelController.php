<?php

namespace App\Http\Controllers\Admin;

use App\Events\CatalogUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, RecordLabel};
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Storage};
use Illuminate\Support\Str;

class LabelController extends Controller
{
    public function index()
    {
        $labels = RecordLabel::withCount(['songs', 'albums'])->latest()->paginate(20);
        return view('admin.labels.index', compact('labels'));
    }

    public function create()
    {
        return view('admin.labels.form', ['label' => new RecordLabel]);
    }

    public function edit(RecordLabel $label)
    {
        return view('admin.labels.form', compact('label'));
    }

    public function store(Request $request)
    {
        $data  = $this->validated($request);
        $label = RecordLabel::create($data);

        AuditLog::record('create', 'RecordLabel', $label->id, newValues: ['name' => $label->name]);
        $this->syncBroadcast('created', $label);

        return redirect()->route('admin.labels.index')->with('ok', "Label musik \"{$label->name}\" ditambahkan & disiarkan real-time.");
    }

    public function update(Request $request, RecordLabel $label)
    {
        $data = $this->validated($request, $label);
        $label->update($data);

        AuditLog::record('update', 'RecordLabel', $label->id, newValues: ['name' => $label->name]);
        $this->syncBroadcast('updated', $label);

        return redirect()->route('admin.labels.index')->with('ok', "Label musik \"{$label->name}\" diperbarui.");
    }

    public function destroy(RecordLabel $label)
    {
        if ($label->logo) Storage::disk('public')->delete($label->logo);
        AuditLog::record('delete', 'RecordLabel', $label->id, ['name' => $label->name]);
        $id = $label->id;
        $name = $label->name;
        $label->delete();

        Cache::forget('home:labels');
        Cache::forget('seo:sitemap:xml');
        SafeBroadcast::dispatch(new CatalogUpdatedEvent('deleted', 'label', $id, $name));

        return back()->with('ok', 'Label musik dihapus.');
    }

    private function validated(Request $request, ?RecordLabel $label = null): array
    {
        $data = $request->validate([
            'name'         => 'required|string|max:120',
            'country'      => 'nullable|string|max:80',
            'founded_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'website'      => 'nullable|url|max:255',
            'description'  => 'nullable|string|max:2000',
            'logo'         => 'nullable|image|max:2048',
            'is_featured'  => 'boolean',
        ]);

        unset($data['logo']);
        $data['is_featured'] = $request->boolean('is_featured');
        if (! $label) {
            $data['slug'] = Str::slug($data['name']) . '-' . Str::lower(Str::random(4));
        }

        if ($request->hasFile('logo')) {
            if ($label?->logo) Storage::disk('public')->delete($label->logo);
            $data['logo'] = $request->file('logo')->store('labels', 'public');
        }

        return $data;
    }

    private function syncBroadcast(string $action, RecordLabel $label): void
    {
        Cache::forget('home:labels');
        Cache::forget('seo:sitemap:xml');
        SafeBroadcast::dispatch(new CatalogUpdatedEvent($action, 'label', $label->id, $label->name));
    }
}
