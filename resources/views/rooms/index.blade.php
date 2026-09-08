<x-layouts.meeting :activeUser="$activeUser" :users="$users" title="Daftar Ruang Meeting">
    <div x-data="{
        createModalOpen: false,
        editModalOpen: false,
        hoursModalOpen: false,
        deleteModalOpen: false,
        currentRoom: {},
        roomToDelete: null,
        operatingHoursList: [],
        daysOptions: [
            { id: 1, name: 'Senin' },
            { id: 2, name: 'Selasa' },
            { id: 3, name: 'Rabu' },
            { id: 4, name: 'Kamis' },
            { id: 5, name: 'Jumat' },
            { id: 6, name: 'Sabtu' },
            { id: 0, name: 'Minggu' }
        ],
        openEditModal(room) {
            this.currentRoom = Object.assign({}, room);
            this.editModalOpen = true;
        },
        openHoursModal(room) {
            this.currentRoom = room;
            if (room.operating_hours && room.operating_hours.length > 0) {
                this.operatingHoursList = room.operating_hours.map(h => ({
                    day_of_week: parseInt(h.day_of_week),
                    open_time: h.open_time.substring(0, 5),
                    close_time: h.close_time.substring(0, 5)
                }));
            } else {
                this.operatingHoursList = [];
            }
            this.hoursModalOpen = true;
        },
        addOperatingDay() {
            const usedDays = this.operatingHoursList.map(h => h.day_of_week);
            const nextDay = this.daysOptions.find(d => !usedDays.includes(d.id)) || this.daysOptions[0];
            this.operatingHoursList.push({
                day_of_week: nextDay.id,
                open_time: '08:00',
                close_time: '18:00'
            });
        },
        removeOperatingDay(index) {
            this.operatingHoursList.splice(index, 1);
        },
        confirmDeleteRoom(room) {
            this.roomToDelete = room;
            this.deleteModalOpen = true;
        }
    }">

        <!-- Page Header & Action Bar -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-zinc-950 tracking-tight">Daftar Ruang Meeting</h1>
                <p class="text-xs text-zinc-500 mt-1">Kelola konfigurasi ruangan, kapasitas, jeda pembersihan (buffer), dan jam operasional.</p>
            </div>
            <div class="flex items-center space-x-2.5">
                <a href="{{ route('web.rooms.search') }}" class="px-3.5 py-2 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-700 font-medium text-xs shadow-sm transition inline-flex items-center space-x-2">
                    <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span>Cari Ketersediaan</span>
                </a>
                <button @click="createModalOpen = true" class="px-3.5 py-2 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs shadow-sm transition inline-flex items-center space-x-2">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Tambah Ruang</span>
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white p-3.5 rounded-xl border border-zinc-200 shadow-sm mb-6">
            <form action="{{ route('web.rooms.index') }}" method="GET" class="flex flex-wrap items-center gap-3 text-xs">
                <div class="flex items-center space-x-2">
                    <label class="font-medium text-zinc-600">Kapasitas Minimum:</label>
                    <input type="number" name="min_capacity" min="1" value="{{ request('min_capacity') }}" placeholder="Contoh: 10" class="w-28 px-2.5 py-1.5 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                </div>
                <div class="flex items-center space-x-2">
                    <label class="font-medium text-zinc-600">Status:</label>
                    <select name="is_active" class="px-2.5 py-1.5 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none bg-white">
                        <option value="">Semua Status</option>
                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Hanya Aktif</option>
                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Non-Aktif</option>
                    </select>
                </div>
                <button type="submit" class="px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white font-medium rounded-lg transition text-xs">
                    Terapkan
                </button>
                @if(request()->hasAny(['min_capacity', 'is_active']))
                    <a href="{{ route('web.rooms.index') }}" class="text-xs text-zinc-500 hover:text-zinc-900 underline transition">Reset Filter</a>
                @endif
            </form>
        </div>

        <!-- Room Cards Grid -->
        @if($rooms->isEmpty())
            <div class="bg-white rounded-2xl border border-zinc-200 p-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 flex items-center justify-center text-zinc-400 mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-zinc-900 text-sm">Belum Ada Ruang Meeting</h3>
                <p class="text-zinc-500 text-xs mt-1">Tambahkan ruang meeting baru untuk memulai konfigurasi reservasi.</p>
                <button @click="createModalOpen = true" class="mt-4 px-3.5 py-2 bg-zinc-900 text-white rounded-lg font-medium text-xs hover:bg-zinc-800 transition">
                    Tambah Ruang Sekarang
                </button>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($rooms as $room)
                    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm hover:border-zinc-300 transition flex flex-col justify-between overflow-hidden">
                        <div class="p-5">
                            <!-- Card Header -->
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div>
                                    <h3 class="font-semibold text-base text-zinc-950">{{ $room->name }}</h3>
                                    <p class="text-xs text-zinc-500 mt-0.5">
                                        {{ $room->location }}
                                    </p>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[11px] font-medium {{ $room->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' : 'bg-zinc-100 text-zinc-600 border border-zinc-200' }}">
                                    {{ $room->is_active ? 'Aktif' : 'Non-Aktif' }}
                                </span>
                            </div>

                            <!-- Highlights / Specs -->
                            <div class="grid grid-cols-2 gap-2.5 my-3 bg-zinc-50 p-2.5 rounded-lg border border-zinc-100 text-xs">
                                <div>
                                    <span class="text-zinc-400 block text-[11px]">Kapasitas</span>
                                    <span class="font-medium text-zinc-800">{{ $room->capacity }} Orang</span>
                                </div>
                                <div>
                                    <span class="text-zinc-400 block text-[11px]">Jeda Buffer</span>
                                    <span class="font-medium text-zinc-800">{{ $room->buffer_minutes }} Menit</span>
                                </div>
                            </div>

                            <!-- Operating Hours preview -->
                            <div class="text-xs text-zinc-500 border-t border-zinc-100 pt-3">
                                <span class="font-medium text-zinc-700 block mb-0.5">Jam Operasional:</span>
                                @if($room->operatingHours->isEmpty())
                                    <span class="text-zinc-400 italic text-[11px]">24 Jam Penuh (Tanpa Batasan)</span>
                                @else
                                    <div class="text-zinc-600 text-[11px] space-y-0.5 mt-1">
                                        <span class="font-medium text-zinc-700">{{ $room->operatingHours->count() }} hari operasional:</span>
                                        <div class="font-mono text-[10px] text-zinc-500 flex flex-wrap gap-1">
                                            @php
                                                $dayNames = [0 => 'Min', 1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab'];
                                            @endphp
                                            @foreach($room->operatingHours->sortBy('day_of_week') as $h)
                                                <span class="bg-zinc-100 px-1.5 py-0.5 rounded border border-zinc-200">
                                                    {{ $dayNames[$h->day_of_week] ?? $h->day_of_week }}: {{ substr($h->open_time, 0, 5) }}-{{ substr($h->close_time, 0, 5) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Card Action Footer -->
                        <div class="bg-zinc-50 px-5 py-3 border-t border-zinc-100 flex items-center justify-between">
                            <a href="{{ route('web.rooms.show', $room->id) }}" class="px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-medium rounded-lg transition inline-flex items-center space-x-1.5">
                                <span>Jadwal & Reservasi</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                            <div class="flex items-center space-x-1">
                                <button @click="openEditModal({{ $room->toJson() }})" title="Edit Ruang" class="p-1.5 text-zinc-500 hover:text-zinc-900 hover:bg-white rounded border border-transparent hover:border-zinc-200 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                </button>
                                <button @click="openHoursModal({{ $room->toJson() }})" title="Atur Jam Operasional" class="p-1.5 text-zinc-500 hover:text-zinc-900 hover:bg-white rounded border border-transparent hover:border-zinc-200 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </button>
                                <button type="button" @click="confirmDeleteRoom({{ $room->toJson() }})" title="Hapus Ruang" class="p-1.5 text-zinc-400 hover:text-rose-600 hover:bg-white rounded border border-transparent hover:border-zinc-200 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Modal 1: Tambah Ruang Baru -->
        <div x-show="createModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="createModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-zinc-200">
                <div class="flex justify-between items-center pb-4 border-b border-zinc-100">
                    <div>
                        <h3 class="font-semibold text-base text-zinc-950">Tambah Ruang Meeting Baru</h3>
                        <p class="text-xs text-zinc-500">Konfigurasikan detail dan kapasitas ruang</p>
                    </div>
                    <button @click="createModalOpen = false" class="text-zinc-400 hover:text-zinc-600 text-lg leading-none p-1">&times;</button>
                </div>
                <form action="{{ route('web.rooms.store') }}" method="POST" class="space-y-4 pt-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">Nama Ruang *</label>
                        <input type="text" name="name" required placeholder="Contoh: Ruang Rinjani" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-zinc-700 mb-1">Kapasitas (Orang) *</label>
                            <input type="number" name="capacity" min="1" required placeholder="12" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-700 mb-1">Jeda Buffer (Menit)</label>
                            <input type="number" name="buffer_minutes" min="0" value="0" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">Lokasi Ruang *</label>
                        <input type="text" name="location" required placeholder="Contoh: Lantai 2, Sayap Barat" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">Status Ruangan *</label>
                        <select name="is_active" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none bg-white font-medium">
                            <option value="1" selected>Aktif & Siap Digunakan</option>
                            <option value="0">Non-Aktif (Ditutup Sementara)</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-2.5 pt-4 border-t border-zinc-100">
                        <button type="button" @click="createModalOpen = false" class="px-3.5 py-2 border border-zinc-200 rounded-lg text-zinc-700 text-xs font-medium hover:bg-zinc-50 transition">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-lg text-xs font-medium shadow-sm transition">Simpan Ruang</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal 2: Edit Ruang -->
        <div x-show="editModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="editModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-zinc-200">
                <div class="flex justify-between items-center pb-4 border-b border-zinc-100">
                    <div>
                        <h3 class="font-semibold text-base text-zinc-950">Edit Data Ruang Meeting</h3>
                        <p class="text-xs text-zinc-500">Perbarui informasi ruangan</p>
                    </div>
                    <button @click="editModalOpen = false" class="text-zinc-400 hover:text-zinc-600 text-lg leading-none p-1">&times;</button>
                </div>
                <form :action="'/rooms/' + currentRoom.id + '/update'" method="POST" class="space-y-4 pt-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">Nama Ruang *</label>
                        <input type="text" name="name" :value="currentRoom.name" required class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-zinc-700 mb-1">Kapasitas (Orang) *</label>
                            <input type="number" name="capacity" :value="currentRoom.capacity" min="1" required class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-700 mb-1">Jeda Buffer (Menit)</label>
                            <input type="number" name="buffer_minutes" :value="currentRoom.buffer_minutes" min="0" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">Lokasi Ruang *</label>
                        <input type="text" name="location" :value="currentRoom.location" required class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">Status Ruangan *</label>
                        <select name="is_active" x-model.number="currentRoom.is_active" class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none bg-white font-medium">
                            <option :value="1">Aktif & Siap Digunakan</option>
                            <option :value="0">Non-Aktif (Ditutup Sementara)</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-2.5 pt-4 border-t border-zinc-100">
                        <button type="button" @click="editModalOpen = false" class="px-3.5 py-2 border border-zinc-200 rounded-lg text-zinc-700 text-xs font-medium hover:bg-zinc-50 transition">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-lg text-xs font-medium shadow-sm transition">Perbarui Ruang</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal 3: Atur Jam Operasional Fleksibel (Tambah/Hapus Hari) -->
        <div x-show="hoursModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="hoursModalOpen = false" class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-xl border border-zinc-200">
                <div class="flex justify-between items-center pb-3 border-b border-zinc-100">
                    <div>
                        <h3 class="font-semibold text-base text-zinc-950">Konfigurasi Jam Operasional</h3>
                        <p class="text-xs text-zinc-500">Ruangan: <span class="font-medium text-zinc-900" x-text="currentRoom.name"></span></p>
                    </div>
                    <button @click="hoursModalOpen = false" class="text-zinc-400 hover:text-zinc-600 text-lg leading-none p-1">&times;</button>
                </div>

                <form :action="'/rooms/' + currentRoom.id + '/operating-hours'" method="POST" class="pt-4">
                    @csrf

                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-zinc-100">
                        <span class="text-xs text-zinc-500 font-medium">Daftar Hari & Waktu Operasional:</span>
                        <button type="button" @click="addOperatingDay()" class="px-2.5 py-1.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-medium rounded-lg transition inline-flex items-center space-x-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Tambah Hari</span>
                        </button>
                    </div>

                    <div class="space-y-2.5 max-h-80 overflow-y-auto pr-1">
                        <template x-for="(item, index) in operatingHoursList" :key="index">
                            <div class="flex items-center justify-between gap-2.5 bg-zinc-50 p-2.5 rounded-xl border border-zinc-200 text-xs">
                                <div class="w-32">
                                    <select :name="'hours[' + index + '][day_of_week]'" x-model.number="item.day_of_week" class="w-full border border-zinc-300 rounded-lg px-2.5 py-1.5 text-xs bg-white font-medium focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                                        <template x-for="day in daysOptions" :key="day.id">
                                            <option :value="day.id" x-text="day.name" :selected="day.id === item.day_of_week"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="flex items-center space-x-1.5">
                                    <span class="text-zinc-400 text-[11px]">Buka:</span>
                                    <input type="time" :name="'hours[' + index + '][open_time]'" x-model="item.open_time" required class="border border-zinc-300 rounded-lg px-2 py-1.5 text-xs font-mono bg-white focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                                </div>
                                <div class="flex items-center space-x-1.5">
                                    <span class="text-zinc-400 text-[11px]">Tutup:</span>
                                    <input type="time" :name="'hours[' + index + '][close_time]'" x-model="item.close_time" required class="border border-zinc-300 rounded-lg px-2 py-1.5 text-xs font-mono bg-white focus:ring-1 focus:ring-zinc-900 focus:outline-none">
                                </div>
                                <button type="button" @click="removeOperatingDay(index)" title="Hapus hari ini" class="p-1.5 text-zinc-400 hover:text-rose-600 hover:bg-white rounded-lg transition border border-transparent hover:border-zinc-200">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </template>

                        <template x-if="operatingHoursList.length === 0">
                            <div class="p-8 text-center bg-zinc-50 rounded-xl border border-dashed border-zinc-300">
                                <svg class="w-8 h-8 text-zinc-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-xs text-zinc-700 font-semibold">Tidak Ada Batasan Jam Operasional</p>
                                <p class="text-[11px] text-zinc-400 mt-1 max-w-xs mx-auto">Ruangan ini beroperasi 24 Jam penuh dan dapat dipesan kapan saja tanpa batasan hari.</p>
                                <button type="button" @click="addOperatingDay()" class="mt-3 px-3 py-1.5 bg-zinc-900 text-white rounded-lg text-xs font-medium hover:bg-zinc-800 transition">
                                    + Tambah Hari Operasional
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="flex justify-between items-center pt-4 border-t border-zinc-100 mt-4">
                        <span class="text-[11px] text-zinc-400" x-text="operatingHoursList.length > 0 ? (operatingHoursList.length + ' hari operasional diatur') : '24 Jam aktif'"></span>
                        <div class="flex items-center space-x-2.5">
                            <button type="button" @click="hoursModalOpen = false" class="px-3.5 py-2 border border-zinc-200 rounded-lg text-zinc-700 text-xs font-medium hover:bg-zinc-50 transition">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-lg text-xs font-medium shadow-sm transition">Simpan Jam Operasional</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal 4: Konfirmasi Hapus Ruang (Modern UI) -->
        <div x-show="deleteModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="deleteModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-zinc-200">
                <div class="flex items-center space-x-3 text-rose-600 mb-3">
                    <div class="w-9 h-9 rounded-full bg-rose-50 flex items-center justify-center border border-rose-100 shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-base text-zinc-950">Hapus Ruang Meeting</h3>
                        <p class="text-xs text-zinc-500">Tindakan ini tidak dapat dibatalkan</p>
                    </div>
                </div>

                <p class="text-xs text-zinc-600 mb-4 leading-relaxed">
                    Apakah Anda yakin ingin menghapus ruang <span class="font-semibold text-zinc-950" x-text="roomToDelete ? roomToDelete.name : ''"></span>? Seluruh data konfigurasi jam operasional dan jadwal terkait ruangan ini akan ikut dihapus.
                </p>

                <form :action="'/rooms/' + (roomToDelete ? roomToDelete.id : '')" method="POST" class="flex justify-end space-x-2.5 pt-3 border-t border-zinc-100">
                    @csrf
                    @method('DELETE')
                    <button type="button" @click="deleteModalOpen = false" class="px-3.5 py-2 border border-zinc-200 rounded-lg text-zinc-700 text-xs font-medium hover:bg-zinc-50 transition">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-medium shadow-sm transition">
                        Ya, Hapus Ruangan
                    </button>
                </form>
            </div>
        </div>

    </div>
</x-layouts.meeting>

