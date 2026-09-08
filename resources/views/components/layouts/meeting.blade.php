<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Sistem Reservasi Ruang Meeting' }}</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            600: '#0f172a',
                            700: '#020617',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="bg-zinc-50 text-zinc-900 min-h-screen flex flex-col antialiased">

    <!-- Minimalist Header -->
    <header class="bg-white border-b border-zinc-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Brand Title -->
                <div class="flex items-center space-x-8">
                    <a href="{{ route('web.rooms.index') }}" class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg bg-zinc-900 text-white flex items-center justify-center font-bold text-sm tracking-tighter">
                            MR
                        </div>
                        <div class="leading-none">
                            <span class="font-semibold text-zinc-950 text-sm tracking-tight block">Meeting Reservation</span>
                            <span class="text-[11px] text-zinc-500 font-normal block mt-0.5">Enterprise Room Management</span>
                        </div>
                    </a>

                    <!-- Minimal Nav Links -->
                    <nav class="hidden md:flex items-center space-x-1 pl-4 border-l border-zinc-200">
                        <a href="{{ route('web.rooms.index') }}" class="px-3 py-1.5 rounded-md text-xs font-medium transition {{ request()->routeIs('web.rooms.index') || request()->routeIs('web.rooms.show') ? 'bg-zinc-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' }}">
                            Ruang Meeting
                        </a>
                        <a href="{{ route('web.rooms.search') }}" class="px-3 py-1.5 rounded-md text-xs font-medium transition {{ request()->routeIs('web.rooms.search') ? 'bg-zinc-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' }}">
                            Cari Ruang Kosong
                        </a>
                        <a href="{{ route('web.bookings.my') }}" class="px-3 py-1.5 rounded-md text-xs font-medium transition {{ request()->routeIs('web.bookings.my') ? 'bg-zinc-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' }}">
                            Reservasi Saya
                        </a>
                    </nav>
                </div>

                <!-- Minimal User Switcher -->
                <div class="flex items-center space-x-3">
                    <form action="{{ route('web.user.switch') }}" method="POST" class="flex items-center bg-zinc-100/80 pl-2.5 pr-1 py-1 rounded-lg border border-zinc-200 text-xs">
                        @csrf
                        <span class="text-zinc-500 font-medium mr-2 hidden sm:inline">User Aktif:</span>
                        <select name="user_id" onchange="this.form.submit()" class="bg-white border border-zinc-200 text-zinc-900 font-medium rounded-md px-2 py-1 text-xs focus:ring-1 focus:ring-zinc-900 focus:outline-none cursor-pointer">
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ $activeUser->id === $u->id ? 'selected' : '' }}>
                                    {{ $u->name }} (ID: {{ $u->id }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Minimal Notifications -->
        @if(session('success'))
            <div class="mb-6 p-4 rounded-xl bg-emerald-50/80 border border-emerald-200 text-emerald-900 text-xs flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                    <span class="font-medium">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 p-4 rounded-xl bg-rose-50/80 border border-rose-200 text-rose-900 text-xs flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-2">
                    <span class="w-2 h-2 rounded-full bg-rose-600"></span>
                    <span class="font-medium">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-50/80 border border-rose-200 text-rose-900 text-xs shadow-sm">
                <div class="font-semibold mb-1 flex items-center space-x-2">
                    <span class="w-2 h-2 rounded-full bg-rose-600"></span>
                    <span>Validasi Masukan Gagal (HTTP 422):</span>
                </div>
                <ul class="list-disc list-inside space-y-0.5 pl-4 text-rose-800">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>

    <!-- Minimal Footer -->
    <footer class="bg-white border-t border-zinc-200 py-6 text-center text-xs text-zinc-500">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <span class="font-medium text-zinc-600">Meeting Room Reservation System</span>
            <span class="text-zinc-400">Pessimistic Concurrency &bull; Storage Constraints &bull; Clean Architecture</span>
        </div>
    </footer>

</body>
</html>
