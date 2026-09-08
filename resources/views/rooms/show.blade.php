<x-layouts.meeting :activeUser="$activeUser" :users="$users" :title="'Jadwal ' . $room->name">
    <div x-data="{
        bookingMode: '{{ old('is_recurring') === '1' || request('mode') === 'recurring' ? 'recurring' : 'single' }}',
        bookingScope: 'all',
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
            <span class="font-medium text-zinc-800">{{ $room->name }}</span>
        </div>

        <!-- Room Header Card -->
        <div class="bg-white rounded-2xl border border-zinc-200 p-6 sm:p-7 shadow-sm mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-6 border-b border-zinc-100">
                <div>
                    <div class="flex items-center space-x-3 mb-1.5">
                        <h1 class="text-xl sm:text-2xl font-bold text-zinc-950 tracking-tight">{{ $room->name }}</h1>
                        <span class="px-2.5 py-0.5 rounded-md text-xs font-medium {{ $room->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' : 'bg-zinc-100 text-zinc-600 border border-zinc-200' }}">
                            {{ $room->is_active ? 'Aktif' : 'Non-Aktif' }}
                        </span>
                    </div>
                    <p class="text-xs text-zinc-500">
                        Lokasi: <span class="font-medium text-zinc-700">{{ $room->location }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2.5">
                    <div class="bg-zinc-50 border border-zinc-200 px-3.5 py-2 rounded-xl text-xs">
                        <span class="text-zinc-400 block font-normal">Kapasitas Maksimal</span>
                        <span class="text-xs font-semibold text-zinc-800">{{ $room->capacity }} Orang</span>
                    </div>
                    <div class="bg-zinc-50 border border-zinc-200 px-3.5 py-2 rounded-xl text-xs">
                        <span class="text-zinc-400 block font-normal">Jeda Pembersihan</span>
                        <span class="text-xs font-semibold text-zinc-800">{{ $room->buffer_minutes }} Menit</span>
                    </div>
                </div>
            </div>

            <!-- Operating Hours Info -->
            <div class="pt-4 flex flex-col sm:flex-row sm:items-center justify-between text-xs text-zinc-600 gap-2">
                <div>
                    <span class="font-medium text-zinc-700">Jam Operasional:</span>
                    @if($room->operatingHours->isEmpty())
                        <span class="text-zinc-400 ml-1">24 Jam (Tanpa batasan jadwal)</span>
                    @else
                        <span class="text-zinc-800 ml-1 font-mono">
                            Senin - Jumat ({{ substr($room->operatingHours->first()->open_time, 0, 5) }} - {{ substr($room->operatingHours->first()->close_time, 0, 5) }})
                        </span>
                    @endif
                </div>
                <span class="text-zinc-400 font-mono text-[11px]">ID Ruang: #{{ $room->id }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- Left Column: Form Reservasi Baru (5 Cols) -->
            <div class="lg:col-span-5 xl:col-span-4">
                <div class="bg-white rounded-2xl border border-zinc-200 p-6 shadow-sm sticky top-24">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-zinc-100">
                        <h2 class="font-bold text-sm text-zinc-900">Reservasi Ruangan</h2>
                        <span class="text-[11px] text-zinc-400 font-medium">Formulir Pemesanan</span>
                    </div>

                    <!-- Booking Type Switcher Tabs -->
                    <div class="grid grid-cols-2 gap-1 bg-zinc-100 p-1 rounded-lg mb-4 text-xs font-medium">
                        <button type="button" @click="bookingMode = 'single'" :class="bookingMode === 'single' ? 'bg-white text-zinc-900 shadow-sm font-semibold' : 'text-zinc-600 hover:text-zinc-900'" class="py-1.5 rounded-md transition text-center">
                            Sekali (Single)
                        </button>
                        <button type="button" @click="bookingMode = 'recurring'" :class="bookingMode === 'recurring' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-600 hover:text-zinc-900'" class="py-1.5 rounded-md transition text-center">
                            Berulang (Recurring)
                        </button>
                    </div>

                    <form action="{{ route('web.bookings.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="room_id" value="{{ $room->id }}">
                        <!-- Single definitive input for is_recurring -->
                        <input type="hidden" name="is_recurring" :value="bookingMode === 'recurring' ? '1' : '0'">

                        <div>
                            <label class="block text-xs font-medium text-zinc-700 mb-1">Judul Meeting *</label>
                            <input type="text" name="title" value="{{ old('title') }}" required placeholder="Contoh: Diskusi Perencanaan Tim" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:border-zinc-900 focus:outline-none">
                        </div>

                        <!-- Single Booking Mode Inputs -->
                        <div x-show="bookingMode === 'single'" class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-zinc-700 mb-1">Waktu Mulai *</label>
                                <input type="datetime-local" name="start_time" :disabled="bookingMode !== 'single'" value="{{ old('start_time', request('start_time') ? \Carbon\Carbon::parse(request('start_time'))->format('Y-m-d\TH:i') : now()->addHour()->format('Y-m-d\TH:00')) }}" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs font-mono focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-zinc-700 mb-1">Waktu Selesai *</label>
                                <input type="datetime-local" name="end_time" :disabled="bookingMode !== 'single'" value="{{ old('end_time', request('end_time') ? \Carbon\Carbon::parse(request('end_time'))->format('Y-m-d\TH:i') : now()->addHours(2)->format('Y-m-d\TH:00')) }}" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs font-mono focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                                <span class="text-[11px] text-zinc-400 block mt-1">Waktu selesai harus setelah waktu mulai.</span>
                            </div>
                        </div>

                        <!-- Recurring Booking Mode Inputs -->
                        <div x-show="bookingMode === 'recurring'" x-cloak class="space-y-3 bg-zinc-50 p-3.5 rounded-xl border border-zinc-200 text-xs">
                            <div>
                                <label class="block font-medium text-zinc-700 mb-1">Frekuensi Pengulangan</label>
                                <select name="frequency" :disabled="bookingMode !== 'recurring'" class="w-full px-2.5 py-1.5 border border-zinc-300 rounded-md bg-white text-xs">
                                    <option value="daily" {{ old('frequency', request('frequency', 'daily')) === 'daily' ? 'selected' : '' }}>Setiap Hari (Daily)</option>
                                    <option value="weekly" {{ old('frequency', request('frequency')) === 'weekly' ? 'selected' : '' }}>Setiap Minggu (Weekly)</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-medium text-zinc-700 mb-1">Dari Tanggal</label>
                                    <input type="date" name="start_date" :disabled="bookingMode !== 'recurring'" value="{{ old('start_date', request('start_date', now()->format('Y-m-d'))) }}" class="w-full px-2 py-1.5 border border-zinc-300 rounded-md bg-white text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block font-medium text-zinc-700 mb-1">Sampai Tanggal</label>
                                    <input type="date" name="end_date" :disabled="bookingMode !== 'recurring'" value="{{ old('end_date', request('end_date', now()->addDays(4)->format('Y-m-d'))) }}" class="w-full px-2 py-1.5 border border-zinc-300 rounded-md bg-white text-xs font-mono">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-medium text-zinc-700 mb-1">Jam Mulai Harian</label>
                                    <input type="time" name="start_time_of_day" :disabled="bookingMode !== 'recurring'" value="{{ old('start_time_of_day', request('start_time_of_day', '09:00')) }}" class="w-full px-2 py-1.5 border border-zinc-300 rounded-md bg-white text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block font-medium text-zinc-700 mb-1">Jam Selesai Harian</label>
                                    <input type="time" name="end_time_of_day" :disabled="bookingMode !== 'recurring'" value="{{ old('end_time_of_day', request('end_time_of_day', '10:00')) }}" class="w-full px-2 py-1.5 border border-zinc-300 rounded-md bg-white text-xs font-mono">
                                </div>
                            </div>
                            <div>
                                <label class="block font-medium text-zinc-700 mb-1">Pengecualian Tanggal (Opsional)</label>
                                <input type="text" name="exception_dates" :disabled="bookingMode !== 'recurring'" value="{{ old('exception_dates') }}" placeholder="YYYY-MM-DD, YYYY-MM-DD" class="w-full px-2.5 py-1.5 border border-zinc-300 rounded-md bg-white text-xs font-mono">
                                <span class="text-[10px] text-zinc-400 block mt-0.5">Pisahkan koma untuk tanggal libur/skip.</span>
                            </div>
                        </div>

                        <!-- Occupied Slots Visualizer
                        <div class="pt-1 border-t border-zinc-100">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xs font-semibold text-zinc-800">Jadwal Terisi pada Ruangan Ini:</span>
                                <span class="text-[10px] text-zinc-400 font-mono">{{ $allConfirmedBookings->count() }} slot</span>
                            </div>

                            <div class="max-h-36 overflow-y-auto space-y-1.5 pr-1 border border-zinc-100 p-2 rounded-xl bg-zinc-50/50">
                                @forelse($allConfirmedBookings as $cb)
                                    <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-zinc-200 text-xs">
                                        <div>
                                            <span class="font-semibold text-zinc-900 font-mono text-[11px]">
                                                {{ $cb->start_time->format('d M') }} &bull; {{ $cb->start_time->format('H:i') }} - {{ $cb->end_time->format('H:i') }}
                                            </span>
                                            <span class="text-zinc-500 block text-[10px] truncate max-w-[140px]">{{ $cb->title }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[10px] text-zinc-500 block">{{ $cb->user?->name ?? 'User #' . $cb->user_id }}</span>
                                            @if($room->buffer_minutes > 0)
                                                <span class="text-[9px] bg-zinc-100 text-zinc-600 px-1 py-0.5 rounded font-mono border border-zinc-200">+{{ $room->buffer_minutes }}m</span>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-[11px] text-zinc-500 bg-white p-2.5 rounded-lg border border-zinc-200 text-center">
                                        Belum ada jadwal terisi pada ruangan ini.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                        -->

                        <!-- Active User Context -->
                        <div class="bg-zinc-50 border border-zinc-200 p-3 rounded-xl text-xs flex items-center justify-between text-zinc-700">
                            <div>
                                <span class="text-zinc-400 block text-[10px]">Pemesan:</span>
                                <span class="font-semibold text-zinc-900">{{ $activeUser->name }}</span>
                                <span class="text-zinc-400 font-mono text-[11px]">(ID: {{ $activeUser->id }})</span>
                            </div>
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-medium rounded-xl text-xs transition shadow-sm">
                            Reservasi Ruang Ini
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Column: Daftar Jadwal Booking & Filter (7-8 Cols) -->
            <div class="lg:col-span-7 xl:col-span-8">

                <!-- Filter Bar -->
                <div class="bg-white p-5 rounded-2xl border border-zinc-200 shadow-sm mb-6">
                    <h3 class="font-semibold text-xs text-zinc-700 mb-3 uppercase tracking-wider">
                        Filter Jadwal Reservasi
                    </h3>
                    <form action="{{ route('web.rooms.show', $room->id) }}" method="GET" class="flex flex-wrap items-end gap-3 text-xs">
                        <div>
                            <label class="block text-zinc-500 font-medium mb-1">Tanggal Spesifik:</label>
                            <input type="date" name="date" value="{{ $dateFilter }}" class="px-2.5 py-1.5 border border-zinc-300 rounded-lg text-xs font-mono focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                        </div>
                        <div class="text-zinc-300 font-medium self-center pt-3 text-xs">atau</div>
                        <div>
                            <label class="block text-zinc-500 font-medium mb-1">Dari Tanggal:</label>
                            <input type="date" name="start_date" value="{{ $startDateFilter }}" class="px-2.5 py-1.5 border border-zinc-300 rounded-lg text-xs font-mono focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-zinc-500 font-medium mb-1">Sampai Tanggal:</label>
                            <input type="date" name="end_date" value="{{ $endDateFilter }}" class="px-2.5 py-1.5 border border-zinc-300 rounded-lg text-xs font-mono focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                        </div>
                        <button type="submit" class="px-3.5 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white font-medium rounded-lg transition text-xs">
                            Filter
                        </button>
                        @if($dateFilter || $startDateFilter || $endDateFilter)
                            <a href="{{ route('web.rooms.show', $room->id) }}" class="px-2 py-1.5 text-zinc-500 hover:text-zinc-900 hover:underline text-xs self-center">
                                Reset Filter
                            </a>
                        @endif
                    </form>
                </div>

                <!-- Bookings Schedule List -->
                <div class="space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
                        <div>
                            <h2 class="font-bold text-sm text-zinc-900">
                                Daftar Booking Ruangan
                            </h2>
                            <span class="text-[11px] text-zinc-400">Urut berdasarkan waktu mulai</span>
                        </div>

                        <!-- Scope Switcher: Semua vs Booking Saya -->
                        <div class="inline-flex p-1 bg-zinc-100 rounded-xl border border-zinc-200 text-xs self-start sm:self-auto">
                            <button
                                type="button"
                                @click="bookingScope = 'all'"
                                :class="bookingScope === 'all' ? 'bg-white text-zinc-900 shadow-sm font-semibold' : 'text-zinc-500 hover:text-zinc-800 font-medium'"
                                class="px-3 py-1 rounded-lg transition"
                            >
                                Semua ({{ $bookings->count() }})
                            </button>
                            <button
                                type="button"
                                @click="bookingScope = 'mine'"
                                :class="bookingScope === 'mine' ? 'bg-white text-zinc-900 shadow-sm font-semibold' : 'text-zinc-500 hover:text-zinc-800 font-medium'"
                                class="px-3 py-1 rounded-lg transition"
                            >
                                Booking Saya ({{ $bookings->where('user_id', $activeUser->id)->count() }})
                            </button>
                        </div>
                    </div>

                    @if($bookings->isEmpty())
                        <div class="bg-white rounded-2xl border border-zinc-200 p-12 text-center">
                            <h4 class="font-semibold text-zinc-800 text-sm">Tidak Ada Booking pada Rentang Ini</h4>
                            <p class="text-zinc-500 text-xs mt-1">Ruangan ini tidak memiliki jadwal reservasi untuk waktu yang dipilih.</p>
                        </div>
                    @else
                        @foreach($bookings as $b)
                            @php
                                $isOwner = ($activeUser->id === $b->user_id);
                                $isConfirmed = $b->isConfirmed();
                            @endphp
                            <div
                                x-show="bookingScope === 'all' || (bookingScope === 'mine' && {{ $isOwner ? 'true' : 'false' }})"
                                class="bg-white rounded-xl border border-zinc-200 p-4 transition hover:border-zinc-300"
                            >
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                    <div>
                                        <div class="flex items-center space-x-2 mb-1">
                                            <h3 class="font-semibold text-sm text-zinc-900 {{ !$isConfirmed ? 'line-through text-zinc-400' : '' }}">
                                                {{ $b->title }}
                                            </h3>
                                            <!-- Status Badge -->
                                            <span class="px-2 py-0.5 rounded text-[10px] font-medium {{ $isConfirmed ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-zinc-100 text-zinc-500 border border-zinc-200' }}">
                                                {{ $isConfirmed ? 'Confirmed' : 'Cancelled' }}
                                            </span>
                                            <!-- Ownership Badge -->
                                            @if($isOwner)
                                                <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-zinc-100 text-zinc-800 border border-zinc-200">
                                                    Milik Anda
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Time & Date -->
                                        <div class="flex flex-wrap items-center gap-x-3 text-xs text-zinc-600 mt-1.5 font-mono">
                                            <span class="font-medium text-zinc-900">
                                                {{ $b->start_time->format('d M Y') }}
                                            </span>
                                            <span>
                                                {{ $b->start_time->format('H:i') }} - {{ $b->end_time->format('H:i') }} (UTC)
                                            </span>
                                            <span class="text-zinc-400 text-[11px]">
                                                ({{ $b->start_time->diffInMinutes($b->end_time) }} menit)
                                            </span>
                                        </div>

                                        <!-- Organizer Info -->
                                        <div class="text-[11px] text-zinc-500 mt-1.5">
                                            Penyelenggara: <span class="font-medium text-zinc-700">{{ $b->user?->name ?? 'User #' . $b->user_id }}</span>
                                            <span class="text-zinc-400 font-mono">(ID: {{ $b->user_id }})</span>
                                        </div>

                                        @if($b->isCancelled() && $b->cancellation_reason)
                                            <div class="mt-2 text-xs text-zinc-600 bg-zinc-50 px-2.5 py-1 rounded border border-zinc-200">
                                                <span class="font-medium text-zinc-700">Alasan Batal:</span> {{ $b->cancellation_reason }}
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex items-center space-x-2 self-end sm:self-start pt-1 sm:pt-0">
                                        <!-- Audit Trail Button -->
                                        <button
                                            @click="selectedBooking = {{ $b->load('histories.user')->toJson() }}; historyModalOpen = true"
                                            title="Lihat Riwayat Perubahan"
                                            class="px-2.5 py-1 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-700 text-xs font-medium transition"
                                        >
                                            Riwayat ({{ $b->histories->count() }})
                                        </button>

                                        @if($isConfirmed)
                                            @if($isOwner)
                                                <!-- Reschedule Button (Owner only) -->
                                                <button
                                                    @click="selectedBooking = {{ $b->toJson() }}; rescheduleModalOpen = true"
                                                    class="px-2.5 py-1 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-700 text-xs font-medium transition"
                                                >
                                                    Reschedule
                                                </button>

                                                <!-- Cancel Button (Owner only) -->
                                                <button
                                                    @click="selectedBooking = {{ $b->toJson() }}; cancelModalOpen = true"
                                                    class="px-2.5 py-1 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-medium transition"
                                                >
                                                    Batalkan
                                                </button>
                                            @else
                                                <!-- Disabled badge for non-owner -->
                                                <span
                                                    title="Hanya pemilik booking yang dapat membatalkan atau mengubah jadwal."
                                                    class="px-2.5 py-1 rounded-lg bg-zinc-50 text-zinc-400 text-xs font-normal border border-zinc-200 cursor-not-allowed"
                                                >
                                                    Bukan Milik Anda
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        @if($bookings->where('user_id', $activeUser->id)->isEmpty())
                            <div
                                x-show="bookingScope === 'mine'"
                                x-cloak
                                class="bg-white rounded-2xl border border-zinc-200 p-8 text-center"
                            >
                                <h4 class="font-semibold text-zinc-800 text-sm">Belum Ada Reservasi Anda</h4>
                                <p class="text-zinc-500 text-xs mt-1">Anda belum memiliki jadwal reservasi di ruangan ini untuk rentang tanggal yang dipilih.</p>
                            </div>
                        @endif
                    @endif
                </div>

            </div>
        </div>

        <!-- =================== MODAL SECTION (MINIMALIST) =================== -->

        <!-- Modal 1: Interaktif Peringatan Konflik Jadwal (HTTP 409 Conflict) -->
        <div x-show="conflictModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="conflictModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-zinc-200">
                <div class="flex items-start justify-between pb-3 border-b border-zinc-100 mb-4">
                    <div>
                        <div class="flex items-center space-x-2">
                            <h3 class="font-bold text-base text-zinc-950">Konflik Jadwal Reservasi</h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-medium bg-rose-50 text-rose-700 border border-rose-200">409 Conflict</span>
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
                        <!-- Comparison Details Box -->
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

                    @if(session('occupied_slots_today') && count(session('occupied_slots_today')) > 0)
                        <!-- Occupied Slots on Date -->
                        <div class="border border-zinc-200 rounded-xl p-3 bg-zinc-50">
                            <span class="font-semibold text-zinc-800 block mb-2">
                                Jadwal yang Sudah Terisi pada Tanggal Ini ({{ session('conflicting_date') }}):
                            </span>
                            <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                                @foreach(session('occupied_slots_today') as $slot)
                                    @php
                                        $slotStart = is_array($slot) ? ($slot['start_formatted'] ?? '') : $slot->start_time->format('H:i');
                                        $slotEnd = is_array($slot) ? ($slot['end_formatted'] ?? '') : $slot->end_time->format('H:i');
                                        $slotTitle = is_array($slot) ? ($slot['title'] ?? '') : $slot->title;
                                        $slotUser = is_array($slot) ? ($slot['user_name'] ?? '') : ($slot->user?->name ?? 'User #' . $slot->user_id);
                                    @endphp
                                    <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-zinc-200 text-xs">
                                        <span class="font-mono font-medium text-zinc-900">
                                            {{ $slotStart }} - {{ $slotEnd }}
                                        </span>
                                        <span class="text-zinc-600 truncate max-w-[150px]">{{ $slotTitle }} ({{ $slotUser }})</span>
                                        @if($room->buffer_minutes > 0)
                                            <span class="text-[9px] bg-zinc-100 text-zinc-600 px-1 py-0.5 rounded font-mono border border-zinc-200">+{{ $room->buffer_minutes }}m buffer</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="text-[11px] text-zinc-500 bg-white p-2.5 rounded-lg border border-zinc-200">
                        Rekomendasi: Silakan pilih jam lain di luar rentang jadwal yang sudah terisi di atas.
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-100 mt-4">
                    <button type="button" @click="conflictModalOpen = false" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-lg text-xs font-medium transition">
                        Tutup & Ubah Jam Reservasi
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal 2: Pembatalan Booking (Otorisasi Pemilik) -->
        <div x-show="cancelModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="cancelModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-zinc-200">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-sm text-zinc-900">Konfirmasi Pembatalan Booking</h3>
                    <button @click="cancelModalOpen = false" class="text-zinc-400 hover:text-zinc-600 text-lg">&times;</button>
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
                        <button type="button" @click="cancelModalOpen = false" class="px-3.5 py-1.5 border border-zinc-300 rounded-lg text-zinc-700 text-xs font-medium hover:bg-zinc-50">Batal</button>
                        <button type="submit" class="px-4 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-medium transition">Batalkan Booking</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal 3: Reschedule Booking -->
        <div x-show="rescheduleModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="rescheduleModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-zinc-200">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-sm text-zinc-900">Ubah Jadwal (Reschedule)</h3>
                    <button @click="rescheduleModalOpen = false" class="text-zinc-400 hover:text-zinc-600 text-lg">&times;</button>
                </div>
                <form :action="'/bookings/' + (selectedBooking ? selectedBooking.id : '') + '/reschedule'" method="POST" class="space-y-3.5 text-xs">
                    @csrf
                    <p class="text-zinc-500">
                        Mengubah jadwal meeting: <span class="font-semibold text-zinc-800" x-text="selectedBooking ? selectedBooking.title : ''"></span>
                    </p>
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Waktu Mulai Baru *</label>
                        <input type="datetime-local" name="reschedule_start" required class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs font-mono focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Waktu Selesai Baru *</label>
                        <input type="datetime-local" name="reschedule_end" required class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs font-mono focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Alasan Perubahan</label>
                        <input type="text" name="reschedule_reason" placeholder="Contoh: Menyesuaikan waktu peserta" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                    <div class="flex justify-end space-x-2.5 pt-3 border-t border-zinc-100">
                        <button type="button" @click="rescheduleModalOpen = false" class="px-3.5 py-1.5 border border-zinc-300 rounded-lg text-zinc-700 text-xs font-medium hover:bg-zinc-50">Batal</button>
                        <button type="submit" class="px-4 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white rounded-lg text-xs font-medium transition">Simpan Jadwal Baru</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal 4: Audit Trail / Riwayat Perubahan -->
        <div x-show="historyModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="historyModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-zinc-200">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-zinc-100">
                    <div>
                        <h3 class="font-bold text-sm text-zinc-900">Riwayat Perubahan (Audit Trail)</h3>
                        <p class="text-xs text-zinc-500 mt-0.5" x-text="'Booking: ' + (selectedBooking ? selectedBooking.title : '')"></p>
                    </div>
                    <button @click="historyModalOpen = false" class="text-zinc-400 hover:text-zinc-600 text-lg">&times;</button>
                </div>

                <div class="space-y-2.5 max-h-96 overflow-y-auto pr-1">
                    <template x-if="selectedBooking && selectedBooking.histories && selectedBooking.histories.length > 0">
                        <template x-for="(h, idx) in selectedBooking.histories" :key="idx">
                            <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-200 text-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-mono text-[10px] uppercase font-semibold px-2 py-0.5 rounded"
                                          :class="{
                                              'bg-emerald-50 text-emerald-700 border border-emerald-200': h.action === 'created',
                                              'bg-zinc-100 text-zinc-800 border border-zinc-200': h.action === 'rescheduled',
                                              'bg-rose-50 text-rose-700 border border-rose-200': h.action === 'cancelled'
                                          }"
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
                    <button type="button" @click="historyModalOpen = false" class="px-4 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white rounded-lg text-xs font-medium">Tutup</button>
                </div>
            </div>
        </div>

    </div>
</x-layouts.meeting>
