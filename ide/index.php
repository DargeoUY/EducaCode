<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];
$rol = $usuario['rol'] ?? 'estudiante';
$nombre = $usuario['nombre'] ?? $usuario['username'];

$titulo = 'IDE Multi-Lenguaje';
require_once __DIR__ . '/../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js"></script>
<style>
    #ide-wrap{position:relative;margin:-24px -20px;height:calc(100vh - 56px);display:flex;flex-direction:column;background:#1e1e1e}
    .ide-top{height:40px;background:#252526;display:flex;align-items:center;padding:0 10px;gap:8px;border-bottom:1px solid #3c3c3c;flex-shrink:0}
    .ide-top select,.ide-top button,.ide-top input{background:#2d2d2d;color:#ccc;border:1px solid #3c3c3c;padding:4px 10px;border-radius:4px;font-size:.78rem;cursor:pointer;font-family:Inter,sans-serif}
    .ide-top button:hover{background:#0e639c;color:#fff;border-color:#0e639c}
    .ide-run{background:#4ec9b0!important;color:#111!important;font-weight:700;border-color:#4ec9b0!important}
    .ide-msg{font-size:.75rem;color:#858585;margin-left:auto}
    .ide-body{flex:1;display:flex;overflow:hidden}
    .ide-editor{flex:1;display:flex;flex-direction:column}
    .ide-tabs{height:32px;background:#252526;display:flex;align-items:center;padding:0 6px;gap:2px;overflow-x:auto;border-bottom:1px solid #3c3c3c;flex-shrink:0}
    .ide-tab{padding:4px 12px;border-radius:3px 3px 0 0;font-size:.72rem;cursor:pointer;background:#2d2d2d;border:1px solid #3c3c3c;border-bottom:none;color:#858585;white-space:nowrap}
    .ide-tab.act{background:#1e1e1e;color:#ccc}
    .ide-tab .cls{margin-left:6px;opacity:.4}.ide-tab .cls:hover{opacity:1;color:#f44747}
    .ide-ed{flex:1}
    .ide-out{height:200px;min-height:40px;resize:vertical;overflow:auto;background:#1e1e1e;border-top:1px solid #3c3c3c;flex-shrink:0}
    .ide-out-head{display:flex;background:#252526;border-bottom:1px solid #3c3c3c;padding:0 8px}
    .ide-out-tab{padding:5px 12px;font-size:.7rem;cursor:pointer;border-bottom:2px solid transparent;color:#858585}
    .ide-out-tab.act{border-bottom-color:#0e639c;color:#ccc}
    .ide-out-body{flex:1;padding:8px 12px;font-family:'Cascadia Code',Consolas,monospace;font-size:.8rem;overflow:auto;white-space:pre-wrap}
    .ide-side{width:220px;background:#252526;border-left:1px solid #3c3c3c;display:flex;flex-direction:column;overflow-y:auto;flex-shrink:0}
    .ide-side h4{font-size:.7rem;color:#858585;text-transform:uppercase;padding:8px 10px 4px}
    .ide-file{padding:5px 10px;font-size:.75rem;cursor:pointer;color:#ccc;display:flex;justify-content:space-between;align-items:center;border-radius:3px}
    .ide-file:hover{background:#2d2d2d}
    .ide-file .del{opacity:0;font-size:.65rem;color:#f44747;cursor:pointer}.ide-file:hover .del{opacity:1}
    .ide-foot{height:24px;background:#007acc;display:flex;align-items:center;padding:0 10px;font-size:.68rem;color:#fff;gap:12px;flex-shrink:0}
    .ide-full{position:fixed!important;top:0!important;left:0!important;width:100vw!important;height:100vh!important;z-index:9999!important;margin:0!important}
    #preview-frame{display:none;width:100%;height:100%;border:none;background:#fff;flex:1}
</style>

<div id="ide-wrap">
    <div class="ide-top">
        <span style="font-weight:700;font-size:.85rem;color:#ccc">💻 <?= sanitizar($nombre) ?></span>
        <select id="lang">
            <option value="python">Python</option><option value="javascript">JavaScript</option><option value="html">HTML</option><option value="php">PHP</option><option value="java">Java</option><option value="cpp">C++</option><option value="c">C</option><option value="go">Go</option><option value="rust">Rust</option><option value="ruby">Ruby</option><option value="sql">SQL</option><option value="typescript">TypeScript</option><option value="bash">Bash</option><option value="r">R</option><option value="kotlin">Kotlin</option><option value="swift">Swift</option>
        </select>
        <button class="ide-run" onclick="ejecutar()">▶ Ejecutar</button>
        <button onclick="guardarProyecto()">💾 Guardar</button>
        <button onclick="nuevoProyecto()">📄 Nuevo</button>
        <span class="ide-msg" id="msg"></span>
        <button title="Pantalla completa" onclick="toggleFull()">⛶</button>
        <a href="<?= BASE_URL ?>" style="color:#858585;text-decoration:none;font-size:.78rem">← Salir</a>
    </div>
    <div class="ide-body">
        <div class="ide-editor">
            <div class="ide-tabs" id="tabs"><div class="ide-tab act" data-file="main">main.py</div></div>
            <div class="ide-ed" id="editor"></div>
            <div class="ide-out" id="outPanel">
                <div class="ide-out-head">
                    <div class="ide-out-tab act" data-o="term">Terminal</div>
                    <div class="ide-out-tab" data-o="prev">Vista previa</div>
                </div>
                <div class="ide-out-body" id="output">Escribí código y hacé clic en ▶ Ejecutar</div>
                <iframe id="preview-frame"></iframe>
            </div>
        </div>
        <div class="ide-side" id="sidebar">
            <h4>💾 Mis Proyectos</h4>
            <div id="file-list"></div>
            <h4>📂 Por lenguaje</h4>
            <div id="lang-list" style="display:flex;flex-wrap:wrap;gap:4px;padding:0 8px">
                <button class="ide-tab" onclick="filtrar('')" style="font-size:.65rem">Todos</button>
                <?php foreach(['python','javascript','html','php','java','cpp','go','rust','ruby','sql'] as $l): ?>
                <button class="ide-tab" onclick="filtrar('<?=$l?>')" style="font-size:.65rem"><?=ucfirst($l)?></button>
                <?php endforeach; ?>
            </div>
            <?php if ($rol === 'docente' || $rol === 'admin'): ?>
            <h4>👨‍🎓 Proyectos estudiantes</h4>
            <div id="est-list" style="padding:4px 8px"><button class="ide-tab" onclick="cargarEstudiantes()" style="font-size:.65rem;">Cargar</button></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="ide-foot">
        <span id="foot-lang">Python</span>
        <span id="foot-lines">Ln 1, Col 1</span>
        <span style="margin-left:auto" id="foot-status">Listo</span>
    </div>
</div>

<script>
var FILES={'main':{name:'main.py',content:CODE['python']}},ACTIVE='main',EDITOR=null,PROY_ACT=null,FILTRO='',IS_FULL=false;

require.config({paths:{vs:'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs'}});
require(['vs/editor/editor.main'],function(){
    EDITOR=monaco.editor.create(document.getElementById('editor'),{
        value:FILES['main'].content,language:'python',theme:'vs-dark',
        fontSize:14,fontFamily:"'Cascadia Code',Consolas,monospace",
        automaticLayout:true,minimap:{enabled:false},
    });
    EDITOR.onDidChangeCursorPosition(function(e){document.getElementById('foot-lines').textContent='Ln '+(e.position.lineNumber)+', Col '+(e.position.column)});
    EDITOR.onDidChangeModelContent(function(){if(FILES[ACTIVE])FILES[ACTIVE].content=EDITOR.getValue()});
    document.getElementById('lang').addEventListener('change',cambiarLenguaje);
    cargarProyectos();
    document.querySelectorAll('.ide-out-tab').forEach(function(t){t.addEventListener('click',function(){
        document.querySelectorAll('.ide-out-tab').forEach(function(x){x.classList.remove('act')});
        t.classList.add('act');var o=t.dataset.o;
        document.getElementById('output').style.display=o==='term'?'block':'none';
        document.getElementById('preview-frame').style.display=o==='prev'?'block':'none';
    })});
});

var CODE={
'python':'print("Hola, mundo!")\n\nnombre = input("Tu nombre: ")\nprint(f"Bienvenido, {nombre}")',
'javascript':'console.log("Hola, mundo!");\n\nlet nombre = prompt("Tu nombre:");\nconsole.log(`Bienvenido, ${nombre}`);',
'html':'<!DOCTYPE html>\n<html>\n<head><title>Hola</title></head>\n<body>\n  <h1>Hola, mundo!</h1>\n  <p>Mi primera pagina</p>\n</body>\n</html>',
'php':'<?php\necho "Hola, mundo!\\n";\n$nombre = "Estudiante";\necho "Bienvenido, $nombre";',
'java':'public class Main {\n    public static void main(String[] args) {\n        System.out.println("Hola, mundo!");\n    }\n}',
'cpp':'#include <iostream>\nusing namespace std;\nint main() {\n    cout << "Hola, mundo!" << endl;\n    return 0;\n}',
'c':'#include <stdio.h>\nint main() {\n    printf("Hola, mundo!\\n");\n    return 0;\n}',
'go':'package main\nimport "fmt"\nfunc main() {\n    fmt.Println("Hola, mundo!")\n}',
'rust':'fn main() {\n    println!("Hola, mundo!");\n}',
'ruby':'puts "Hola, mundo!"\nnombre = gets.chomp\nputs "Bienvenido, #{nombre}"',
'sql':'SELECT "Hola, mundo!" AS saludo;\nSELECT * FROM usuarios LIMIT 5;',
'typescript':'const saludo: string = "Hola, mundo!";\nconsole.log(saludo);',
'bash':'#!/bin/bash\necho "Hola, mundo!"\nread -p "Tu nombre: " nombre\necho "Bienvenido, $nombre"',
'r':'print("Hola, mundo!")\nnombre <- readline("Tu nombre: ")\ncat("Bienvenido,", nombre)',
'kotlin':'fun main() {\n    println("Hola, mundo!")\n}',
'swift':'print("Hola, mundo!")\nlet nombre = readLine() ?? "Anonimo"\nprint("Bienvenido, \\(nombre)")',
};

function cambiarLenguaje(){
    var l=document.getElementById('lang').value;
    EDITOR.getModel().setLanguage(l==='cpp'?'cpp':l==='csharp'?'csharp':l);
    document.getElementById('foot-lang').textContent=l.charAt(0).toUpperCase()+l.slice(1);
    var ext=l==='javascript'?'js':l==='typescript'?'ts':l;
    var tab=document.querySelector('.ide-tab.act');if(tab)tab.innerHTML='main.'+ext;
    if(FILES['main']&&(!FILES['main'].content||FILES['main'].content===CODE['python'])){FILES['main'].content=CODE[l]||CODE['python'];EDITOR.setValue(FILES['main'].content)}
}

function ejecutar(){
    var code=EDITOR.getValue(),lang=document.getElementById('lang').value,out=document.getElementById('output'),prev=document.getElementById('preview-frame');
    out.style.display='block';prev.style.display='none';document.querySelectorAll('.ide-out-tab').forEach(function(t){t.classList.remove('act')});document.querySelector('[data-o="term"]').classList.add('act');
    out.innerHTML='Ejecutando...';
    document.getElementById('foot-status').textContent='Ejecutando...';

    if(lang==='html'){
        out.style.display='none';prev.style.display='block';document.querySelectorAll('.ide-out-tab').forEach(function(t){t.classList.remove('act')});document.querySelector('[data-o="prev"]').classList.add('act');
        prev.srcdoc=code.includes('<html')?code:'<!DOCTYPE html><html><head><style>body{font-family:sans-serif;padding:20px;max-width:800px;margin:0 auto}</style></head><body>'+code+'</body></html>';
        document.getElementById('foot-status').textContent='Vista previa HTML';
        return;
    }
    if(lang==='javascript'){
        try{var o='';var old=console.log;console.log=function(){o+=Array.from(arguments).join(' ')+'\n'};eval(code);console.log=old;out.innerHTML=o||'Ejecutado (sin salida)';document.getElementById('foot-status').textContent='OK'}
        catch(e){out.innerHTML='Error: '+e.message;document.getElementById('foot-status').textContent='Error'};return;
    }

    fetch('https://emkc.org/api/v2/piston/execute',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({language:lang==='cpp'?'c++':lang,version:'*',files:[{content:code}]})})
    .then(function(r){return r.json().then(function(d){return{status:r.status,data:d}})})
    .then(function(r){
        if(r.status===200){var run=r.data.run||{};out.innerHTML=(run.stdout||'')+(run.stderr?'\n'+run.stderr:'')+(run.output?'\n'+run.output:'')||'Ejecutado (sin salida)';document.getElementById('foot-status').textContent='OK'}
        else{out.innerHTML='Error: '+(r.data.message||r.status)+'\n\nProbá con JavaScript o HTML para ejecución local.';document.getElementById('foot-status').textContent='Error '+(r.status)}
    }).catch(function(){out.innerHTML='Error de conexión al motor de ejecución.\n\nEjecutá en modo JavaScript o HTML.';document.getElementById('foot-status').textContent='Offline'});
}

function guardarProyecto(){
    var n=PROY_ACT?FILES['main'].name.replace(/\.[^.]+$/,''):prompt('Nombre del proyecto:',FILES['main'].name.replace(/\.[^.]+$/,''));
    if(!n)return;
    var body={accion:'guardar',nombre:n,lenguaje:document.getElementById('lang').value,contenido:EDITOR.getValue()};
    if(PROY_ACT)body.id=PROY_ACT;
    fetch('../api/proyectos.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})
    .then(function(r){return r.json()}).then(function(d){
        if(d.ok){PROY_ACT=d.id;msg('Guardado ✓');cargarProyectos()}else msg('Error: '+(d.error||''));
    });
}

function nuevoProyecto(){PROY_ACT=null;FILES['main']={name:'main.py',content:CODE[document.getElementById('lang').value]||CODE['python']};EDITOR.setValue(FILES['main'].content);cambiarLenguaje();msg('Nuevo proyecto')}

function cargarProyecto(id){
    fetch('../api/proyectos.php?id='+id).then(function(r){return r.json()}).then(function(d){
        if(d.ok&&d.id){PROY_ACT=d.id;FILES['main']={name:d.nombre+'.'+extLang(d.lenguaje),content:d.contenido};EDITOR.setValue(d.contenido);document.getElementById('lang').value=d.lenguaje;cambiarLenguaje();msg('Cargado: '+d.nombre)}
    });
}

function eliminarProyecto(id,e){e.stopPropagation();if(!confirm('Eliminar proyecto?'))return;
    fetch('../api/proyectos.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({accion:'eliminar',id:id})})
    .then(function(r){return r.json()}).then(function(d){if(d.ok){if(PROY_ACT===id)PROY_ACT=null;cargarProyectos();msg('Eliminado ✓')}});
}

function cargarProyectos(){
    fetch('../api/proyectos.php'+(FILTRO?'?lenguaje='+FILTRO:'')).then(function(r){return r.json()}).then(function(d){
        var h='';
        (d||[]).forEach(function(p){h+='<div class="ide-file" onclick="cargarProyecto('+p.id+')"><span>📄 '+p.nombre+' <span style="color:#858585;font-size:.65rem">'+p.lenguaje+'</span></span><span class="del" onclick="eliminarProyecto('+p.id+',event)">✕</span></div>'});
        document.getElementById('file-list').innerHTML=h||'<div style="color:#858585;font-size:.7rem;padding:5px 10px">Sin proyectos</div>';
    });
}

function filtrar(l){FILTRO=l;document.querySelectorAll('#lang-list button').forEach(function(b){b.style.background=b.textContent.toLowerCase()===l||(l===''&&b.textContent==='Todos')?'#0e639c':'#2d2d2d'});cargarProyectos()}

function cargarEstudiantes(){
    fetch('../api/proyectos.php?docente=1').then(function(r){return r.json()}).then(function(d){
        var h='';
        (d||[]).forEach(function(p){h+='<div class="ide-file" onclick="cargarProyectoEst('+p.id+')"><span>👤 '+p.usuario_nombre+' <span style="color:#858585;font-size:.65rem">'+p.lenguaje+'</span></span></div>'});
        document.getElementById('est-list').innerHTML=h||'<div style="color:#858585;font-size:.7rem;padding:5px 10px">Sin proyectos</div>';
    });
}

function cargarProyectoEst(id){
    fetch('../api/proyectos.php?docente=1&proyecto_id='+id).then(function(r){return r.json()}).then(function(d){
        if(d.id){PROY_ACT=null;FILES['main']={name:d.nombre+'.'+extLang(d.lenguaje),content:d.contenido};EDITOR.setValue(d.contenido);document.getElementById('lang').value=d.lenguaje;cambiarLenguaje();msg('Viendo: '+d.nombre+' ('+d.usuario_nombre+')')}
    });
}

function extLang(l){return l==='javascript'?'js':l==='typescript'?'ts':l==='python'?'py':l==='html'?'html':l==='php'?'php':l}
function msg(m){var e=document.getElementById('msg');e.textContent=m;setTimeout(function(){e.textContent=''},2500)}

function toggleFull(){
    var w=document.getElementById('ide-wrap');
    w.classList.toggle('ide-full');
    IS_FULL=!IS_FULL;
    if(EDITOR)EDITOR.layout();
}

document.addEventListener('keydown',function(e){
    if((e.ctrlKey||e.metaKey)&&e.key==='Enter'){e.preventDefault();ejecutar()}
    if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();guardarProyecto()}
    if(e.key==='F11'){e.preventDefault();toggleFull()}
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
