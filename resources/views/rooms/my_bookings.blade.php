<x-layouts.meeting :activeUser="$activeUser" :users="$users" title="Reservasi Saya">
    <div x-data="{
        cancelModalOpen: false,
        rescheduleModalOpen: false,
        historyModalOpen: false,
        conflictModalOpen: {{ session('conflict_error') ? 'true' : 'false' }},
        selectedBooking: null
    }">

        <!-- Minimal Breadcrumb -->
        <div class="mb-4 flex items-center space-x-2 text-xs text-zinc-500">
            <a href="{{ route('web.rooms.index') }}" class="hover:text-zinc-900 transition">Ruang Meeting</a>
            <span>/</span>
            <span class="font-medium text-zinc-800">Reservasi Saya</span>
        </div>

        <!-- Page Header & Action Bar -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-zinc-950 tracking-tight">Reservasi Saya</h1>
                <p class="text-xs text-zinc-500 mt-1">Daftar pemesanan ruangan untuk akun <span class="font-medium text-zinc-800">{{ $activeUser->name }}</span>.</p>
            </div>
            <div class="flex items-center space-x-2.5">
                <a href="{{ route('web.rooms.search') }}" class="px-3.5 py-2 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-700 font-medium text-xs shadow-sm transition inline-flex items-center space-x-2">
                    <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span>Cari Ruang Kosong</span>
                </a>
                <a href="{{ route('web.rooms.index') }}" class="px-3.5 py-2 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs shadow-sm transition inline-flex items-center space-x-2">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Pesan Ruangan</span>
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white p-3.5 rounded-xl border border-zinc-200 shadow-sm mb-6 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-1.5">
                <a href="{{ route('web.bookings.my', ['status' => 'all']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium transition {{ $statusFilter === 'all' ? 'bg-zinc-900 text-white shadow-sm' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100' }}">
                    Semua ({{ $totalCount }})
                </a>
                <a href="{{ route('web.bookings.my', ['status' => 'upcoming']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium transition {{ $statusFilter === 'upcoming' ? 'bg-zinc-900 text-white shadow-sm' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100' }}">
                    Mendatang ({{ $upcomingCount }})
                </a>
                <a href="{{ route('web.bookings.my', ['status' => 'confirmed']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium transition {{ $statusFilter === 'confirmed' ? 'bg-zinc-900 text-white shadow-sm' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100' }}">
                    Terkonfirmasi ({{ $confirmedCount }})
                </a>
                <a href="{{ route('web.bookings.my', ['status' => 'cancelled']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium transition {{ $statusFilter === 'cancelled' ? 'bg-zinc-900 text-white shadow-sm' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100' }}">
                    Dibatalkan ({{ $cancelledCount }})
                </a>
            </div>

            <div class="text-zinc-500 text-xs hidden sm:block">
                Menampilkan <span class="font-medium text-zinc-900 font-mono">{{ $bookings->count() }}</span> data
            </div>
        </div>

        <!-- Bookings List (Horizontal Rows) -->
        @if($bookings->isEmpty())
            <div class="bg-white rounded-2xl border border-zinc-200 p-12 text-center shadow-sm">
                <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 flex items-center justify-center text-zinc-400 mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-zinc-900 text-sm">Belum Ada Reservasi</h3>
                <p class="text-zinc-500 text-xs mt-1 max-w-sm mx-auto">
                    @if($statusFilter === 'upcoming')
                        Anda tidak memiliki jadwal reservasi mendatang.
                    @elseif($statusFilter === 'cancelled')
                        Anda belum pernah membatalkan reservasi.
                    @else
                        Anda belum memiliki riwayat pemesanan ruangan pada akun ini.
                    @endif
                </p>
                <div class="mt-4">
                    <a href="{{ route('web.rooms.search') }}" class="px-3.5 py-2 bg-zinc-900 text-white rounded-lg font-medium text-xs hover:bg-zinc-800 transition inline-flex items-center space-x-2">
                        <span>Cari Ruang Kosong</span>
                    </a>
                </div>
            </div>
        @else
            <div class="space-y-3">
                @foreach($bookings as $b)
                    @php
                        $isConfirmed = $b->isConfirmed();
                    @endphp
                    <div class="bg-white rounded-xl border border-zinc-200 p-4 sm:p-5 shadow-sm hover:border-zinc-300 transition">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <!-- Left / Main Details -->
                            <div class="space-y-1.5 flex-1 min-w-0">
                                <!-- Title, Room, Status Line -->
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-sm text-zinc-950 truncate {{ !$isConfirmed ? 'line-through text-zinc-400' : '' }}">
                                        {{ $b->title }}
                                    </h3>
                                    <span class="text-zinc-300">&bull;</span>
                                    <a href="{{ route('web.rooms.show', $b->room_id) }}" class="text-xs font-medium text-zinc-700 hover:text-zinc-950 hover:underline">
                                        {{ $b->room->name }}
                                    </a>
                                    <span class="text-xs text-zinc-400">({{ $b->room->location }})</span>

                                    <!-- Status Badge -->
                                    <span class="px-2 py-0.5 rounded text-[11px] font-medium shrink-0 {{ $isConfirmed ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' : 'bg-zinc-100 text-zinc-600 border border-zinc-200' }}">
                                        {{ $isConfirmed ? 'Terkonfirmasi' : 'Dibatalkan' }}
                                    </span>

                                    @if($b->parent_id)
                                        <span class="bg-zinc-100 text-zinc-600 px-1.5 py-0.5 rounded border border-zinc-200 text-[10px] font-mono">
                                            Seri Berulang
                                        </span>
                                    @endif
                                </div>

                                <!-- Schedule & Duration Line -->
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-600 font-mono">
                                    <span class="font-medium text-zinc-900">
                                        {{ $b->start_time->format('d M Y') }}
                                    </span>
                                    <span>
                                        {{ $b->start_time->format('H:i') }} - {{ $b->end_time->format('H:i') }} (UTC)
                                    </span>
                                    <span class="text-zinc-400 text-[11px]">
                                        ({{ $b->start_time->diffInMinutes($b->end_time) }} Menit)
                                    </span>
                                </div>

                                @if($b->isCancelled() && $b->cancellation_reason)
                                    <div class="text-[11px] text-zinc-600 bg-zinc-50 px-2.5 py-1.5 rounded-lg border border-zinc-200 mt-1 max-w-2xl">
                                        <span class="font-medium text-zinc-800">Alasan Batal:</span> {{ $b->cancellation_reason }}
                                    </div>
                                @endif
                            </div>

                            <!-- Right / Actions -->
                            <div class="flex items-center space-x-1.5 shrink-0 self-start lg:self-center pt-2 lg:pt-0 border-t lg:border-t-0 border-zinc-100">
                                <a href="{{ route('web.rooms.show', $b->room_id) }}" class="px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-medium rounded-lg transition inline-flex items-center space-x-1.5 shadow-sm">
                                    <span>Lihat Ruang</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>

                                <button
                                    type="button"
                                    @click="selectedBooking = {{ $b->load('histories.user')->toJson() }}; historyModalOpen = true"
                                    title="Riwayat Perubahan"
                                    class="px-2.5 py-1.5 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-700 text-xs font-medium transition"
                                >
                                    Riwayat ({{ $b->histories->count() }})
                                </button>

                                @if($isConfirmed)
                                    <button
                                        type="button"
                                        @click="selectedBooking = {{ $b->toJson() }}; rescheduleModalOpen = true"
                                        title="Ubah Jadwal"
                                        class="px-2.5 py-1.5 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-700 text-xs font-medium transition"
                                    >
                                        Reschedule
                                    </button>

                                    <button
                                        type="button"
                                        @click="selectedBooking = {{ $b->toJson() }}; cancelModalOpen = true"
                                        title="Batalkan Booking"
                                        class="px-2.5 py-1.5 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-100 text-zinc-700 hover:text-rose-600 text-xs font-medium transition"
                                    >
                                        Batalkan
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- =================== MODALS (CLEAN & MINIMALIST) =================== -->

        <!-- Modal 1: Interaktif Peringatan Konflik Jadwal (HTTP 409 Conflict) -->
        <div x-show="conflictModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="conflictModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-zinc-200">
                <div class="flex items-start justify-between pb-3 border-b border-zinc-100 mb-4">
                    <div>
                        <div class="flex items-center space-x-2">
                            <h3 class="font-semibold text-base text-zinc-950">Konflik Jadwal Reservasi</h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-medium bg-zinc-100 text-zinc-700 border border-zinc-200">409 Conflict</span>
                        </div>
                        <p class="text-xs text-zinc-500 mt-0.5">Waktu yang Anda ajukan beririsan dengan jadwal yang sudah ada.</p>
                    </div>
                    <button @click="conflictModalOpen = false" class="text-zinc-400 hover:text-zinc-600 text-lg leading-none">&times;</button>
                </div>

                <div class="space-y-4 text-xs">
                    <p class="text-zinc-700 leading-relaxed">
                        {{ session('conflict_error') }}
                    </p>

                    @if(session('conflict_details'))
                        <div class="bg-zinc-50 p-3.5 rounded-xl border border-zinc-200 space-y-2">
                            <span class="font-semibold text-zinc-900 block text-xs">Booking yang Bertabrakan:</span>
                            <div class="grid grid-cols-2 gap-2 text-zinc-800">
                                <div>
                                    <span class="text-zinc-400 block text-[10px]">Judul:</span>
                                    <span class="font-medium">{{ session('conflict_details.title') }}</span>
                                </div>
                                <div>
                                    <span class="text-zinc-400 block text-[10px]">Penyelenggara:</span>
                                    <span class="font-medium">{{ session('conflict_details.user_name') ?? 'User #' . session('conflict_details.conflicting_booking_id') }}</span>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-zinc-400 block text-[10px]">Rentang Waktu:</span>
                                    <span class="font-mono text-zinc-950 font-medium">
                                        {{ session('conflict_details.formatted_start') ?? session('conflict_details.start_time') }} s/d {{ session('conflict_details.formatted_end') ?? session('conflict_details.end_time') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="text-[11px] text-zinc-500 bg-white p-2.5 rounded-lg border border-zinc-200">
                        Rekomendasi: Silakan pilih jam lain di luar rentang jadwal yang sudah terisi di atas.
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-100 mt-4">
                    <button type="button" @click="conflictModalOpen = false" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-lg text-xs font-medium transition">
                        Tutup & Ubah Jam
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal 2: Konfirmasi Pembatalan Booking -->
        <div x-show="cancelModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="cancelModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-zinc-200">
                <div class="flex items-center space-x-3 text-rose-600 mb-3">
                    <div class="w-9 h-9 rounded-full bg-rose-50 flex items-center justify-center border border-rose-100 shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-base text-zinc-950">Konfirmasi Pembatalan</h3>
                        <p class="text-xs text-zinc-500">Tindakan ini akan membatalkan jadwal reservasi</p>
                    </div>
                </div>

                <form :action="'/bookings/' + (selectedBooking ? selectedBooking.id : '') + '/cancel'" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <p class="text-zinc-600">
                        Apakah Anda yakin ingin membatalkan jadwal meeting <span class="font-semibold text-zinc-900" x-text="selectedBooking ? selectedBooking.title : ''"></span>?
                    </p>
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Alasan Pembatalan (Opsional)</label>
                        <textarea name="reason" rows="2" placeholder="Tulis alasan pembatalan jika ada..." class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none"></textarea>
                    </div>
                    <div class="flex justify-end space-x-2.5 pt-3 border-t border-zinc-100">
                        <button type="button" @click="cancelModalOpen = false" class="px-3.5 py-2 border border-zinc-200 rounded-lg text-zinc-700 text-xs font-medium hover:bg-zinc-50 transition">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-medium shadow-sm transition">Batalkan Booking</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal 3: Reschedule Booking -->
        <div x-show="rescheduleModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="rescheduleModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-zinc-200">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-zinc-100">
                    <div>
                        <h3 class="font-semibold text-base text-zinc-950">Ubah Jadwal (Reschedule)</h3>
                        <p class="text-xs text-zinc-500 mt-0.5" x-text="'Meeting: ' + (selectedBooking ? selectedBooking.title : '')"></p>
                    </div>
                    <button @click="rescheduleModalOpen = false" class="text-zinc-400 hover:text-zinc-600 text-lg leading-none">&times;</button>
                </div>
                <form :action="'/bookings/' + (selectedBooking ? selectedBooking.id : '') + '/reschedule'" method="POST" class="space-y-3.5 text-xs">
                    @csrf
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Waktu Mulai Baru *</label>
                        <input type="datetime-local" name="reschedule_start" required class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs font-mono focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Waktu Selesai Baru *</label>
                        <input type="datetime-local" name="reschedule_end" required class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs font-mono focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Alasan Perubahan (Opsional)</label>
                        <input type="text" name="reschedule_reason" placeholder="Contoh: Menyesuaikan waktu peserta" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                    <div class="flex justify-end space-x-2.5 pt-3 border-t border-zinc-100">
                        <button type="button" @click="rescheduleModalOpen = false" class="px-3.5 py-2 border border-zinc-200 rounded-lg text-zinc-700 text-xs font-medium hover:bg-zinc-50 transition">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-lg text-xs font-medium shadow-sm transition">Simpan Jadwal Baru</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal 4: Riwayat Perubahan (Audit Trail) -->
        <div x-show="historyModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="historyModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-zinc-200">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-zinc-100">
                    <div>
                        <h3 class="font-semibold text-base text-zinc-950">Riwayat Perubahan (Audit Trail)</h3>
                        <p class="text-xs text-zinc-500 mt-0.5" x-text="'Meeting: ' + (selectedBooking ? selectedBooking.title : '')"></p>
                    </div>
                    <button @click="historyModalOpen = false" class="text-zinc-400 hover:text-zinc-600 text-lg leading-none">&times;</button>
                </div>

                <div class="space-y-2.5 max-h-96 overflow-y-auto pr-1">
                    <template x-if="selectedBooking && selectedBooking.histories && selectedBooking.histories.length > 0">
                        <template x-for="(h, idx) in selectedBooking.histories" :key="idx">
                            <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-200 text-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-mono text-[10px] uppercase font-semibold px-2 py-0.5 rounded bg-zinc-100 text-zinc-700 border border-zinc-200"
                                          x-text="h.action">
                                    </span>
                                    <span class="text-zinc-400 font-mono text-[11px]" x-text="new Date(h.created_at).toLocaleString()"></span>
                                </div>
                                <div class="text-zinc-700 mt-1">
                                    <span class="text-zinc-400">Oleh:</span> <span class="font-medium text-zinc-900" x-text="h.user ? h.user.name : ('User #' + h.user_id)"></span>
                                </div>
                                <div x-show="h.reason" class="text-zinc-600 mt-1 bg-white p-2 rounded border border-zinc-200 text-[11px]" x-text="'Catatan: ' + h.reason"></div>
                            </div>
                        </template>
                    </template>
                    <template x-if="!selectedBooking || !selectedBooking.histories || selectedBooking.histories.length === 0">
                        <div class="text-center py-6 text-zinc-400 text-xs">
                            Belum ada riwayat tercatat.
                        </div>
                    </template>
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-100 mt-3">
                    <button type="button" @click="historyModalOpen = false" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-lg text-xs font-medium transition">Tutup</button>
                </div>
            </div>
        </div>

    </div>
</x-layouts.meeting>
