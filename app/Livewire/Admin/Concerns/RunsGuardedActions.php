<?php

namespace App\Livewire\Admin\Concerns;

use App\Exceptions\ElectionStateException;
use Closure;

/**
 * Helper for Livewire actions: re-checks the permission on EVERY call
 * (Livewire actions are public endpoints) and turns rule violations
 * into friendly toasts instead of error pages.
 */
trait RunsGuardedActions
{
    protected function guarded(string $permission, Closure $action, ?string $success = null): mixed
    {
        $this->authorize($permission);

        try {
            $result = $action();

            if ($success) {
                $this->dispatch('notify', message: $success, type: 'success');
            }

            return $result;
        } catch (ElectionStateException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return null;
        }
    }
}
