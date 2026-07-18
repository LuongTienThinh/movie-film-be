<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

abstract class CatalogController extends Controller
{
    protected string $modelClass;

    protected string $label;

    protected string $routePrefix;

    protected string $table;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $search = trim($validated['search'] ?? '');
        $items = $this->newQuery()
            ->withCount('films')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('slug', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.catalog.index', $this->viewData(compact('items', 'search')));
    }

    public function create()
    {
        return view('admin.catalog.form', $this->viewData([
            'item' => null,
            'formAction' => route($this->routePrefix . '.store'),
            'formMethod' => 'POST',
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $this->newModel()->create($data);

        return redirect()->route($this->routePrefix . '.index')
            ->with('success', $this->label . ' đã được tạo.');
    }

    public function edit(int $id)
    {
        $item = $this->newQuery()->findOrFail($id);

        return view('admin.catalog.form', $this->viewData([
            'item' => $item,
            'formAction' => route($this->routePrefix . '.update', $item),
            'formMethod' => 'PUT',
        ]));
    }

    public function update(Request $request, int $id)
    {
        $item = $this->newQuery()->findOrFail($id);
        $item->update($this->validatedData($request, $item));

        return redirect()->route($this->routePrefix . '.index')
            ->with('success', $this->label . ' đã được cập nhật.');
    }

    public function destroy(int $id)
    {
        $item = $this->newQuery()->findOrFail($id);
        if ($item->films()->exists()) {
            return back()->with('error', 'Không thể xóa mục đang được gán cho phim.');
        }

        $item->delete();

        return back()->with('success', $this->label . ' đã được xóa.');
    }

    protected function validatedData(Request $request, ?Model $item = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique($this->table, 'slug')->ignore($item?->getKey()),
            ],
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);

        validator($data, [
            'slug' => [Rule::unique($this->table, 'slug')->ignore($item?->getKey())],
        ])->validate();

        return $data;
    }

    protected function newQuery()
    {
        return $this->newModel()->newQuery();
    }

    protected function newModel(): Model
    {
        $modelClass = $this->modelClass;

        return new $modelClass();
    }

    protected function viewData(array $data): array
    {
        return array_merge($data, [
            'label' => $this->label,
            'routePrefix' => $this->routePrefix,
        ]);
    }
}
