<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Staff accounts')]
class Users extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'election_admin';

    public bool $is_active = true;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->authorize('manage-users');
    }

    public function create(): void
    {
        $this->authorize('manage-users');
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'email', 'password', 'password_confirmation']);
        $this->role = Role::ElectionAdmin->value;
        $this->is_active = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('manage-users');
        $u = User::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $u->id;
        $this->name = $u->name;
        $this->email = $u->email;
        $this->role = $u->role()?->value ?? Role::Committee->value;
        $this->is_active = $u->is_active;
        $this->password = '';
        $this->password_confirmation = '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('manage-users');

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'confirmed', User::passwordRule()],
        ]);

        $role = Role::from($this->role);

        if ($this->editingId === auth()->id() && (! $this->is_active || $role !== Role::SuperAdmin)) {
            $this->addError('role', 'You cannot remove your own Super Administrator access or deactivate yourself.');

            return;
        }

        $user = $this->editingId ? User::findOrFail($this->editingId) : new User;
        $user->fill([
            'name' => $this->name,
            'email' => strtolower($this->email),
            'role_id' => UserRole::idFor($role),
            'is_active' => $this->is_active,
        ]);
        if ($this->password !== '') {
            $user->password = $this->password;
        }
        $user->save();

        AuditLogger::log($this->editingId ? 'staff.updated' : 'staff.created', $user, [
            'role' => $role->value, 'active' => $user->is_active, 'password_changed' => $this->password !== '',
        ]);

        $this->reset(['password', 'password_confirmation']);
        $this->showForm = false;
        $this->dispatch('notify', message: 'Staff account saved.', type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.users', [
            'users' => User::with('userRole')->orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }
}
