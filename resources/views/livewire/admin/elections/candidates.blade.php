<div>
    <x-election-nav :election="$election" />

    <x-ui.page-header title="Candidates" description="Register, review and approve candidates. Only approved candidates appear on the ballot.">
        <x-slot:actions>
            <label class="sr-only" for="pf">Position</label>
            <select id="pf" wire:model.live="positionFilter" class="form-input w-48">
                <option value="">All positions</option>
                @foreach ($positions as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
            <label class="sr-only" for="sf">Status</label>
            <select id="sf" wire:model.live="statusFilter" class="form-input w-36">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
            </select>
            @can('manage-elections')
                @if ($editable)<x-ui.button wire:click="create" icon="plus">Register candidate</x-ui.button>@endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @unless ($editable)
        <div class="mb-4 flex items-center gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
            <x-lucide-lock class="size-4" /> The candidate list is locked because the election is {{ $election->status->label() }}.
        </div>
    @endunless

    @if ($candidates->isEmpty())
        <x-ui.empty icon="users-round" title="No candidates" description="Register students who filed their certificate of candidacy." />
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($candidates as $c)
                <article wire:key="c-{{ $c->id }}" class="flex flex-col rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-start gap-3">
                        @if ($c->photoUrl())
                            <img src="{{ $c->photoUrl() }}" alt="Photo of {{ $c->display_name }}" class="size-16 rounded-xl object-cover">
                        @else
                            <span class="flex size-16 items-center justify-center rounded-xl bg-slate-100 text-lg font-bold text-slate-500">{{ $c->initials() }}</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900">{{ $c->display_name }}</p>
                            <p class="text-sm text-slate-600">{{ $c->position->name }}</p>
                            <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                <span class="rounded-full px-2 py-0.5 font-medium text-white" style="background-color: {{ $c->partyList?->color ?? '#64748b' }}">{{ $c->partyLabel() }}</span>
                                <span class="text-slate-500">Grade {{ $c->grade_level }} · {{ $c->student->student_number }}</span>
                            </p>
                        </div>
                        <x-ui.badge :color="$c->status->badgeClasses()">{{ $c->status->label() }}</x-ui.badge>
                    </div>
                    @if ($c->platform)<p class="mt-3 line-clamp-3 text-sm text-slate-600">{{ $c->platform }}</p>@endif
                    @if ($c->status_reason)<p class="mt-2 text-xs text-rose-700">Reason: {{ $c->status_reason }}</p>@endif

                    @can('manage-elections')
                        @if ($editable)
                            <div class="mt-auto flex flex-wrap gap-1 border-t border-slate-100 pt-3">
                                @if ($c->status !== \App\Enums\CandidateStatus::Approved)
                                    <x-ui.button size="sm" variant="success" wire:click="approve({{ $c->id }})" icon="check">Approve</x-ui.button>
                                @endif
                                @if ($c->status === \App\Enums\CandidateStatus::Pending || $c->status === \App\Enums\CandidateStatus::Approved)
                                    <x-ui.button size="sm" variant="secondary" wire:click="openReject({{ $c->id }})">Reject</x-ui.button>
                                @endif
                                @if ($c->status === \App\Enums\CandidateStatus::Approved)
                                    <x-ui.button size="sm" variant="secondary" wire:click="withdraw({{ $c->id }})" wire:confirm="Mark {{ $c->display_name }} as withdrawn?">Withdraw</x-ui.button>
                                @endif
                                <x-ui.button size="sm" variant="ghost" wire:click="edit({{ $c->id }})" icon="pencil">Edit</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" class="text-rose-600" wire:click="delete({{ $c->id }})" wire:confirm="Remove this candidate record?" icon="trash-2"><span class="sr-only">Delete</span></x-ui.button>
                            </div>
                        @endif
                    @endcan
                </article>
            @endforeach
        </div>
    @endif

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit candidate' : 'Register candidate'" max-width="max-w-2xl">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="student-search" class="form-label">Student</label>
                @if ($selectedStudent)
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200">
                        <span><strong>{{ $selectedStudent->full_name }}</strong> · {{ $selectedStudent->student_number }}</span>
                        @unless ($editingId)
                            <button type="button" wire:click="$set('student_id', null)" class="text-brand hover:underline">Change</button>
                        @endunless
                    </div>
                @else
                    <input id="student-search" type="search" wire:model.live.debounce.300ms="studentSearch" class="form-input" placeholder="Search by name, Student ID or LRN" autocomplete="off">
                    @if ($searchResults->isNotEmpty())
                        <ul class="mt-1 divide-y divide-slate-100 rounded-lg ring-1 ring-slate-200">
                            @foreach ($searchResults as $s)
                                @php($enr = $s->enrollments->first())
                                <li>
                                    <button type="button" wire:click="pickStudent({{ $s->id }})" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-50">
                                        <span>{{ $s->full_name }} <span class="text-slate-500">· {{ $s->student_number }}</span></span>
                                        <span class="text-xs text-slate-500">{{ $enr ? 'Grade '.$enr->grade_level.' '.$enr->section : 'Not enrolled this S.Y.' }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
                @error('student_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <x-ui.select name="election_position_id" label="Position" wire:model="election_position_id">
                <option value="">Select…</option>
                @foreach ($positions as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->candidateGradesLabel() }})</option>@endforeach
            </x-ui.select>
            <x-ui.select name="party_list_id" label="Party list" wire:model="party_list_id">
                <option value="">Independent</option>
                @foreach ($parties as $party)<option value="{{ $party->id }}">{{ $party->displayName() }}</option>@endforeach
            </x-ui.select>
            <x-ui.input name="ballot_name" label="Name on ballot (optional)" wire:model="ballot_name" class="sm:col-span-2" help="Defaults to the student's full name." />
            <x-ui.textarea name="platform" label="Platform" wire:model="platform" rows="4" class="sm:col-span-2" />
            <x-ui.textarea name="biography" label="Short biography" wire:model="biography" rows="3" class="sm:col-span-2" />
            <div class="sm:col-span-2">
                <label for="photo" class="form-label">Photo (JPG/PNG/WebP, square works best, max 2 MB)</label>
                <input id="photo" type="file" wire:model="photo" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2">
                <div wire:loading wire:target="photo" class="mt-1 text-xs text-slate-500">Uploading…</div>
                @error('photo') <p class="form-error">{{ $message }}</p> @enderror
                @if ($photo && ! $errors->has('photo'))
                    <img src="{{ $photo->temporaryUrl() }}" alt="Preview" class="mt-2 size-20 rounded-xl object-cover">
                @endif
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button wire:click="save" icon="check" wire:loading.attr="disabled">Save candidate</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="showReject" title="Reject candidate">
        <x-ui.textarea name="rejectReason" label="Reason (visible to staff, recorded in audit log)" wire:model="rejectReason" />
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button variant="danger" wire:click="reject">Reject</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
