@extends('layouts.guest')

@section('title', 'Login')

@section('styles')
    <style>
        /* Reset & Base */
        html,
        body {
            height: 100%;
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        /* Layout container */
        .split-layout {
            display: flex;
            min-height: 100vh;
            width: 100vw;
        }

        /* Left Pane - Branding */
        .brand-pane {
            flex: 1.2;
            background: linear-gradient(135deg, #072a1a 0%, #0d3f28 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: max(2rem, 3vh) 8%;
            position: relative;
            overflow: hidden;
        }

        .brand-pane::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 80% 20%, rgba(74, 222, 128, 0.1) 0%, transparent 40%);
            pointer-events: none;
        }

        .logo-container {
            margin-bottom: 1rem;
        }

        .brand-title {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 900;
            margin: 0;
            line-height: 1.05;
            letter-spacing: -1px;
        }

        .brand-title-ethio {
            color: #ffffff;
        }

        .brand-title-railway {
            color: #4ade80;
            text-shadow: 0 0 20px rgba(74, 222, 128, 0.3);
        }

        .brand-divider {
            width: 60px;
            height: 4px;
            background-color: #4ade80;
            margin: 1.5rem 0;
            border-radius: 2px;
        }

        .brand-description {
            font-size: 1rem;
            font-weight: 300;
            line-height: 1.5;
            color: #a7f3d0;
            max-width: 450px;
            margin-bottom: 2rem;
        }

        .stats-container {
            display: flex;
            gap: 3rem;
            margin-top: 2rem;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.1rem;
        }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #86efac;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .stat-divider {
            width: 1px;
            background-color: rgba(167, 243, 208, 0.2);
        }

        /* Right Pane - Form */
        .form-pane {
            flex: 1;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: max(2rem, 3vh) 8%;
            position: relative;
        }

        .form-wrapper {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            background-color: #ecfdf5;
            color: #059669;
            padding: 0.3rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
        }

        .security-badge i {
            margin-right: 6px;
        }

        .login-heading {
            font-size: 2rem;
            font-weight: 800;
            color: #111827;
            margin-bottom: 0.25rem;
            letter-spacing: -0.5px;
        }

        .login-subheading {
            font-size: 0.95rem;
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            color: #9ca3af;
            font-size: 1rem;
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.95rem;
            color: #111827;
            background-color: #f9fafb;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #10b981;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 0;
            font-size: 1rem;
        }

        .password-toggle:focus {
            outline: none;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
        }

        .custom-checkbox {
            width: 1rem;
            height: 1rem;
            border-radius: 4px;
            border: 1px solid #d1d5db;
            margin-right: 0.5rem;
            cursor: pointer;
            accent-color: #10b981;
        }

        .checkbox-label {
            font-size: 0.875rem;
            color: #4b5563;
            cursor: pointer;
            user-select: none;
        }

        .forgot-link {
            font-size: 0.875rem;
            font-weight: 600;
            color: #10b981;
            text-decoration: none;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: #059669;
        }

        .btn-submit {
            width: 100%;
            padding: 0.875rem 1rem;
            background-color: #059669;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.2), 0 2px 4px -1px rgba(5, 150, 105, 0.1);
        }

        .btn-submit:hover {
            background-color: #047857;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(5, 150, 105, 0.2), 0 4px 6px -2px rgba(5, 150, 105, 0.1);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .support-card {
            margin-top: 1.5rem;
            background-color: #f9fafb;
            border: 1px solid #f3f4f6;
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .support-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #ecfdf5;
            color: #059669;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .support-text h4 {
            margin: 0 0 0.25rem 0;
            font-size: 0.875rem;
            font-weight: 700;
            color: #111827;
        }

        .support-text p {
            margin: 0;
            font-size: 0.75rem;
            color: #6b7280;
        }

        .footer-links {
            position: absolute;
            bottom: 1rem;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1.5rem;
            padding: 0 2rem;
        }

        .copyright {
            font-size: 0.65rem;
            font-weight: 700;
            color: #9ca3af;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-right: auto;
        }

        .footer-link {
            font-size: 0.65rem;
            font-weight: 700;
            color: #9ca3af;
            text-decoration: none;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: color 0.2s;
        }

        .footer-link:hover {
            color: #4b5563;
        }



        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            border: 1px solid transparent;
        }

        .alert-danger {
            background-color: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .alert-success {
            background-color: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        @media (max-width: 992px) {
            .split-layout {
                flex-direction: column;
            }

            .brand-pane {
                padding: 3rem 2rem;
                flex: none;
            }

            .form-pane {
                padding: 3rem 2rem 6rem;
            }

            .brand-title {
                font-size: 2.5rem;
            }

            .footer-links {
                flex-direction: column;
                gap: 0.5rem;
                bottom: 1rem;
            }

            .copyright {
                margin-right: 0;
                margin-bottom: 0.5rem;
            }
        }
    </style>
@endsection

@section('content')
    <div class="split-layout">
        <!-- Left Pane -->
        <div class="brand-pane">
            <div class="logo-container">
                <!-- EDR Style Abstract Train Logo -->
                <svg width="150" height="60" viewBox="0 0 240 90" xmlns="http://www.w3.org/2000/svg">
                    <!-- Outer swoop -->
                    <path
                        d="M10,40 C70,10 130,5 180,5 C215,5 235,20 235,45 C235,50 230,53 220,53 C200,53 190,30 160,30 C120,30 60,45 20,55 Z"
                        fill="#ffffff" opacity="0.9" />
                    <!-- Middle swoop -->
                    <path
                        d="M40,65 C90,40 140,30 180,30 C205,30 220,42 220,58 C220,62 215,64 205,64 C190,64 180,45 155,45 C120,45 75,58 50,70 Z"
                        fill="#4ade80" />
                    <!-- Inner swoop -->
                    <path
                        d="M80,85 C120,70 160,60 190,60 C205,60 215,68 215,78 C215,82 210,84 200,84 C190,84 180,72 165,72 C140,72 105,82 85,90 Z"
                        fill="#10b981" />
                </svg>
            </div>

            <h1 class="brand-title">
                <span class="brand-title-ethio">ETHIO-DJIBOUTI</span><br>
                <span class="brand-title-railway">RAILWAY</span>
            </h1>

            <div class="brand-divider"></div>

            <p class="brand-description">
                Powering regional integration through safe, efficient, and reliable modern rail transport services.
            </p>

            <div class="stats-container">
                <div class="stat-item">
                    <span class="stat-value">750+ km</span>
                    <span class="stat-label">Total Track</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-value">100%</span>
                    <span class="stat-label">Electric Powered</span>
                </div>
            </div>
        </div>

        <!-- Right Pane -->
        <div class="form-pane">
            <div class="form-wrapper">

                <div class="security-badge">
                    <i class="fas fa-shield-check"></i> SECURE ENTERPRISE ACCESS
                </div>

                <h2 class="login-heading">IPRPMS Login</h2>
                <p class="login-subheading">Government Planning & Performance System</p>

                @if(session('error'))
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Invalid credentials provided.
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" id="loginForm">
                    @csrf

                    <div class="form-group">
                        <label for="email" class="form-label">Official Email</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" name="email" id="email" class="form-control" placeholder="name@edr.gov.et"
                                value="{{ old('email') }}" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Access Key</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" id="password" class="form-control" placeholder="••••••••"
                                required>
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" name="remember" id="remember" class="custom-checkbox" {{ old('remember') ? 'checked' : '' }}>
                            <label for="remember" class="checkbox-label">Keep me logged in</label>
                        </div>
                        <a href="#" class="forgot-link">Forgot access?</a>
                    </div>

                    <button type="submit" class="btn-submit" id="loginBtn">
                        Authenticate <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </form>

                <div class="support-card">
                    <div class="support-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="support-text">
                        <h4>Need Technical Support?</h4>
                        <p>Contact the IT Department </p>
                    </div>
                </div>

            </div>

            <div class="footer-links">
                <span class="copyright">© {{ date('Y') }} EDR IT DEPARTMENT</span>
                <a href="#" class="footer-link">PRIVACY</a>
                <a href="#" class="footer-link">SECURITY</a>
                <a href="#" class="footer-link">LEGAL</a>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function () {
            // Toggle password visibility
            $('#togglePassword').on('click', function () {
                const passwordInput = $('#password');
                const icon = $(this).find('i');

                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Form submission state
            let submitting = false;
            $('#loginForm').on('submit', async function (event) {
                event.preventDefault();
                if (submitting) return;
                submitting = true;
                const form = this;
                const btn = $('#loginBtn');
                const originalLabel = btn.html();
                btn.html('<i class="fas fa-circle-notch fa-spin"></i> Authenticating...');
                btn.css('opacity', '0.8');
                btn.prop('disabled', true);

                try {
                    // A page left open may contain a token from an expired session.
                    const response = await fetch(@json(route('login.session-token', [], false)), {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { 'Accept': 'application/json' },
                        signal: AbortSignal.timeout(10000)
                    });
                    if (!response.ok) throw new Error('Session refresh failed');
                    const data = await response.json();
                    if (typeof data.token !== 'string' || !data.token) throw new Error('Missing session token');
                    form.querySelector('input[name="_token"]').value = data.token;
                    HTMLFormElement.prototype.submit.call(form);
                } catch (error) {
                    submitting = false;
                    btn.html(originalLabel).css('opacity', '1').prop('disabled', false);
                    let message = document.getElementById('login-session-error');
                    if (!message) {
                        message = document.createElement('div');
                        message.id = 'login-session-error';
                        message.className = 'alert alert-danger';
                        message.setAttribute('role', 'alert');
                        form.before(message);
                    }
                    message.textContent = 'Unable to connect. Check your connection and try again.';
                }
            });

            // Auto-capitalize email first letter logic
            $('#email').on('blur', function () {
                const email = $(this).val();
                if (email && email.indexOf('@') > 0) {
                    const parts = email.split('@');
                    if (parts[0]) {
                        parts[0] = parts[0].charAt(0).toLowerCase() + parts[0].slice(1);
                        $(this).val(parts.join('@'));
                    }
                }
            });
        });
    </script>
@endsection
