<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\LetterMemory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemoryController extends Controller
{
    public function store(Request $request, Letter $letter)
    {
        $this->ownLetter($letter);
        $data = $this->validated($request);
        unset($data['memory_image']);
        $data['sort_order'] = ($letter->memories()->max('sort_order') ?? -1) + 1;
        if ($request->hasFile('memory_image')) {
            $data['image_path'] = $request->file('memory_image')->store('letters/memories', 'public');
        }
        $letter->memories()->create($data);

        return back()->with('success', 'Memory added.');
    }

    public function update(Request $request, LetterMemory $memory)
    {
        $this->ownMemory($memory);
        $data = $this->validated($request);
        unset($data['memory_image']);
        if ($request->boolean('remove_memory_image') || $request->hasFile('memory_image')) {
            Storage::disk('public')->delete($memory->image_path);
            $data['image_path'] = null;
        }
        if ($request->hasFile('memory_image')) {
            $data['image_path'] = $request->file('memory_image')->store('letters/memories', 'public');
        }
        $memory->update($data);

        return back()->with('success', 'Memory updated.');
    }

    public function move(LetterMemory $memory, string $direction)
    {
        $this->ownMemory($memory);
        abort_unless(in_array($direction, ['up', 'down']), 404);
        $comparison = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'desc' : 'asc';
        $neighbor = $memory->letter->memories()
            ->where('sort_order', $comparison, $memory->sort_order)
            ->orderBy('sort_order', $order)
            ->first();

        if ($neighbor) {
            [$memoryOrder, $neighborOrder] = [$memory->sort_order, $neighbor->sort_order];
            $memory->update(['sort_order' => $neighborOrder]);
            $neighbor->update(['sort_order' => $memoryOrder]);
        }

        return back();
    }

    public function destroy(LetterMemory $memory)
    {
        $this->ownMemory($memory);
        Storage::disk('public')->delete($memory->image_path);
        $memory->delete();

        return back()->with('success', 'Memory deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:120',
            'memory_date' => 'nullable|date',
            'caption' => 'nullable|string|max:1000',
            'memory_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
    }

    private function ownLetter(Letter $letter): void
    {
        abort_unless($letter->user_id === auth()->id(), 403);
    }

    private function ownMemory(LetterMemory $memory): void
    {
        abort_unless($memory->letter->user_id === auth()->id(), 403);
    }
}
