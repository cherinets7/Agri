<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Support\Audit;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PermissionController extends Controller
{
    public function edit()
    {
        Gate::authorize('manage-permissions');

        return view('permissions.edit', [
            'capabilities' => Permissions::definitions(),
            'roles' => Role::cases(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-permissions');

        $submitted = (array) $request->input('perm', []);
        $changed = [];

        foreach (Permissions::definitions() as $capability => $definition) {
            if ($definition['locked']) {
                continue;
            }
            foreach (Role::cases() as $role) {
                $allowed = isset($submitted[$role->value][$capability]);
                if (Permissions::roleAllows($role, $capability) !== $allowed) {
                    $changed[] = "{$role->value}:{$capability} → ".($allowed ? 'on' : 'off');
                }
                Permissions::set($role, $capability, $allowed, $request->user());
            }
        }

        if ($changed) {
            Audit::record('permissions_changed', null, 'ፈቃዶች ተቀይረዋል', ['changed' => $changed]);
        }

        return redirect()->route('permissions.edit')->with('status', $changed ? 'ፈቃዶች ተቀምጠዋል።' : 'ምንም ለውጥ የለም።');
    }
}
