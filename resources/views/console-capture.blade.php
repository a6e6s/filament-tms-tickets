<script>
    (function () {
        if (window.__tmsConsoleCaptureInstalled) {
            return;
        }
        window.__tmsConsoleCaptureInstalled = true;
        window.__tmsConsoleErrors = [];

        var push = function (message) {
            if (window.__tmsConsoleErrors.length < 50) {
                window.__tmsConsoleErrors.push(String(message).slice(0, 2000));
            }
        };

        var originalError = console.error;
        console.error = function () {
            push(Array.prototype.map.call(arguments, function (arg) {
                return typeof arg === 'string' ? arg : (arg && arg.stack) || String(arg);
            }).join(' '));
            return originalError.apply(this, arguments);
        };

        window.addEventListener('error', function (event) {
            push(event.message || 'Unhandled error');
        });

        window.addEventListener('unhandledrejection', function (event) {
            push('Unhandled promise rejection: ' + (event.reason && event.reason.message ? event.reason.message : event.reason));
        });
    })();
</script>
