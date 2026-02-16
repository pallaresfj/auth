<!DOCTYPE html>
<html class="dark" lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Portal Unico - IED Jose Maria Herrera</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="sso-home bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen flex flex-col">
<main class="flex-grow flex flex-col lg:flex-row">
    <div class="relative w-full lg:w-1/2 min-h-[400px] lg:min-h-screen flex flex-col justify-between p-8 lg:p-16 overflow-hidden">
        <div
            class="absolute inset-0 z-0 bg-cover bg-center"
            style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDH9J2GFr8K1FIDEGcQWYILdub9RgK-pVJD5R1bGSlBZHYNJt0cHNjjeoXZS1f5984zECuNvJbKMfRXbSceggaix6bY61skrwBCg2ee2--cSjSXmGNhtdLY4v_5aj_f1kj337oBcwvPmZDjfrtQS0d5k13BQeun3uWD7S5hPONxRNSJc8IfSC8cOps3445np1i_mEdw5d2QRh8c8MG-T069KJOHUuRWrjdZq5pPBbDAbjy3v8K0OiUSOJttTAZBLO30a2w4_OMPuDM');"
        >
            <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-background-dark/90"></div>
        </div>

        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-primary p-3 rounded-xl shadow-lg shadow-primary/20">
                    <span class="material-symbols-outlined text-background-dark text-3xl font-bold">agriculture</span>
                </div>
                <h2 class="text-white text-xl font-bold tracking-tight">IED Jose Maria Herrera</h2>
            </div>

            <div class="max-w-xl">
                <h1 class="text-white text-5xl lg:text-6xl font-black leading-[1.1] mb-6 tracking-tight">
                    Educacion <span class="text-primary">Agropecuaria</span> de Excelencia
                </h1>
                <p class="text-slate-200 text-lg lg:text-xl leading-relaxed font-medium opacity-90">
                    Bienvenido al Portal Unico de Acceso. Gestiona tu informacion en un entorno seguro, moderno y eficiente disenado para nuestra comunidad educativa.
                </p>
            </div>
        </div>

        <div class="relative z-10 mt-auto pt-10">
            <div class="flex items-center gap-2 text-slate-300">
                <span class="material-symbols-outlined text-primary">location_on</span>
                <span class="text-sm font-semibold tracking-wide uppercase">Pivijay, Magdalena - Colombia</span>
            </div>
        </div>
    </div>

    <div class="w-full lg:w-1/2 bg-background-light dark:bg-background-dark flex flex-col p-6 lg:p-16">
        <div class="max-w-2xl mx-auto w-full flex flex-col gap-12">
            <section class="bg-white dark:bg-[#1c261c] p-8 lg:p-10 rounded-2xl shadow-xl border border-slate-200 dark:border-[#293829]">
                <div class="mb-6 flex justify-center">
                    <img
                        src="{{ asset('images/logo-ied.png') }}"
                        alt="Logo IED Agropecuaria Jose Maria Herrera"
                        class="h-[100px] w-[100px] rounded-full object-cover ring-1 ring-primary/20 bg-white dark:bg-slate-900"
                        style="width: 100px; height: 100px;"
                        loading="eager"
                    />
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl lg:text-3xl font-bold text-slate-900 dark:text-white mb-2">Iniciar Sesion</h2>
                    <p class="text-slate-600 dark:text-[#9db89d]">Accede de forma segura con tu cuenta institucional de Google para continuar.</p>
                </div>

                @php
                    $accessMessage = session('error');
                    $successMessage = session('success');

                    if (! $accessMessage && request()->query('access') === 'denied') {
                        $accessMessage = 'Tu cuenta no tiene acceso al panel administrativo.';
                    }

                    if (! $successMessage && request()->query('logged_out') == 1) {
                        $successMessage = 'Sesion cerrada correctamente.';
                    }
                @endphp

                @if ($accessMessage)
                    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ $accessMessage }}
                    </div>
                @endif

                @if ($successMessage)
                    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ $successMessage }}
                    </div>
                @endif

                @auth
                    <div class="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-700/40 dark:bg-emerald-900/20 dark:text-emerald-200">
                        Sesion activa como <strong>{{ auth()->user()->email }}</strong>.
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full flex items-center justify-center gap-3 bg-white hover:bg-slate-50 dark:bg-transparent dark:hover:bg-primary/5 text-slate-700 dark:text-white font-bold py-4 px-6 rounded-xl border-2 border-slate-200 dark:border-primary/30 transition-all active:scale-[0.98]"
                        >
                            <span class="material-symbols-outlined text-xl">logout</span>
                            <span>Cerrar sesion</span>
                        </button>
                    </form>
                @else
                    <a
                        class="w-full flex items-center justify-center gap-3 bg-white hover:bg-slate-50 dark:bg-transparent dark:hover:bg-primary/5 text-slate-700 dark:text-white font-bold py-4 px-6 rounded-xl border-2 border-slate-200 dark:border-primary/30 transition-all active:scale-[0.98]"
                        href="{{ route('login') }}"
                    >
                        <svg class="w-6 h-6" viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"></path>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"></path>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"></path>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"></path>
                        </svg>
                        <span>Continuar con Google</span>
                    </a>
                @endauth
            </section>

            <section>
                <div class="flex items-center gap-3 mb-6">
                    <div class="h-px flex-grow bg-slate-200 dark:bg-[#293829]"></div>
                    <h3 class="text-xs font-black tracking-[0.2em] uppercase text-slate-500 dark:text-[#9db89d] whitespace-nowrap">Ecosistema Institucional</h3>
                    <div class="h-px flex-grow bg-slate-200 dark:bg-[#293829]"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a class="group flex flex-col p-5 bg-white dark:bg-[#1c261c] rounded-xl border border-slate-200 dark:border-[#293829] hover:border-primary/50 dark:hover:border-primary/50 transition-all hover:shadow-lg" href="https://planes.iedagropivijay.edu.co" target="_blank" rel="noopener noreferrer">
                        <div class="text-primary mb-3">
                            <span class="material-symbols-outlined text-3xl group-hover:scale-110 transition-transform">school</span>
                        </div>
                        <h4 class="text-slate-900 dark:text-white font-bold text-sm mb-1">Gestion Academica</h4>
                        <p class="text-xs text-slate-500 dark:text-[#9db89d] leading-relaxed mb-3">Plataforma integral de planeacion estrategica y seguimiento escolar.</p>
                        <span class="mt-auto text-[10px] font-mono text-primary opacity-80 group-hover:opacity-100">planes.iedagropivijay.edu.co</span>
                    </a>

                    <a class="group flex flex-col p-5 bg-white dark:bg-[#1c261c] rounded-xl border border-slate-200 dark:border-[#293829] hover:border-primary/50 dark:hover:border-primary/50 transition-all hover:shadow-lg" href="https://asistencia.iedagropivijay.edu.co" target="_blank" rel="noopener noreferrer">
                        <div class="text-primary mb-3">
                            <span class="material-symbols-outlined text-3xl group-hover:scale-110 transition-transform">assignment_ind</span>
                        </div>
                        <h4 class="text-slate-900 dark:text-white font-bold text-sm mb-1">Teaching Assistance</h4>
                        <p class="text-xs text-slate-500 dark:text-[#9db89d] leading-relaxed mb-3">Sistema de control de asistencia docente y registro de actividades diarias.</p>
                        <span class="mt-auto text-[10px] font-mono text-primary opacity-80 group-hover:opacity-100">asistencia.iedagropivijay.edu.co</span>
                    </a>

                    <a class="group flex flex-col p-5 bg-white dark:bg-[#1c261c] rounded-xl border border-slate-200 dark:border-[#293829] hover:border-primary/50 dark:hover:border-primary/50 transition-all hover:shadow-lg" href="https://silo.iedagropivijay.edu.co" target="_blank" rel="noopener noreferrer">
                        <div class="text-primary mb-3">
                            <span class="material-symbols-outlined text-3xl group-hover:scale-110 transition-transform">folder_managed</span>
                        </div>
                        <h4 class="text-slate-900 dark:text-white font-bold text-sm mb-1">SILO</h4>
                        <p class="text-xs text-slate-500 dark:text-[#9db89d] leading-relaxed mb-3">Sistema de gestion documental para la administracion eficiente de archivos.</p>
                        <span class="mt-auto text-[10px] font-mono text-primary opacity-80 group-hover:opacity-100">silo.iedagropivijay.edu.co</span>
                    </a>
                </div>
            </section>
        </div>

        <footer class="mt-auto pt-12 text-center lg:text-left">
            <div class="flex flex-col lg:flex-row items-center justify-between gap-4 text-slate-500 dark:text-[#9db89d] text-xs font-medium">
                <p>
                    &copy; 2026 IED Agropecuaria Jose Maria Herrera - Desarrollado por
                    <a class="hover:text-primary transition-colors" href="https://www.asyservicios.com" target="_blank" rel="noopener noreferrer">AS&amp;Servicios.com</a>.
                </p>
            </div>
        </footer>
    </div>
</main>
</body>
</html>
