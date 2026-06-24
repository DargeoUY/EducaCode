(function(){
    var fl = document.querySelector('.flash');
    if (fl) setTimeout(function(){ fl.style.display = 'none' }, 5000);

    var API = '../api/registrar-tab.php';

    document.addEventListener('visibilitychange', function(){
        if (document.hidden) {
            fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ tipo_evento: 'visibility' }) }).catch(function(){});
            var overlay = document.getElementById('bloqueo-overlay');
            if (overlay) overlay.style.display = 'flex';
        }
    });

    window.addEventListener('blur', function(){
        fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ tipo_evento: 'blur' }) }).catch(function(){});
        var overlay = document.getElementById('bloqueo-overlay');
        if (overlay) overlay.style.display = 'flex';
    });
})();
