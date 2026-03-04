<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Lung AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: radial-gradient(circle at 10% 15%, #dbeafe 0%, #eff6ff 35%, #f8fafc 100%);
        }

        .auth-shell {
            border-radius: 1rem;
            border: 1px solid #dbe7ff;
            overflow: hidden;
            box-shadow: 0 1.2rem 2.5rem rgba(15, 23, 42, 0.12);
            background: #ffffff;
        }

        .hero-pane {
            position: relative;
            min-height: 620px;
            background:
                radial-gradient(circle at 70% 20%, rgba(14, 165, 233, 0.35), transparent 42%),
                linear-gradient(145deg, #020617 0%, #0f172a 45%, #1d4ed8 100%);
            color: #f8fafc;
        }

        .hero-pane::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(2, 6, 23, 0.05), rgba(2, 6, 23, 0.45));
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .resp-canvas {
            position: relative;
            width: min(100%, 380px);
            margin: 0 auto;
            animation: floatPanel 6s ease-in-out infinite;
        }

        .resp-svg {
            width: 100%;
            height: auto;
            filter: drop-shadow(0 12px 24px rgba(2, 6, 23, 0.45));
        }

        .resp-breath {
            transform-origin: 50% 56%;
            animation: breathe 4.8s ease-in-out infinite;
        }

        .lesion {
            animation: lesionPulse 2.3s ease-in-out infinite;
        }

        .scan-sweep {
            animation: scanMove 3.8s linear infinite;
        }

        @keyframes breathe {
            0%, 100% { transform: scale(0.985); }
            50% { transform: scale(1.02); }
        }

        @keyframes lesionPulse {
            0%, 100% { opacity: 0.7; transform: scale(0.92); }
            50% { opacity: 1; transform: scale(1.15); }
        }

        @keyframes scanMove {
            0% { transform: translateY(-58px); opacity: 0; }
            15% { opacity: 0.55; }
            85% { opacity: 0.55; }
            100% { transform: translateY(58px); opacity: 0; }
        }

        @keyframes floatPanel {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
    </style>
</head>
<body class="d-flex align-items-center" style="min-height: 100vh;">
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-xl-11">
            <div class="auth-shell">
                <div class="row g-0">
                    <div class="col-lg-6 hero-pane p-4 p-xl-5 d-none d-lg-flex align-items-center">
                        <div class="hero-content w-100">
                            <div class="resp-canvas mb-4" aria-hidden="true">
                                <svg viewBox="0 0 420 520" class="resp-svg" role="img" aria-label="Respiratory system visualization">
                                    <defs>
                                        <linearGradient id="torsoReg" x1="0" y1="0" x2="1" y2="1">
                                            <stop offset="0%" stop-color="#93c5fd" stop-opacity="0.25"/>
                                            <stop offset="100%" stop-color="#60a5fa" stop-opacity="0.08"/>
                                        </linearGradient>
                                        <linearGradient id="lungLReg" x1="0" y1="0" x2="1" y2="1">
                                            <stop offset="0%" stop-color="#f0abfc" stop-opacity="0.92"/>
                                            <stop offset="100%" stop-color="#818cf8" stop-opacity="0.84"/>
                                        </linearGradient>
                                        <linearGradient id="lungRReg" x1="0" y1="0" x2="1" y2="1">
                                            <stop offset="0%" stop-color="#f5d0fe" stop-opacity="0.94"/>
                                            <stop offset="100%" stop-color="#60a5fa" stop-opacity="0.82"/>
                                        </linearGradient>
                                        <radialGradient id="lesionGlowReg" cx="50%" cy="50%" r="50%">
                                            <stop offset="0%" stop-color="#fde68a" stop-opacity="0.95"/>
                                            <stop offset="100%" stop-color="#f97316" stop-opacity="0.15"/>
                                        </radialGradient>
                                    </defs>

                                    <g>
                                        <path d="M210 35 C285 38, 345 84, 362 162 C372 208, 365 304, 336 368 C309 428, 268 470, 210 478 C152 470, 111 428, 84 368 C55 304, 48 208, 58 162 C75 84, 135 38, 210 35 Z" fill="url(#torsoReg)" stroke="rgba(191,219,254,0.45)"/>
                                    </g>

                                    <g class="resp-breath">
                                        <rect x="196" y="62" width="28" height="78" rx="13" fill="rgba(191,219,254,0.75)"/>
                                        <path d="M210 138 L210 204" stroke="rgba(191,219,254,0.7)" stroke-width="12" stroke-linecap="round"/>

                                        <path d="M205 170 C142 168, 102 210, 94 276 C86 340, 116 400, 168 420 C195 431, 208 402, 208 356 L208 183 C208 175, 207 171, 205 170Z" fill="url(#lungLReg)"/>
                                        <path d="M215 170 C278 168, 318 210, 326 276 C334 340, 304 400, 252 420 C225 431, 212 402, 212 356 L212 183 C212 175, 213 171, 215 170Z" fill="url(#lungRReg)"/>

                                        <path d="M210 200 L176 226 M176 226 L156 260 M176 226 L190 278" stroke="rgba(255,255,255,0.62)" stroke-width="4" fill="none" stroke-linecap="round"/>
                                        <path d="M210 200 L244 226 M244 226 L264 260 M244 226 L232 280" stroke="rgba(255,255,255,0.62)" stroke-width="4" fill="none" stroke-linecap="round"/>

                                        <circle class="lesion" cx="172" cy="262" r="22" fill="url(#lesionGlowReg)"/>
                                        <circle class="lesion" cx="172" cy="262" r="9" fill="#f59e0b"/>
                                    </g>

                                    <rect class="scan-sweep" x="72" y="148" width="276" height="1.8" fill="#67e8f9" opacity="0.7"/>
                                </svg>
                            </div>
                            <h2 class="h4 mb-2">Respiratory AI Workspace</h2>
                            <p class="text-white-50 mb-0">Create your account to access lung risk stratification and explainability tools.</p>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="p-4 p-lg-5">
                            <h1 class="h4 mb-3">Create Account</h1>

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('register.store') }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label" for="name">Name</label>
                                    <input id="name" type="text" name="name" class="form-control" value="{{ old('name') }}" required autofocus>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="email">Email</label>
                                    <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="password">Password</label>
                                    <input id="password" type="password" name="password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Register</button>
                            </form>

                            <p class="small text-muted mt-3 mb-0">
                                Already registered? <a href="{{ route('login') }}">Sign in</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
