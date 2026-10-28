<div>
    <x-ui.page-header title="Audit log" description="Append-only record of sign-ins and administrative actions. Ballot choices are never logged." />

    <div class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <select wire:model.live="action" class="form-input" aria-label="Action">
            <option value="">All actions</option>
            @foreach ($actions as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
        </select>
        <select wire:model.live="actorType" class="form-input" aria-label="Actor type">
            <option value="">All actors</option>
            <option value="staff">Staff</option>
            <option value="student">Student</option>
            <option value="system">System</option>
        </select>
        <select wire:model.live="electionId" class="form-input" aria-label="Election">
            <option value="">All elections</option>
            @foreach ($elections as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach
        </select>
        <input type="date" wire:model.live="date" class="form-input" aria-label="Date">
    </div>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="table-base">
            <thead><tr><th>Time (PH)</th><th>Actor</th><th>Action</th><th>Subject</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="text-xs whitespace-nowrap">{{ local_dt($log->created_at, 'M j, Y g:i:s A') }}</td>
                        <td class="text-xs"><span class="font-medium">{{ ucfirst((string) $log->actor_type) }}</span> {{ $log->actor_name }}</td>
                        <td><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ $log->action }}</code></td>
                        <td class="text-xs">{{ $log->subject_type }} {{ $log->subject_id ? '#'.$log->subject_id : '' }}</td>
                        <td class="max-w-md text-xs">
                            {{ $log->description }}
                            @if ($log->properties)
                                <span class="block truncate text-slate-400" title="{{ json_encode($log->properties) }}">{{ json_encode($log->properties) }}</span>
                            @endif
                        </td>
                        <td class="font-mono text-xs">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">No entries.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</div>
