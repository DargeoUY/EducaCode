(function () {
    'use strict';

    if (typeof window.__seguridadInit !== 'undefined') return;
    window.__seguridadInit = true;

    var API_BASE = '/api';

    function logEvento(evento) {
        try {
            fetch(API_BASE + '/seguridad/log', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ evento: evento }),
                keepalive: true
            }).catch(function () {});
        } catch (e) {}
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            logEvento('cambio_pestana');
        }
    });

    window.addEventListener('blur', function () {
        logEvento('blur');
    });

    var isEvaluacion = window.location.href.indexOf('/evaluacion') !== -1 ||
                       document.body.classList.contains('modo-evaluacion');

    if (isEvaluacion) {
        try {
            var el = document.documentElement;
            if (el.requestFullscreen) {
                el.requestFullscreen().catch(function () {});
            }
        } catch (e) {}

        document.addEventListener('fullscreenchange', function () {
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                logEvento('fullscreen_exit');
            }
        });

        document.addEventListener('keydown', function (e) {
            var prohibidas = ['F12', 'PrintScreen'];
            var ctrl = e.ctrlKey || e.metaKey;
            var tecla = e.key || e.keyCode;
            if (prohibidas.indexOf(tecla) !== -1 || (ctrl && (tecla === 'u' || tecla === 's' || tecla === 'p' || tecla === 'w' || tecla === 't' || tecla === 'n' || tecla === 'r' || tecla === 85 || tecla === 83 || tecla === 80))) {
                e.preventDefault();
                e.stopPropagation();
                if (tecla === 'w' || tecla === 87) return;
                return false;
            }
        });

        document.addEventListener('contextmenu', function (e) {
            e.preventDefault();
        });
    }
})();
