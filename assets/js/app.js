/**
 * app.js — Utilidades JS generales de la plataforma
 */

document.addEventListener('DOMContentLoaded', function() {
    // Auto-ocultar flash messages después de 5 segundos
    var flashes = document.querySelectorAll('.flash');
    flashes.forEach(function(f) {
        setTimeout(function() {
            f.style.transition = 'opacity 0.5s';
            f.style.opacity = '0';
            setTimeout(function() { f.remove(); }, 500);
        }, 5000);
    });
});
