<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDE Multi-Lenguaje — EducaCode</title>
    <link rel="stylesheet" data-name="vs/editor/editor.main" href="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/editor/editor.main.css">
    <style>
        :root{--bg:#1e1e1e;--panel:#252526;--border:#3c3c3c;--text:#ccc;--text2:#858585;--ok:#4ec9b0;--err:#f44747;--tab-bg:#2d2d2d}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:Inter,sans-serif;background:var(--bg);color:var(--text);height:100vh;overflow:hidden;display:flex;flex-direction:column}
        .topbar{height:42px;background:var(--panel);display:flex;align-items:center;padding:0 12px;border-bottom:1px solid var(--border);gap:8px;justify-content:space-between}
        .topbar-left,.topbar-right{display:flex;align-items:center;gap:8px}
        select,button,input{background:var(--tab-bg);color:var(--text);border:1px solid var(--border);padding:5px 10px;border-radius:4px;font-size:.8rem;cursor:pointer;font-family:inherit}
        button:hover{background:var(--panel)}
        .run-btn{background:var(--ok)!important;border-color:var(--ok)!important;color:#111!important;font-weight:700}
        .run-btn:hover{background:#3cb89a!important}
        .main-area{flex:1;display:flex}
        .editor-panel{flex:1;display:flex;flex-direction:column}
        .tab-bar{height:34px;background:var(--panel);display:flex;align-items:center;padding:0 6px;border-bottom:1px solid var(--border);gap:2px;overflow-x:auto}
        .file-tab{padding:5px 12px;border-radius:3px 3px 0 0;font-size:.75rem;cursor:pointer;background:var(--tab-bg);border:1px solid var(--border);border-bottom:none;color:var(--text2)}
        .file-tab.act{background:var(--bg);color:var(--text)}
        .editor-container{flex:1}
        .output-panel{height:180px;min-height:60px;resize:vertical;overflow:auto;background:var(--panel);border-top:1px solid var(--border);display:flex;flex-direction:column}
        .output-content{flex:1;padding:8px 12px;font-family:'Cascadia Code',Consolas,monospace;font-size:.83rem;overflow:auto;white-space:pre-wrap}
        .sidebar{width:200px;background:var(--panel);border-left:1px solid var(--border);padding:8px;font-size:.78rem;overflow-y:auto}
        .sidebar h4{color:var(--text2);text-transform:uppercase;font-size:.7rem;margin:8px 0 4px}
        .file-item{padding:4px 8px;cursor:pointer;border-radius:3px;color:var(--text2)}.file-item:hover{background:var(--tab-bg);color:var(--text)}
    </style>
</head>
<body>
<div class="topbar">
    <div class="topbar-left">
        <span style="font-weight:700;font-size:.85rem">💻 IDE EducaCode</span>
        <select id="lang-select">
            <option value="python">Python</option><option value="javascript">JavaScript</option><option value="html">HTML</option><option value="php">PHP</option><option value="java">Java</option><option value="cpp">C++</option><option value="c">C</option><option value="go">Go</option><option value="rust">Rust</option><option value="ruby">Ruby</option><option value="sql">SQL</option><option value="typescript">TypeScript</option><option value="bash">Bash</option><option value="r">R</option><option value="kotlin">Kotlin</option><option value="swift">Swift</option>
        </select>
        <button class="run-btn" onclick="ejecutar()">▶ Ejecutar</button>
    </div>
    <div class="topbar-right">
        <button onclick="window.history.back()">← Volver</button>
    </div>
</div>
<div class="main-area">
    <div class="editor-panel">
        <div class="tab-bar" id="tab-bar"><div class="file-tab act" data-file="main">main.py</div></div>
        <div class="editor-container" id="editor-container"></div>
        <div class="output-panel"><div class="output-content" id="output">Escribí tu código y hacé clic en Ejecutar</div></div>
    </div>
    <div class="sidebar">
        <h4>Archivos</h4><div id="file-list"><div class="file-item" data-file="main">📄 main.py</div></div>
        <button style="width:100%;margin-top:8px;font-size:.72rem" onclick="nuevoArchivo()">+ Nuevo</button>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js"></script>
<script>
var FILES={'main':{name:'main.py',content:'print("Hola desde EducaCode!")\n'}},ACTIVE='main',EDITOR;
require.config({paths:{vs:'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs'}});
require(['vs/editor/editor.main'],function(){
    EDITOR=monaco.editor.create(document.getElementById('editor-container'),{value:FILES['main'].content,language:'python',theme:'vs-dark',fontSize:14,fontFamily:"'Cascadia Code',Consolas,monospace",automaticLayout:true,minimap:{enabled:false}});
    document.getElementById('lang-select').addEventListener('change',function(){EDITOR.getModel().setLanguage(this.value==='cpp'?'cpp':this.value==='csharp'?'csharp':this.value)});
    EDITOR.onDidChangeModelContent(function(){FILES[ACTIVE].content=EDITOR.getValue()});
});

function ejecutar(){
    var code=EDITOR.getValue(),lang=document.getElementById('lang-select').value,out=document.getElementById('output');
    out.innerHTML='Ejecutando...';
    if(lang==='html'){var w=window.open('');w.document.write(code);w.document.close();out.innerHTML='Abierto en ventana nueva';return}
    if(lang==='javascript'){try{var o='';var old=console.log;console.log=function(){o+=Array.from(arguments).join(' ')+'\n'};eval(code);console.log=old;out.innerHTML=o||'Ejecutado (sin salida)'}catch(e){out.innerHTML='Error: '+e.message};return}
    fetch('https://emkc.org/api/v2/piston/execute',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({language:lang==='cpp'?'c++':lang,version:'*',files:[{content:code}]})})
    .then(function(r){return r.json()}).then(function(d){var run=d.run||{};out.innerHTML=(run.stdout||'')+(run.stderr?'\n'+run.stderr:'')+(run.output?'\n'+run.output:'')||'Ejecutado (sin salida)'})
    .catch(function(){out.innerHTML='Error de conexión al motor de ejecución'});
}

var fc=1;
function nuevoArchivo(){var n=prompt('Nombre:','archivo'+fc+'.txt');if(!n)return;fc++;FILES[n]={name:n,content:''};document.getElementById('tab-bar').innerHTML+='<div class="file-tab" data-file="'+n+'" onclick="selectFile(\''+n+'\')">'+n+'</div>';document.getElementById('file-list').innerHTML+='<div class="file-item" data-file="'+n+'" onclick="selectFile(\''+n+'\')">📄 '+n+'</div>';selectFile(n)}
function selectFile(n){EDITOR.setValue(FILES[n].content||'');ACTIVE=n;document.querySelectorAll('.file-tab').forEach(function(t){t.classList.remove('act')});var t=document.querySelector('.file-tab[data-file="'+n+'"]');if(t)t.classList.add('act')}
document.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key==='Enter'){e.preventDefault();ejecutar()}});
</script>
</body>
</html>
