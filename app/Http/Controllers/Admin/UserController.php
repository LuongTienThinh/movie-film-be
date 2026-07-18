<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'role' => ['sometimes', 'nullable', Rule::in(['admin', 'user'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $search = trim($validated['search'] ?? '');
        $role = $validated['role'] ?? '';

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                });
            })
            ->when($role !== '', fn ($query) => $query->where('role', $role))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.user.index', compact('users', 'search', 'role'));
    }

    public function edit(int $id)
    {
        $user = User::query()->findOrFail($id);

        return view('admin.user.edit', compact('user'));
    }

    public function update(Request $request, int $id)
    {
        $user = User::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+() .-]+$/'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'role' => ['required', Rule::in(['admin', 'user'])],
        ]);

        if ($user->role === 'admin' && $data['role'] !== 'admin') {
            $isCurrentUser = $request->user()->is($user);
            $isLastAdmin = User::query()->where('role', 'admin')->count() <= 1;

            if ($isCurrentUser || $isLastAdmin) {
                return back()
                    ->withInput()
                    ->with('error', 'Không thể hạ quyền tài khoản admin này.');
            }
        }

        if ($user->email !== $data['email']) {
            $user->email_verified_at = null;
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->gender = $data['gender'] ?? null;
        $user->date_of_birth = $data['date_of_birth'] ?? null;
        $user->role = $data['role'];
        $user->save();

        return redirect()->route('admin.users.index')
            ->with('success', 'Thông tin người dùng đã được cập nhật.');
    }
}
