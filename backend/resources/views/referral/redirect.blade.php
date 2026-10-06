<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Открытие приложения...</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://code.jquery.com/jquery-4.0.0.min.js" integrity="sha256-OaVG6prZf4v69dPg6PhVattBXkcOWQB62pdZ3ORyrao=" crossorigin="anonymous"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background-color: #f5f5f7;
            color: #333;
            text-align: center;
            padding: 20px;
        }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007aff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background-color: #007aff;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="loader"></div>
<h2>Переходим в приложение...</h2>
<p>Если переход не произошел автоматически, нажмите на кнопку ниже:</p>

<a href="{{ $appSchemeUrl }}" id="fallback-link" class="btn">Открыть приложение</a>

<script>

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        async function generateHash(string) {
            const msgUint8 = new TextEncoder().encode(string);
            const hashBuffer = await crypto.subtle.digest('SHA-256', msgUint8);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
            return hashHex;
        }

        function redirectToApp() {
            const storeUrl = "{{ $storeUrl }}";
            const appSchemeUrl = "{{ $appSchemeUrl }}";
            const os = "{{ $os }}";

            if (os === 'desktop') {
                window.location.href = storeUrl;
            } else {
                let appRedirectTimeout;

                // Функция редиректа в маркет
                function redirectToStore() {
                    window.location.href = storeUrl;
                }

                // Функция очистки таймера, если приложение успешно открылось
                function clearTimers() {
                    clearTimeout(appRedirectTimeout);
                    window.removeEventListener('pagehide', clearTimers);
                    window.removeEventListener('visibilitychange', clearTimers);
                }

                // 1. Слушаем события ухода со страницы.
                // Если приложение откроется, страница скроется, и мы отменим редирект в App Store.
                window.addEventListener('pagehide', clearTimers);
                window.addEventListener('visibilitychange', function () {
                    if (document.hidden) {
                        clearTimers();
                    }
                });

                // 2. Пытаемся запустить приложение
                window.location.href = appSchemeUrl;

                // 3. Запускаем таймер для App Store с увеличенной задержкой (3.5 - 4 секунды).
                // Это даст пользователю время нажать «Открыть» в системном диалоге Safari.
                appRedirectTimeout = setTimeout(function () {
                    // Дополнительная проверка: если страница всё еще видима и активна
                    if (!document.hidden) {
                        redirectToStore();
                    }
                }, 3500);
            }
        }

        let myIp = '';
        fetch('https://back.geocar.ge/api/ref/ip')
            .then(response => response.json())
            .then(data => myIp = data.ip)

            .then(() => {
            const data = [
                myIp,
                navigator.language,
                screen.width,
                screen.height,
                screen.colorDepth,
                window.devicePixelRatio,
                navigator.maxTouchPoints,
                navigator.hardwareConcurrency ?? '',
                navigator.deviceMemory ?? ''
            ].join('|');

            generateHash(data).then(hash => {

                $.post('/api/ref/save-hash', {
                    hash: hash,
                    code: '{{$code}}'
                }, function (response) {
                    if (response.success) {
                        redirectToApp();
                    }
                });

            })
        });

</script>
</body>
</html>
