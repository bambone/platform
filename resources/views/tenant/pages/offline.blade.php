<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Связь потеряна | Moto Levins</title>
    <!-- Use inline styles or reliable CDN to ensure loading without network reliance if main CSS isn't cached yet -->
    <style>
        :root {
            --obsidian: #050505;
            --carbon: #101012;
            --moto-amber: #e65c00;
            --silver: #8a8a93;
        }
        body {
            margin: 0;
            padding: 0;
            background-color: var(--obsidian);
            color: #ffffff;
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100dvh;
            min-height: 100vh;
            text-align: center;
            overflow-x: hidden;
            overflow-y: auto;
            padding: max(1rem, env(safe-area-inset-top)) max(1rem, env(safe-area-inset-right)) max(1rem, env(safe-area-inset-bottom)) max(1rem, env(safe-area-inset-left));
            box-sizing: border-box;
        }
        .container {
            max-width: 500px;
            width: 100%;
            padding: 1rem 1rem 1.5rem;
            position: relative;
            z-index: 10;
        }
        @media (min-width: 480px) {
            .container { padding: 2rem; }
        }
        .glow {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 300px;
            height: 300px;
            background: rgba(230, 92, 0, 0.1);
            filter: blur(80px);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }
        .icon {
            width: 64px;
            height: 64px;
            color: var(--silver);
            opacity: 0.5;
            margin: 0 auto 2rem;
        }
        h1 {
            font-size: clamp(1.375rem, 5vw + 0.5rem, 2rem);
            font-weight: 800;
            margin: 0 0 1rem;
            line-height: 1.2;
            text-wrap: balance;
        }
        p {
            color: var(--silver);
            font-size: clamp(0.9375rem, 2vw + 0.5rem, 1.125rem);
            line-height: 1.6;
            margin: 0 0 1.75rem;
        }
        @media (min-width: 480px) {
            p { margin-bottom: 2.5rem; }
        }
        button {
            background-color: var(--moto-amber);
            color: white;
            border: none;
            min-height: 44px;
            padding: 0.875rem 1.75rem;
            font-size: clamp(1rem, 2.5vw, 1.125rem);
            font-weight: bold;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 25px rgba(230, 92, 0, 0.2);
            width: 100%;
            max-width: 20rem;
        }
        @media (min-width: 400px) {
            button { width: auto; padding: 1rem 2.5rem; }
        }
        button:hover {
            background-color: #ff6600;
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(230, 92, 0, 0.4);
        }
        button:active {
            transform: translateY(0);
        }
        .logo {
            font-weight: 900;
            font-size: 1.25rem;
            letter-spacing: -0.025em;
            margin-bottom: 3rem;
        }
    </style>
</head>
<body>
    <div class="glow"></div>
    <div class="container">
        <div class="logo">Moto Levins</div>
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238L3 3M12 12L3 3" />
        </svg>
        <h1>Вне зоны действия сети</h1>
        <p>Для бронирования мотоцикла и проверки дат требуется подключение к интернету.</p>
        <button id="retryBtn" onclick="window.location.reload()">Обновить страницу</button>
    </div>

    <script>
        setInterval(() => {
            if (navigator.onLine) {
                const btn = document.getElementById('retryBtn');
                if (btn) btn.innerText = 'Загрузка...';
                window.location.reload();
            }
        }, 3000);
    </script>
</body>
</html>
