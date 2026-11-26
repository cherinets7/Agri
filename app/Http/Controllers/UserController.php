<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    private function ensureManager(Request $request): void
    {
        abort_unless($request->user()->can('manage-users'), 403);
    }

    private function ensureCanManage(Request $request, User $target): void
    {
        $this->ensureManager($request);
        $manager = $request->user();
        abort_unless($manager->isZone() || User::query()->visibleTo($manager)->whereKey($target->id)->exists(), 403);
    }

    private function roleOptions(Request $request): array
    {
        return $request->user()->isZone()
            ? Role::options()
            : [Role::Kebele->value => Role::Kebele->label()];
    }

    public function index(Request $request)
    {
        $this->ensureManager($request);

        $users = User::query()->visibleTo($request->user())->with('location.parent')->orderBy('role')->orderBy('name')->paginate(30);

        return view('users.index', ['users' => $users]);
    }

    public function create(Request $request)
    {
        $this->ensureManager($request);

        return view('users.form', $this->formData($request, new User(['is_active' => true, 'role' => Role::Kebele])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureManager($request);
        $data = $this->validated($request, null);
        User::create($data);

        return redirect()->route('users.index')->with('status', 'ተጠቃሚው ተፈጥሯል።');
    }

    public function edit(Request $request, User $user)
    {
        $this->ensureCanManage($request, $user);

        return view('users.form', $this->formData($request, $user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureCanManage($request, $user);
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->is($request->user())) {
            unset($data['is_active'], $data['role'], $data['location_id']);
        }
        $user->update($data);

        return redirect()->route('users.index')->with('status', 'ተጠቃሚው ተስተካክሏል።');
    }

    private function formData(Request $request, User $user): array
    {
        $manager = $request->user();
        $roles = $this->roleOptions($request);

        $locations = collect();
        foreach (Role::cases() as $role) {
            if (! array_key_exists($role->value, $roles)) {
                continue;
            }
            $locations[$role->value] = $manager->isZone()
                ? Location::query()->ofLevel($role->level())->with('parent')->orderBy('name_am')->get()
                : $manager->manageableLocations($role->level())->with('parent')->get();
        }

        return ['user' => $user, 'roles' => $roles, 'locationsByRole' => $locations];
    }

    private function validated(Request $request, ?User $user): array
    {
        $roles = array_keys($this->roleOptions($request));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in($roles)],
            'location_id' => ['required', 'exists:locations,id'],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'name' => 'ስም', 'email' => 'ኢሜይል', 'phone' => 'ስልክ', 'role' => 'ሚና',
            'location_id' => 'አካባቢ', 'password' => 'የይለፍ ቃል',
        ]);

        $role = Role::from($data['role']);
        $allowed = $request->user()->isZone()
            ? Location::query()->ofLevel($role->level())
            : $request->user()->manageableLocations($role->level());
        abort_unless($allowed->whereKey($data['location_id'])->exists(), 422, 'አካባቢው ከሚናው ጋር አይመጣጠንም።');

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
