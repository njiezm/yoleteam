{{-- Appel rows: one line per member with the four status buttons. Expects $members, $attendances (keyed by member id), $statuses. --}}
                @forelse ($members as $member)
                    @php($current = $attendances->get($member->id)?->status)
                    <div @class(['flex items-center gap-2.5 lg:gap-3 px-3 lg:px-5 py-3', 'bg-amber-50/40' => ! $current])
                         data-member-row="{{ $member->id }}" data-label="{{ $member->short_name }}" data-name="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($member->full_name.' '.$member->nickname)) }}">
                        <x-avatar :member="$member" />
                        <div class="flex-1 min-w-0">
                            <p class="font-bold truncate"><span class="lg:hidden">{{ $member->short_name }}</span><span class="hidden lg:inline">{{ $member->full_name }}</span></p>
                            <p class="text-xs muted truncate">{{ $member->primaryCrewRole()?->label ?? 'Sans poste' }}@if ($member->weight_kg)<span class="hidden sm:inline"> · {{ (float) $member->weight_kg }} kg</span>@endif</p>
                        </div>
                        <div class="flex gap-1 lg:gap-1.5">
                            @foreach ($statuses as $status)
                                @php($on = $current === $status)
                                <button name="statuses[{{ $member->id }}]" value="{{ $on ? '' : $status->value }}"
                                        data-member="{{ $member->id }}" data-status="{{ $status->value }}" data-color="{{ $status->color() }}"
                                        aria-pressed="{{ $on ? 'true' : 'false' }}" title="{{ $status->label() }}"
                                        @class([
                                            'h-10 w-10 lg:w-auto lg:px-3 rounded-xl text-[13px] font-bold border transition cursor-pointer',
                                            'text-white border-transparent shadow-sm' => $on,
                                            'bg-white border-slate-200 text-slate-500 hover:border-slate-300' => ! $on,
                                        ])
                                        @style(["background: {$status->color()}" => $on])>
                                    <span class="lg:hidden">{{ mb_substr($status->label(), 0, 1) }}</span><span class="hidden lg:inline">{{ $status->label() }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="p-5 text-sm muted">Aucun membre actif. <a href="{{ route('members.index') }}" class="font-semibold text-navy-700">Gérer les membres</a></p>
                @endforelse
