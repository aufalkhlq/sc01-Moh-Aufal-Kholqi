<x-layouts.meeting :activeUser="$activeUser" :users="$users" title="Cari Ruang Kosong">
    <div x-data="{
        searchMode: '{{ $searchType === 'recurring' ? 'recurring' : 'single' }}'
    }">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-zinc-950 tracking-tight">Pencarian Ruang Tersedia</h1>
            <p class="text-xs text-zinc-500 mt-1">Cari ruang meeting yang kosong berdasarkan rentang waktu rapat dan kapasitas minimum yang dibutuhkan.</p>
        </div>

        <!-- Search Mode Switcher Tabs -->
        <div class="inline-flex p-1 bg-zinc-100 rounded-xl border border-zinc-200 text-xs mb-4">
            <button
                type="button"
                @click="searchMode = 'single'"
                :class="searchMode === 'single' ? 'bg-white text-zinc-900 shadow-sm font-semibold' : 'text-zinc-500 hover:text-zinc-800 font-medium'"
                class="px-3.5 py-1.5 rounded-lg transition flex items-center space-x-1.5"
            >
                <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Sekali (Single Slot)</span>
            </button>
            <button
                type="button"
                @click="searchMode = 'recurring'"
                :class="searchMode === 'recurring' ? 'bg-white text-zinc-900 shadow-sm font-semibold' : 'text-zinc-500 hover:text-zinc-800 font-medium'"
                class="px-3.5 py-1.5 rounded-lg transition flex items-center space-x-1.5"
            >
                <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span>Berulang (Rentang Hari & Jam)</span>
            </button>
        </div>

        <!-- Search Form Card -->
        <div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6 shadow-sm mb-8">
            <form action="{{ route('web.rooms.search') }}" method="GET" class="space-y-4">
                <input type="hidden" name="search_type" :value="searchMode">

                <!-- Single Search Inputs -->
                <div x-show="searchMode === 'single'" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">Waktu Mulai *</label>
                        <input type="datetime-local" name="start_time" :disabled="searchMode !== 'single'" value="{{ $startTime ?? now()->addHour()->format('Y-m-d\TH:00') }}" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs font-mono focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">Waktu Selesai *</label>
                        <input type="datetime-local" name="end_time" :disabled="searchMode !== 'single'" value="{{ $endTime ?? now()->addHours(2)->format('Y-m-d\TH:00') }}" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs font-mono focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">Kapasitas Minimum (Orang)</label>
                        <input type="number" name="min_capacity" min="1" value="{{ $minCapacity }}" placeholder="Opsional" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                </div>

                <!-- Recurring Search Inputs -->
                <div x-show="searchMode === 'recurring'" x-cloak class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-zinc-700 mb-1">Frekuensi</label>
                            <select name="frequency" :disabled="searchMode !== 'recurring'" class="w-full px-2.5 py-2 border border-zinc-300 rounded-lg bg-white text-xs">
                                <option value="daily" {{ ($frequency ?? 'daily') === 'daily' ? 'selected' : '' }}>Setiap Hari (Daily)</option>
                                <option value="weekly" {{ ($frequency ?? '') === 'weekly' ? 'selected' : '' }}>Setiap Minggu (Weekly)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-700 mb-1">Dari Tanggal *</label>
                            <input type="date" name="start_date" :disabled="searchMode !== 'recurring'" value="{{ $startDate ?? now()->format('Y-m-d') }}" class="w-full px-2.5 py-2 border border-zinc-300 rounded-lg text-xs font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-700 mb-1">Sampai Tanggal *</label>
                            <input type="date" name="end_date" :disabled="searchMode !== 'recurring'" value="{{ $endDate ?? now()->addDays(4)->format('Y-m-d') }}" class="w-full px-2.5 py-2 border border-zinc-300 rounded-lg text-xs font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-700 mb-1">Jam Mulai Harian *</label>
                            <input type="time" name="start_time_of_day" :disabled="searchMode !== 'recurring'" value="{{ $startTimeOfDay ?? '11:00' }}" class="w-full px-2.5 py-2 border border-zinc-300 rounded-lg text-xs font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-700 mb-1">Jam Selesai Harian *</label>
                            <input type="time" name="end_time_of_day" :disabled="searchMode !== 'recurring'" value="{{ $endTimeOfDay ?? '12:00' }}" class="w-full px-2.5 py-2 border border-zinc-300 rounded-lg text-xs font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-700 mb-1">Kapasitas Min</label>
                            <input type="number" name="min_capacity" min="1" :disabled="searchMode !== 'recurring'" value="{{ $minCapacity }}" placeholder="Opsional" class="w-full px-2.5 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-zinc-100">
                    <span class="text-[11px] text-zinc-400">Pencarian cerdas memvalidasi jam operasional harian, buffer antar meeting, dan memisahkan slot harian tanpa bentrok jam lain.</span>
                    <button type="submit" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white font-medium rounded-lg text-xs shadow-sm transition inline-flex items-center justify-center space-x-2">
                        <svg class="w-3.5 h-3.5 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <span>Cari Ketersediaan</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Search Results Section -->
        @if($hasSearched)
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-zinc-900">
                        Hasil Ruang Tersedia ({{ $availableRooms->count() }})
                    </h2>
                    <span class="text-xs text-zinc-500 font-mono">
                        @if($searchType === 'recurring')
                            Rentang Berseri: {{ $startDate }} s/d {{ $endDate }}, Jam: {{ $startTimeOfDay }} &ndash; {{ $endTimeOfDay }} ({{ $frequency ?? 'daily' }})
                        @else
                            Rentang: {{ Carbon\Carbon::parse($startTime)->format('d M Y, H:i') }} &ndash; {{ Carbon\Carbon::parse($endTime)->format('H:i') }}
                        @endif
                    </span>
                </div>

                @if($availableRooms->isEmpty())
                    <div class="bg-white rounded-2xl border border-zinc-200 p-12 text-center shadow-sm">
                        <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 flex items-center justify-center text-zinc-400 mb-3">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-zinc-900 text-sm">Tidak Ada Ruangan yang Tersedia</h3>
                        <p class="text-zinc-500 text-xs mt-1 max-w-md mx-auto">
                            Semua ruangan pada rentang waktu ini sedang dipesan, di luar jam operasional, atau dalam masa jeda pembersihan (*buffer*).
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        @foreach($availableRooms as $room)
                            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm hover:border-zinc-300 transition p-5 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-start justify-between mb-1.5">
                                        <h3 class="font-semibold text-base text-zinc-950">{{ $room->name }}</h3>
                                        <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                            Tersedia
                                        </span>
                                    </div>
                                    <p class="text-xs text-zinc-500 mb-3">{{ $room->location }}</p>

                                    <div class="grid grid-cols-2 gap-2 bg-zinc-50 p-2.5 rounded-lg border border-zinc-100 text-xs mb-4">
                                        <div>
                                            <span class="text-zinc-400 block text-[11px]">Kapasitas</span>
                                            <span class="font-medium text-zinc-800">{{ $room->capacity }} Orang</span>
                                        </div>
                                        <div>
                                            <span class="text-zinc-400 block text-[11px]">Jeda Buffer</span>
                                            <span class="font-medium text-zinc-800">{{ $room->buffer_minutes }} Menit</span>
                                        </div>
                                    </div>

                                    <div class="text-[11px] text-zinc-500 mb-4">
                                        @if($room->operatingHours->isEmpty())
                                            <span class="inline-flex items-center text-zinc-600">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                                Operasional 24 Jam
                                            </span>
                                        @else
                                            <span class="inline-flex items-center text-zinc-600">
                                                <span class="w-1.5 h-1.5 rounded-full bg-zinc-400 mr-1.5"></span>
                                                Jam Operasional Terjadwal ({{ $room->operatingHours->count() }} hari)
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                @php
                                    if ($searchType === 'recurring') {
                                        $bookUrl = route('web.rooms.show', $room->id) . '?' . http_build_query([
                                            'mode' => 'recurring',
                                            'frequency' => $frequency ?? 'daily',
                                            'start_date' => $startDate,
                                            'end_date' => $endDate,
                                            'start_time_of_day' => $startTimeOfDay,
                                            'end_time_of_day' => $endTimeOfDay,
                                        ]);
                                    } else {
                                        $bookUrl = route('web.rooms.show', $room->id) . '?' . http_build_query([
                                            'mode' => 'single',
                                            'start_time' => $startTime,
                                            'end_time' => $endTime,
                                        ]);
                                    }
                                @endphp

                                <a href="{{ $bookUrl }}" class="w-full py-2 bg-zinc-900 hover:bg-zinc-800 text-white font-medium rounded-lg text-xs text-center shadow-sm transition inline-flex items-center justify-center space-x-1.5">
                                    <span>Pesan Ruang Ini</span>
                                    <svg class="w-3.5 h-3.5 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <!-- Initial State Prompt -->
            <div class="bg-white rounded-xl border border-dashed border-zinc-300 p-10 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 flex items-center justify-center text-zinc-400 mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-zinc-900 text-sm">Tentukan Jadwal Rapat Anda</h3>
                <p class="text-zinc-500 text-xs mt-1 max-w-md mx-auto">
                    Pilih mode <strong>Sekali (Single Slot)</strong> untuk satu kali meeting, atau <strong>Berulang (Rentang Hari & Jam)</strong> untuk meeting berseri (misal tanggal 1 s/d 5 setiap jam 11:00 &ndash; 12:00).
                </p>
            </div>
        @endif
    </div>
</x-layouts.meeting>

