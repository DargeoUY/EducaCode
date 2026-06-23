(function () {
    'use strict';
    if (document.getElementById('ide-panel-loader')) return;

    var btn = document.createElement('button');
    btn.id = 'ide-panel-loader';
    btn.textContent = '💻 IDE';
    btn.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9998;background:var(--primario,#3b82f6);color:#fff;border:none;padding:10px 18px;border-radius:10px;cursor:pointer;font-size:0.9rem;font-weight:700;font-family:Inter,sans-serif;box-shadow:0 4px 16px rgba(59,130,246,.4);transition:transform .2s,box-shadow .2s';
    btn.onmouseenter = function () { btn.style.transform = 'scale(1.05)'; btn.style.boxShadow = '0 6px 20px rgba(59,130,246,.5)'; };
    btn.onmouseleave = function () { btn.style.transform = 'scale(1)'; btn.style.boxShadow = '0 4px 16px rgba(59,130,246,.4)'; };

    var overlay = document.createElement('div');
    overlay.style.cssText = 'display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;cursor:pointer';

    var panel = document.createElement('div');
    panel.style.cssText = 'position:fixed;top:0;right:0;bottom:0;width:50%;min-width:400px;z-index:10000;background:#1e1e1e;display:none;flex-direction:column;border-left:1px solid #3c3c3c;box-shadow:-4px 0 24px rgba(0,0,0,.3)';

    var panelTop = document.createElement('div');
    panelTop.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#252526;border-bottom:1px solid #3c3c3c';

    var title = document.createElement('span');
    title.textContent = '💻 IDE Multi-Lenguaje';
    title.style.cssText = 'color:#ccc;font-size:0.9rem;font-weight:600;font-family:Inter,sans-serif';

    var actions = document.createElement('div');
    actions.style.cssText = 'display:flex;gap:8px';

    var fullscreenBtn = document.createElement('button');
    fullscreenBtn.textContent = '⛶ Pantalla completa';
    fullscreenBtn.style.cssText = 'background:#2d2d2d;color:#ccc;border:1px solid #3c3c3c;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:0.75rem;font-family:Inter,sans-serif';
    fullscreenBtn.onclick = function () { window.open('/ide', '_blank'); };

    var closeBtn = document.createElement('button');
    closeBtn.textContent = '✕';
    closeBtn.style.cssText = 'background:#2d2d2d;color:#ccc;border:1px solid #3c3c3c;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:0.8rem;font-family:Inter,sans-serif';
    closeBtn.onclick = function () { closePanel(); };

    actions.appendChild(fullscreenBtn);
    actions.appendChild(closeBtn);
    panelTop.appendChild(title);
    panelTop.appendChild(actions);

    var iframe = document.createElement('iframe');
    iframe.src = '/ide';
    iframe.style.cssText = 'flex:1;border:none;width:100%';

    panel.appendChild(panelTop);
    panel.appendChild(iframe);

    document.body.appendChild(btn);
    document.body.appendChild(overlay);
    document.body.appendChild(panel);

    var isOpen = false;

    function openPanel() {
        isOpen = true;
        overlay.style.display = 'block';
        panel.style.display = 'flex';
        btn.style.display = 'none';
        document.body.style.overflow = 'hidden';
        document.body.style.marginRight = '50%';
    }

    function closePanel() {
        isOpen = false;
        overlay.style.display = 'none';
        panel.style.display = 'none';
        btn.style.display = 'block';
        document.body.style.overflow = '';
        document.body.style.marginRight = '';
    }

    btn.onclick = openPanel;
    overlay.onclick = closePanel;

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) closePanel();
    });
})();
