(function(){
    if(document.getElementById('ide-panel-btn'))return;
    var btn=document.createElement('button');
    btn.id='ide-panel-btn';
    btn.textContent='💻 IDE';
    btn.style.cssText='position:fixed;bottom:20px;right:20px;z-index:9998;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;padding:10px 18px;border-radius:10px;cursor:pointer;font-size:.9rem;font-weight:700;font-family:Inter,sans-serif;box-shadow:0 4px 16px rgba(99,102,241,.4);transition:transform .2s';
    btn.onmouseenter=function(){btn.style.transform='scale(1.05)'};
    btn.onmouseleave=function(){btn.style.transform='scale(1)'};
    btn.onclick=function(){window.open('/ide/index.php','_blank')};
    document.body.appendChild(btn);
})();
