@if (session('status'))
    <div class="no-print fixed z-[80] left-1/2 -translate-x-1/2 bottom-24 lg:bottom-8 pointer-events-none" data-flash>
        <div class="rounded-xl bg-navy-900 text-white px-4 py-3 shadow-2xl text-sm font-semibold flex items-center gap-2"><x-icon name="check" class="w-4 h-4 text-emerald-400" />{{ session('status') }}</div>
    </div>
@endif
