<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\LetterMemory;
use App\Models\LetterMemoryImage;
use App\Support\CreatorStorage;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemoryController extends Controller
{
    public function store(Request $request, Letter $letter, CreatorStorage $storage)
    {
        $this->authorize('update', $letter);
        $data = $this->validated($request);
        $storage->ensureWithinQuota($request->user(), $this->uploadedFiles($request));
        unset($data['memory_image'], $data['memory_images']);
        $data['sort_order'] = ($letter->memories()->max('sort_order') ?? -1) + 1;
        $memory = $letter->memories()->create($data);
        $this->storeImages($request, $memory);

        return back()->with('success', 'Memory added.');
    }

    public function update(Request $request, LetterMemory $memory, CreatorStorage $storage)
    {
        $this->authorize('update', $memory);
        $data = $this->validated($request);
        $removedImages = $memory->images()
            ->whereIn('id', $request->input('remove_memory_images', []))
            ->pluck('image_path');
        $replacedPaths = $removedImages;
        if ($request->boolean('remove_memory_image') || $request->hasFile('memory_image')) {
            $replacedPaths->push($memory->image_path);
        }
        $storage->ensureWithinQuota(
            $request->user(),
            $this->uploadedFiles($request),
            $replacedPaths,
        );
        unset($data['memory_image'], $data['memory_images'], $data['remove_memory_images']);
        if ($request->boolean('remove_memory_image') || $request->hasFile('memory_image')) {
            Storage::disk('public')->delete($memory->image_path);
            $data['image_path'] = null;
        }
        $memory->update($data);
        $this->removeImages($request, $memory);
        $this->storeImages($request, $memory);

        return back()->with('success', 'Memory updated.');
    }

    public function move(LetterMemory $memory, string $direction)
    {
        $this->authorize('update', $memory);
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

    public function reorderMemories(Request $request, Letter $letter)
    {
        $this->authorize('update', $letter);
        $data = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);
        $ownedIds = $letter->memories()->whereIn('id', $data['order'])->pluck('id')->all();
        abort_unless(count($ownedIds) === count($data['order']), 422);

        foreach ($data['order'] as $position => $id) {
            $letter->memories()->whereKey($id)->update(['sort_order' => $position]);
        }

        return response()->noContent();
    }

    public function reorderImages(Request $request, LetterMemory $memory)
    {
        $this->authorize('update', $memory);
        $data = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);
        $ownedIds = $memory->images()->whereIn('id', $data['order'])->pluck('id')->all();
        abort_unless(count($ownedIds) === count($data['order']), 422);

        foreach ($data['order'] as $position => $id) {
            LetterMemoryImage::whereKey($id)->update(['sort_order' => $position]);
        }

        return response()->noContent();
    }

    public function destroy(LetterMemory $memory)
    {
        $this->authorize('delete', $memory);
        Storage::disk('public')->delete(array_filter([
            $memory->image_path,
            ...$memory->images()->pluck('image_path')->all(),
        ]));
        $memory->delete();

        return back()->with('success', 'Memory deleted.');
    }

    private function validated(Request $request): array
    {
        $settings = app(PlatformSettings::class);
        $mediaLimit = $settings->kilobytes($settings->letterMediaLimitMb());

        return $request->validate([
            'title' => 'required|string|max:120',
            'memory_date' => 'nullable|date',
            'caption' => 'nullable|string|max:1000',
            'memory_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm', 'max:'.$mediaLimit],
            'memory_images' => ['nullable', 'array', 'max:'.$settings->memoryFilesPerUpload()],
            'memory_images.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm', 'max:'.$mediaLimit],
            'remove_memory_images' => 'nullable|array',
            'remove_memory_images.*' => 'integer',
        ]);
    }

    private function storeImages(Request $request, LetterMemory $memory): void
    {
        $files = collect($request->file('memory_images', []));
        if ($request->hasFile('memory_image')) {
            $files->prepend($request->file('memory_image'));
        }

        $nextOrder = ($memory->images()->max('sort_order') ?? -1) + 1;
        foreach ($files as $file) {
            $memory->images()->create([
                'image_path' => $file->store('letters/memories', 'public'),
                'sort_order' => $nextOrder++,
            ]);
        }
    }

    private function removeImages(Request $request, LetterMemory $memory): void
    {
        $images = $memory->images()->whereIn('id', $request->input('remove_memory_images', []))->get();
        Storage::disk('public')->delete($images->pluck('image_path')->all());
        $memory->images()->whereKey($images->modelKeys())->delete();
    }

    private function uploadedFiles(Request $request): array
    {
        return [
            $request->file('memory_image'),
            ...$request->file('memory_images', []),
        ];
    }
}
