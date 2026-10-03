<?php
// MAXIM portfolio API: server-side login + project storage + moderation.
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$DATA_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'data';
$DATA_FILE = $DATA_DIR . DIRECTORY_SEPARATOR . 'projects.php';
$ADMIN_USER = getenv('PORTFOLIO_ADMIN_USER') ?: 'maxim';
$ADMIN_HASH = getenv('PORTFOLIO_ADMIN_HASH') ?: '$2y$12$1ZD50r.LBXdsYLVjaxXm6uJ2XxV7xIxwmaTcZPbmtve9HvHz.NayK';
// If you don't configure env vars, setup.php can replace this hash with your own password hash.

if (!is_dir($DATA_DIR)) { @mkdir($DATA_DIR, 0755, true); }
if (!file_exists($DATA_FILE)) {
    $initial = [
        ['id'=>'motor','title'=>'Мотор на велосипеде','category'=>'HARDWARE / DIY','icon'=>'construction','desc'=>'Проект по установке и настройке мотора на велосипед: идея, подбор компонентов и сборка.','tag'=>'ВЕЛО · МЕХАНИКА'],
        ['id'=>'ai','title'=>'Тренировка кастомного ИИ','category'=>'AI / CURRENT','icon'=>'smart_toy','desc'=>'Экспериментирую с обучением и настройкой собственного ИИ под конкретные задачи.','tag'=>'CURRENT PROJECT']
    ];
    @file_put_contents($DATA_FILE, '<?php return ' . var_export($initial, true) . ';', LOCK_EX);
}

function respond($data, $code=200) { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function input_json() { $raw=file_get_contents('php://input'); $d=json_decode($raw,true); return is_array($d)?$d:[]; }
function projects() { global $DATA_FILE; $d=@include $DATA_FILE; return is_array($d)?$d:[]; }
function save_projects($d) { global $DATA_FILE; $php='<?php return ' . var_export(array_values($d), true) . ';'; return @file_put_contents($DATA_FILE,$php,LOCK_EX)!==false; }
function norm($s) {
    $s=mb_strtolower((string)$s,'UTF-8');
    $s=strtr($s,['ё'=>'е','Ё'=>'е','а'=>'a','А'=>'a','е'=>'e','Е'=>'e','о'=>'o','О'=>'o','р'=>'p','Р'=>'p','с'=>'c','С'=>'c','х'=>'x','Х'=>'x','у'=>'y','У'=>'y','і'=>'i','І'=>'i']);
    $s=preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]/u','',$s);
    $s=strtr($s,['4'=>'a','@'=>'a','3'=>'e','1'=>'i','!'=>'i','|'=>'i','0'=>'o','5'=>'s','$'=>'s','7'=>'t']);
    return preg_replace('/[\s\._,;:!?\-\\\/|*~`^\'"()\[\]{}<>+=]+/u','',$s);
}
function moderation_ok($p) {
    $bad=[
      '/х[уy][еёe3]?[йиi]?/u','/п[иi1][з3][дd]/u','/[еe3]б(?:а|о|у|и|е|ё|ь|ть|н|л)/u','/бл[яya]/u','/с[уy][кk][аa]/u','/долб(?:о|а)ё?б/u',
      '/f+u+c+k+/i','/s+h+i+t+/i','/b+i+t+c+h+/i','/n+i+g+g+/i','/k+i+k+e+/i'
    ];
    $text=norm(implode(' ',[$p['title']??'',$p['category']??'',$p['tag']??'',$p['desc']??'']));
    foreach($bad as $re) if(preg_match($re,$text)) return false;
    return mb_strlen(trim($text),'UTF-8')>=2;
}

$method=$_SERVER['REQUEST_METHOD'];
$action=$_GET['action'] ?? 'projects';

if ($action==='status' && $method==='GET') respond(['ok'=>true,'authenticated'=>isset($_SESSION['admin'])&&$_SESSION['admin']===true,'user'=>$_SESSION['user']??null]);

if ($action==='login' && $method==='POST') {
    $d=input_json(); $user=trim((string)($d['username']??'')); $pass=(string)($d['password']??'');
    if ($user===$ADMIN_USER && password_verify($pass,$ADMIN_HASH)) {
        session_regenerate_id(true); $_SESSION['admin']=true; $_SESSION['user']=$user; respond(['ok'=>true]);
    }
    usleep(250000); respond(['ok'=>false,'error'=>'Неверный логин или пароль.'],401);
}
if ($action==='logout' && $method==='POST') { $_SESSION=[]; if(ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Lax']); } session_destroy(); respond(['ok'=>true]); }

if ($action==='projects' && $method==='GET') respond(['ok'=>true,'projects'=>projects()]);

if (!isset($_SESSION['admin']) || $_SESSION['admin']!==true) respond(['ok'=>false,'error'=>'Требуется вход.'],401);

if ($action==='add' && $method==='POST') {
    $d=input_json();
    $p=[
      'id'=>bin2hex(random_bytes(8)),
      'title'=>trim((string)($d['title']??'')),
      'category'=>trim((string)($d['category']??'PROJECT')),
      'icon'=>preg_match('/^[a-zA-Z0-9_]+$/',(string)($d['icon']??''))?(string)$d['icon']:'code',
      'desc'=>trim((string)($d['desc']??'')),
      'tag'=>trim((string)($d['tag']??'NEW PROJECT'))
    ];
    if ($p['title']==='' || $p['desc']==='') respond(['ok'=>false,'error'=>'Название и описание обязательны.'],422);
    if (mb_strlen($p['title'])>70 || mb_strlen($p['category'])>40 || mb_strlen($p['desc'])>300 || mb_strlen($p['tag'])>28) respond(['ok'=>false,'error'=>'Слишком длинное поле.'],422);
    if (!moderation_ok($p)) respond(['ok'=>false,'error'=>'Проект не добавлен: обнаружено недопустимое оскорбительное или дискриминационное содержимое.'],422);
    $list=projects(); $list[]=$p; if(!save_projects($list)) respond(['ok'=>false,'error'=>'Не удалось сохранить проект на сервере.'],500); respond(['ok'=>true,'project'=>$p]);
}
if ($action==='delete' && $method==='POST') {
    $d=input_json(); $id=(string)($d['id']??''); if($id==='') respond(['ok'=>false,'error'=>'Нет ID.'],422);
    $list=array_values(array_filter(projects(),fn($p)=>($p['id']??'')!==$id)); if(!save_projects($list)) respond(['ok'=>false,'error'=>'Не удалось сохранить изменения.'],500); respond(['ok'=>true]);
}
respond(['ok'=>false,'error'=>'Неизвестный запрос.'],404);
