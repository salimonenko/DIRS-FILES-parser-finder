<?php
/* Рекурсивно просматривает все файлы проекта, находит там символы + + + (без пробелов), собирает эти данные и выдает клиенту на экран с указанием строк(и) в конкретном каталоге и файле, где эти символы обнаружились. Если будет найдено хотя бы одно вхождение этих символов в файл, то будет показан также значок запуска Проводника Windows. Клик мышью по этому значку откроет Проводник. Путь к каталогу с расположением файла будет иметь примерно такой вид:
C:\server\example.com\DIR\LOCAL_dir_to_show\
и курсор будет установлен на искомом файле (в случае, если в том или вышележащем каталоге сделана сортировка ПО ИМЕНИ; если делать иную сортировку, то курсор может НЕ установиться на искомом файле).
Программа предназначена только для ЛОКАЛЬНОГО использования. Для обеспечения полной безопасности ее работы требуется реализация авторизации и т.п.
Можно запускать непосредственно, разметив ее в требуемом каталоге, например, так: http://example.com/DIR1/DIR2/CHECK_PROBLEMS.php . Тогда поиск будет осуществляться в каталоге /DIR1/DIR2 .
А можно запускать GET-запросом вида http://example.com/DIR1/DIR2/CHECK_PROBLEMS.php?dir=/LOCAL_dir_to_show/ .
При этом поиск файлов с символами + + + будет осуществляться в локальном каталоге, для которого URL является таким: example.com/LOCAL_dir_to_show/ . 

*/
// ************   НАСТРОЙКИ:    ****************** (Начало) //
$windows_SYS_charser = 'cp1251'; // Предварительно кодировка в операционной системе Windows (по умолчанию). Это нужно для корректного чтения и отображения кириллических имен файлов и каталогов, а также для передачи их в качестве параметров Проводнику Windows.

/* Установка внутренней кодировки этого скрипта в UTF-8 */
mb_internal_encoding("utf-8");
$internal_enc = mb_internal_encoding();
mb_regex_encoding("utf-8");

$script_begin = '< script>'; // Псевдотеги для отсылки скриптов JS от сервера клиенту
$script_end = '< /script>';

define('MAX_TITLE_LEN', 600); // Максимальная длина подсказки (подстроки, содержащей символы + + + )

// Если сервер создает виртуальный диск Z:
$root_DIR = array('Z:' => 'C:\Server'); // Корневой каталог, в котором расположен сервер (для Windows): array(Виртуальный => Физический)
// Если виртуальный диск НЕ создается локальным сервером, тогда нужно закомментировать эту строчку

$parent_name = 'unknown'; // Начальный каталог для поиска файлов, содержащих символы + + + (без пробелов). Это - вышележащий каталог по отношению к текущему. Если такого каталога не существует, поиск будет производиться, начиная с каталога, в котором расположен этот (текущий) файл данной программы - при запуске без GET-параметра.

$forbidden_FILE_extensions = array('png', 'pdf', 'bmp', 'jpg', 'gif', 'doc', 'docx', 'svg', 'svgx', 'rar', 'zip', 'xcf', 'cdw', 'mp4', 'webp', 'ico', 'exe', 'mov');  // Файлы с такими расширениями НЕ будут просматриваться

// ************   /НАСТРОЙКИ    ****************** (Конец) //


// Функция открывает программу Explorer (Проводник Windows), а в ней - выделяет файл с именем $file_to_select
// Однако, выделение файла произойдет, если там была заранее настроена сортировка по имени (НЕ по дате).
function start_Explorer($file_to_select, $internal_enc, $Server_enc){
    $command =  'start explorer /select, ' .  ' "' .  $file_to_select. '"'; // . '" > 2>&1 & ';

    exec($command, $exec_output, $exec_res_code);
//    echo '<span style="font-size: 70%">'. mb_convert_encoding($command, $internal_enc, $Server_enc). '</span>';
    if($exec_res_code != 0){ // Ошибка открытия Проводника Windows
        return array($exec_output, $exec_res_code);
    }else{
        return 0;
    }
}


/*class MyException extends ErrorException {
    public function __construct($message, $code = 0, ErrorException $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}*/

// Целесообразно сделать разные исключения для разных случаев

function myExceptionHandler ($e) {
//    error_log($e);
//    http_response_code(500); // Целесообразно, если только не было вывода в браузер, иначе будет ошибка
    if (filter_var(ini_get('display_errors'),FILTER_VALIDATE_BOOLEAN)) { // Для разработчиков, более подробная информация
        if(is_array($e)){
            $e_str = implode('\n\\', $e);
        }else{
            $e_str = $e;
        }

        $e_str = preg_replace('/(\r\n)|(\n)/', '<br/>', $e_str);
        $mess = 'Function '. __FUNCTION__. ' says: '. $e_str;
    } else { // Для конечных пользователей
        $mess = "<h1>500 Internal Server Error</h1>An internal server error has been occurred.<br>Please try again later.";
    }

//    print_r($e); // Полная информация об исключении
//    echo get_class($e); // ErrorException
    Exception_response($mess, 0);

    exit;
}

// задает пользовательскую функцию для обработки всех необработанных исключений
set_exception_handler('myExceptionHandler'); // Обработчик для перехвата исключений (в т.ч. ошибок, превращенных в исключения)

set_error_handler(function ($level, $message, $file = '', $line = 0){
// Превращаем ошибки в исключения. А они обрабатываются при помощи set_exception_handler()
    throw new ErrorException($message, 0, $level, $file, $line);
});

register_shutdown_function(function (){
    $error = error_get_last(); // Если ошибки были перехвачены, то будет пустой массив
    if ($error !== null) { // Если была НЕПЕРЕХВАЧЕННАЯ ошибка (если не было, то пустой массив считается равным null и дальше не будет выполняться)
        $e = new ErrorException(
            'From register_shutdown_function: '.$error['message'], 0, $error['type'], $error['file'], $error['line']
        );
        myExceptionHandler($e);
    }
});

function Exception_response($mess, $flag_die){
    if($flag_die){
        die($mess);
    }else{
        echo $mess;
    }
}


header('Content-Type: text/html; charset=utf-8');


// **********    Обработчик GET-запроса клиента    ********************   //
if(isset($_GET) && !empty($_GET)) { // Если есть параметры в GET-Запросе клиента, добавляем их перед запуском программы

// Имя каталога, в котором будет производиться поиск файлов с пометками о необходимости доработок (+ + + без пробелов)
    if(!isset($_GET['dir']) || strlen($_GET['dir']) > 1000){
        die('Слишком длинное имя каталога (более '. (1000). ' байт).');
    }

    $dir_relative = $_GET['dir']; // Относительный путь к каталогу
}




// **********    Обработчик POST-запроса клиента    ********************   //
if(isset($_POST) && !empty($_POST)){ // Если есть POST-Запрос клиента, чтобы не отправлять страницу заново
    if(isset($_POST['todo'])){

//        echo $script_begin. 'alert(9);'. $script_end; // Чтобы клиент произвел некоторые действия посредством JS (в данном случае - сделал alert)


        if($_POST['todo'] === 'WinExplorer' ){ // На открытие Проводника Windows

            if (OS_determine() !== 'Windows'){
            $err_mess = 'Операционная система, на которой запущен РНР, НЕ определена, как Windows! Поэтому открыть Проводник Windows не получится. Для обеспечения работы следует внести изменения в программу. См. '. $_SERVER['PHP_SELF']. ', стр.'. __LINE__ ;
            die($err_mess);
            }

            if(!isset($_POST['OS_charset'])){
                die('Не задана кодировка, используемая вашей операционной системой. Она может быть, например, <b>cp1251</b> (для Windows 7), <b>utf-8</b> и т.д.');
            }

            $OS_charset = preg_replace('/[^0-9a-zA-Z._-]/', '', $_POST['OS_charset']); // Очищаем наименование кодировки от подозрительных символов


            if(!isset($_POST['data']) || strlen($_POST['data']) > 1000){
                die('Неверный запрос: отсутствует или слишком длинный (более 1000) полный путь к файлу.');
            }


                $path_absolute = $_POST['data'];
                $path_absolute = str_replace(array('<', '>', '"', '?', '*', '|'), '', $path_absolute); // В полных именах файлов не должно быть таких символов

            $mess = 'Задайте правильную кодировку, используемую операционной системой Windows и/или HTTP-сервером. Например, cp1251. <br/>Сейчас задана кодировка <b>'. $OS_charset. '</b>'.'<br/>Чтобы узнать кодировку, установленную в Windows, используется консольная команда CHCP';
                if(preg_match('/[^0-9a-zA-Z.:\/\\_-]/', $path_absolute)){ // Если полное имя файла содержит нелатинские символы (например, кириллицу)
                    try{
                        $path_absolute_enc = mb_convert_encoding($path_absolute, $OS_charset, $internal_enc);
                    }catch (ErrorException $er){
                        die('Неверная кодировка. Попробуйте указать правильную кодировку.');
                    }
                }else{
                    $path_absolute_enc = $path_absolute;
                }


            if(($path_absolute_real = realpath($path_absolute_enc)) == false){
                die('Ошибка2: невозможно открыть этот файл в Проводнике Windows: <b>'. $path_absolute. '</b>');
            }else{ // Если все хорошо, то открываем программу Проводник (Explorer), а в ней - выделяем искомый файл

                if(isset($root_DIR) && $root_DIR != '' && $root_DIR != array()){ // Заменяем виртуальный корневой каталог расположения сервера на физический
                    $root_DIR_virt = key($root_DIR);
                    $path_absolute_real = str_replace($root_DIR_virt, $root_DIR[$root_DIR_virt], $path_absolute_real);
                }

                if($start_Explorer_Arr = start_Explorer($path_absolute_real, $internal_enc, $OS_charset) != 0){
                    $exec_output = $start_Explorer_Arr[0];
                    $exec_res_code = $start_Explorer_Arr[1];
                    print_r($exec_output); // Вывод команды exec в случае ошибки
                    die(mb_convert_encoding('В результате попытки показа файла в Проводнике Windows возникла ошибка. Вот ее код: '. $exec_res_code, $OS_charset, $internal_enc));
                }

                die('<div style="font-size: 90%">Файл <div style="line-height: 90%; font-weight: bold">'. mb_convert_encoding($path_absolute_real, $internal_enc, 'cp1251'). '</div> показан в Проводнике Windows.</div>');
            }

        }

    }

die();
}



?><!DOCTYPE html>
<html lang="ru">
<head>

    <title>Проверка файлов на наличие символов + + + (без пробелов)</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="description" content="Программа может быть установлена в любом каталоге локального сервера. Она просматривает каталоги, файлы (в том числе, в подкаталогах) и ищет там символы + + + (без пробелов), т.е. ранее сделанные пометки/комментарии об ошибках. Если найдет, то выводит на экран каталог, имя соответствюущего файла и номер строки в нем, где содержатся такие символы." />

    <link rel="icon" href="/favicon.png" type="image/png" />

<style>
* {box-sizing: border-box}

tr, td, p {line-height: 100%; margin: 0; padding: 0; font-size: 14px}
p {padding: 1px}

.Escape_closable{ } /*  Блоки, имеющие этот класс, скрываются (display: none) после нажатия на клавишу Escape  */

.button_clean_input {color: red; font-weight: bold; font-size: 20px; width: 20px; text-align: center; vertical-align: middle; cursor: pointer; }

.button_WinExplorer, .button_clean_input {float: right;  display: inline-block; padding: 0 1px 1px 0; margin-right: 2px; border-color: #FFF #505050 #505050 #FFF; border-width: 1px; border-style: inset; box-shadow: 1px 1px 4px 2px rgba(185, 185, 185, 0.94); background: none repeat scroll 0% 0% #F3F3F3; }

.button_WinExplorer {max-height: 15px;}


.button_WinExplorer:hover, .button_clean_input:hover { background-color: #CEB867; display: inline-block; padding: 0 1px 1px 0; vertical-align: middle; border-width: 1px; border-style: inset; border-color: rgb(255,255,255) rgb(80,80,80) rgb(80,80,80) rgb(255,255,255); box-shadow: 1px 1px 4px 2px rgba(185, 185, 185, 0.94); }

.button_WinExplorer:active, .button_clean_input:active { padding: 1px 0 0 1px; border: 1px solid; border-color: rgb(80,80,80) rgb(255,255,255) rgb(255,255,255) rgb(80,80,80); box-shadow: -2px -2px 7px 5px rgba(161, 161, 161, 0.59) inset; background-color: #b9b9b9; }


#id_response-info {float: left; min-height: 0; border: none; background-color: wheat; padding: 2px}


.response-info_additional { display: inline-block; min-height: 20px; max-height: 300px;  }

#input_charset {position: relative; width: 200px; height: 25px}
#clean_input_charset {}

.line{ background-color: #59E665; padding: 0 3px; cursor: pointer; }
.line:hover {background-color: #CEB867;}
.line:active {background-color: #b9b9b9;}

#begin_FIND {display: inline-block; margin: 0 0 0 10px; padding: 0 1px 1px 0; border: 1px; border-color: transparent; border-style: inset; vertical-align: top; }

#begin_FIND > div { width: 0px; height: 0px; border-top: 15px solid transparent; border-left: 35px solid #30C626; border-bottom: 15px solid transparent;  }

#begin_FIND:hover  {background-color: #CEB867; display: inline-block; padding: 0 1px 1px 0;  border-width: 1px; border-style: inset; border-color: rgb(255,255,255) rgb(80,80,80) rgb(80,80,80) rgb(255,255,255); box-shadow: 1px 1px 4px 2px rgba(185, 185, 185, 0.94);}

#begin_FIND:active {padding: 1px 0 0 1px; border: 1px solid; border-color: rgb(80,80,80) rgb(255,255,255) rgb(255,255,255) rgb(80,80,80); box-shadow: -2px -2px 7px 5px rgba(161, 161, 161, 0.59) inset; background-color: #b9b9b9; }
</style>


</head>
<body>

<!--   Этот блок будет закрываться (display: none) при нажатии на клавишу Escape   -->
<div class="Escape_closable" style="position: fixed; z-index: 1000; display: none"><div class="button_clean_input" title="Закрыть">&times;</div>
    <div id="id_response-info" class="response-info_additional"></div>

</div>

<div  class="response-info_additional">
    <div style="display: table">
        <div style="display: table-row">
            <div style="display: table-cell; vertical-align: top"><input id="input_charset" title='Введите СИСТЕМНУЮ кодировку, которая установлена на компьютере. Например, Windows-1251, UTF-8 или т.п. По умолчанию - <?php echo $windows_SYS_charser; ?>' placeholder="Введите кодировку Вашей ОС..." /></div>
            <div id="clean_input_charset" class="button_clean_input" title="Очистить" style="display: table-cell; vertical-align: top">&times;</div>
            <div style="display: table-cell; vertical-align: middle; padding-left: 30px; min-width: 290px; background-color: wheat; font-family: Arial">По умолчанию используется: <b><?php echo $windows_SYS_charser; ?></b> </div>
        </div>
    </div>
</div>

<div id="begin_FIND" title="Запустить поиск (страница будет обновлена)"><div  onclick="location.reload(); return false;" ></div></div>

<?php


if(!isset($dir_relative)){ // При запуске непосредственно из какого-нибудь каталога

    // Если в GET-параметре не указан каталог (переменная $dir_relative), то
// Рекурентно определяем абсолютный путь к начальному каталогу
// Если указать пустой или несуществующий, то поиск будет проводиться в текущем каталоге __DIR__
    $path = PATH(__DIR__, $parent_name); // Абсолютный путь до начального каталога

}else{ // При запуске из /LOCAL_only/local.php
    $path = $_SERVER['DOCUMENT_ROOT']. $dir_relative; // Абсолютный путь до начального каталога
}

$base_name = basename($path);
$path_initial = dirname($path);

$entry = ''; // Для начала

//Кнопка вызова Проводника Windows
define('BUTT_WIN_EXPLORER', '<input data-onclick="WinExplorer" class="button_WinExplorer" src= '. butt_win_explorer() .' title="Открыть в проводнике Windows" alt="Открыть проводник Windows" type="image">');


$files = FILES_in_DIR($path, $forbidden_FILE_extensions); // Число файлов в текущем каталоге

if($files === -1){
    die('Ошибка! <b>'. $path. '</b> не является каталогом.');
}

if ( __DIR__ === $path ){
    echo '<p>Для текущего каталога '. __DIR__. ' не найден родительский каталог <b>'. $parent_name .'</b>. Поэтому поиск символов +'.'+'.'+ будет производиться в текущем каталоге: <br/><b>'. __DIR__ . '</b></p>';
}


echo '<table data-name="DIR_FILE" border="1" cellspacing="0"><tbody>';
echo '<tr data-type="DIR"><td><p title="Каталог" style="background-color: #ffe38e; display: table; margin-top: 0">'.$base_name .'</p></td><td style="text-align: center;">Строчки, где содержатся <br/>символы <b>+'.'++</b> :</td></tr>';


show_FILES_NAMES($files, $path, 0); // Вначале выводим имена файлов в текущем каталоге

look_dir($path, $path_initial, $base_name, 0, $entry, $forbidden_FILE_extensions); // В подкаталогах


echo '</tbody></table>';
echo '</body></html>';

function look_dir($path, $path_initial, $base_name, $i_num, $entry, $forbidden_FILE_extensions){

    chdir($path);
    if (($handle = opendir($path)) ){

        while (false !== ($entry = readdir($handle))) {

                if (is_dir($entry)) { // Если каталог
                    if (($entry == ".") || ($entry == "..")) {
                        continue;
                    }

                    $entry = realpath($entry);

                    $files = FILES_in_DIR($entry, $forbidden_FILE_extensions); // Массив файлов в текущем каталоге (только файлы, без каталогов)
// Если есть файлы, то вначале нужно вывести имя текущего каталога, затем - их список; а потом уже - ДРУГИЕ каталоги

                    $val = preg_replace('/^'. preg_quote(str_replace('\\', '/',$path_initial).'/', '/') . '/', '', str_replace('\\', '/', $entry));
                    $val = str_replace('\\', '/', $val);

// При отображении имен каталогов размер отступа пропорционален числу символов / в полном пути
                    $i_num = substr_count($val, '/') + 1;


                    echo '<tr data-type="DIR"><td><p title="Каталог" style="background-color: #ffe38e; display: table; margin-top: 10px; margin-left: '. (15*$i_num). 'px">' . mb_convert_encoding($val, 'utf-8', 'cp1251'). /* sizeof($files). */ '</p></td><td></td></tr>';


                    show_FILES_NAMES($files, $entry, $i_num); // Выводим имена файлов

                    look_dir($entry, $path_initial, $base_name, $i_num, $entry, $forbidden_FILE_extensions);
                }
        }
        closedir($handle);
        chdir('..');
    }else{
        echo 'Каталог '. realpath($entry). ' не может быть открыт.';
    }

}

function show_FILES_NAMES($files, $entry, $i_num){
    if(sizeof($files)){ // Если в текущем каталоге есть файлы

        for($j=0; $j < sizeof($files); $j++){

            $file_entry = realpath($entry. '/'. $files[$j]);

            $line = '';
            if(file_exists($file_entry)){
                $str_Arr = file(($file_entry));

                for($i=0; $i < sizeof($str_Arr); $i++){
                    $title = '';

                    if(substr_count($str_Arr[$i], '+'.'+'.'+') > 0){
                        $title = substr(htmlspecialchars($str_Arr[$i]), 0, MAX_TITLE_LEN);

                        if($line){
                            $z = ', ';
                        }else{
                            $z = '';
                        }
                        $line = $line. $z. '<span class="line" title="'. $title. '">' .($i+1). '</span>';
                    }
                }
            }

            $line = $line ? 'Строчки '. $line. BUTT_WIN_EXPLORER : '';
            echo '<tr data-type="FILE"><td><p  title="Файл" style="display: block; background-color: #9bf3ff; margin-left: '. (15*($i_num+0.5)). 'px;">'. '<span>'.   mb_convert_encoding(basename($file_entry), 'utf-8', 'cp1251'). '</span></p></td><td>'. $line. '</td></tr>';
        }
    }

}

// Рекурентно определяем абсолютный путь к начальному каталогу
function PATH($path, $dir, $i=0){
/* $path - текущий каталог (в нем расположена эта программа), начиная с которого будет идти поиск РОДИТЕЛЬСКОГО каталога с именем $dir
 * $dir - искомый РОДИТЕЛЬСКИЙ каталог, в котором и в подкаталогах которого будет производиться поиск номеров строчек в файлах,
 * в которых имеются символы  + + + (без пробелов)
 *
 * Поиск родительского каталога продолжается не более 10 раз (итераций)
 */
    if(!$dir){
        return $path;
    }

    $path_begin = $path;

    while (basename($path) !== $dir && $i++ < 10){
        if($path === realpath($path. '/../')){ // Значит, дошли до корневого каталога

            return $path_begin;
        }

        $path = realpath($path. '/../');

    }

    return $path;
}

function FILES_in_DIR($path, $forbidden_FILE_extensions){ // Возвращает:
/* -1, если $path НЕ является каталогом,
 * false, если в каталоге $path НЕТ файлов,
 * true, если в каталоге $path ЕСТЬ файлы (точнее, не каталоги).
 */
    if(is_dir($path)){ // Требуется ПОЛНЫЙ путь
        $path_Arr = scandir($path);
    }else{
        return -1;
    }

    $rez_Arr = array_filter($path_Arr, function ($name) use ($path){
        return !is_dir(realpath($path. '/'. $name)); // Каталоги НЕ включаем в массив
    });

// Оставляем в массиве файлов только те, расширения которых НЕ содержатся в списке (массиве) запрещенных файлов. Т.е. за исключением рисунков, видео, pdf и пр.
    $rez_Arr = array_filter($rez_Arr, function ($file_name) use ($forbidden_FILE_extensions){
                       return !in_array(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)), $forbidden_FILE_extensions);
                    });
    $rez_Arr = array_values($rez_Arr);

return array_values($rez_Arr);
}

function OS_determine(){
// Определяем вид операционной системы (например, Windows)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') { // Пока будет работать только в Windows
        $OS = 'Windows';
    } else {
        $OS = 'Unknown';
    }

return $OS;
}

?>

<script type="text/javascript">

(function () {


// Сохраняем кодировку операционной системы, введенную пользователем, в сессии браузера и в QOOKIES
var OS_charset = document.getElementById('input_charset').value; // В первую очередь берем кодировку, если она задана в поле <input />
    if(OS_charset){
        sessionStorage.setItem('OS_charset', encodeURIComponent(OS_charset));
        setQookies('OS_charset');

    }else if(sessionStorage.getItem('OS_charset')){
        OS_charset = sessionStorage.getItem('OS_charset');
        setQookies('OS_charset');

    }else if(document.cookie && getCookie('OS_charset') !== 'undefined'){
        sessionStorage.setItem('OS_charset', encodeURIComponent(OS_charset));
    }

// Если все-таки кодировка оказалась НЕ заданной, тогда устанавливаем по умолчанию
    if(!OS_charset){
        OS_charset = '<?php echo $windows_SYS_charser; ?>';
    }


var buttons_WinExplorer = document.getElementsByClassName('button_WinExplorer');

    for(var i=0; i < buttons_WinExplorer.length; i++){
        buttons_WinExplorer[i].onclick = function () {

            var fileName = this.parentNode.parentNode.getElementsByTagName('p')[0]; // Тег p с именем файла, где содержатся требуемые символы

            if(!fileName){
                alert('Ошибка! Не удалось определить имя файла, в котором содержатся символы +' + '+' + '+');
                return;
            }else{

                var tr = fileName;
                var i = 0;
                while (tr.tagName.toLowerCase() !== 'tr' ){ // Не более 50 итераций, на всякий случай
                    tr = tr.parentNode;
                    if(++i > 50){
                        alert('Не удалось найти родителя элемента с именем выбранного файла, имеющего тег <tr>. Поэтому не получится показать этот файл в Проводнике Windows');
                        return;
                    }
                }
                var fileBaseName = tr.getElementsByTagName('td')[0].textContent;

                while (tr.getAttribute('data-type') !== 'DIR'){
                    tr = tr.previousElementSibling; // Ищем вышестоящего соседа найденного тега <tr data-type="FILE">

                    if(!tr){
                        alert('Ошибка! Почему-то не найден вышестоящий сосед тега с найденным именем файла.');
                        return;
                    }
                }
                var dirName_relative = tr.getElementsByTagName('td')[0].textContent;
//                dirName_relative = dirName_relative.split('/').slice(1).join('/');


// Это - абсолютный путь к файлу, в котором содержатся искомые символы (+ + +). Этот путь нужно передать на сервер и открыть его в проводнике
                var path_absolute = '<?php echo str_replace('\\', '/', $path_initial); ?>'+ '/' +dirName_relative + '/'+ fileBaseName;

                var method = 'POST';
                var todo = encodeURIComponent(this.getAttribute('data-onclick'));
                var id_to_RESPONSE = encodeURIComponent('id_response-info');
                var data = encodeURIComponent(path_absolute);
                var URL_PHP = ''; // Запрос тому же самому файлу PHP
                var arg_Arr = []; // Будет передан функции functionAfter()
                    arg_Arr[0] = document.getElementById('id_response-info').parentNode;
                    arg_Arr[1] = 'block';


//Вначале берем кодировку из <input />; если ее там нет, то смотрим сохраненную в сессии браузера.
                OS_charset = document.getElementById('input_charset').value || sessionStorage.getItem('OS_charset');

// Если не латинскоязычное полное имя файла, то нужно ввести кодировку операционной системы (если она не была введена ранее)
                if(!OS_charset && /[^\&0-1a-zA-z_\\\/+\[\]\{\}\(\):;'~!.-]/.test(path_absolute)){

                    alert('Введите кодировку вашей операционной системы. Для русскоязычной Windows кодировка может быть cp1251'+ path_absolute);
                    return;
                }
                OS_charset = encodeURIComponent(OS_charset);

                sender(method, OS_charset, todo, id_to_RESPONSE, data, URL_PHP, function_SHOW_CLOSE_After, arg_Arr); // Посылаем запрос на сервер методом POST
            }
        }
    }




// Задаем обработчики закрытия (display: none) блоков (тех, которые нужно своравивать или разворачивать при кликах на определенные кнопки)
var id_response_info = document.getElementById('id_response-info');
    id_response_info.parentNode.firstChild.addEventListener('click', function () {
            var arg_Arr = [];
            arg_Arr[0] = this.parentNode;
            arg_Arr[1] = 'none';

        function_SHOW_CLOSE_After(null, arg_Arr, null);
        }, false);



document.getElementById('clean_input_charset').onclick = function () { // Очищаем поле для ввода кодировки сервера и/или операционной системы
    document.getElementById('input_charset').value = '';
};



// Задаем обработчик клавиш
document.onkeydown = function ( event ) {

     if (event.keyCode === 27){ // Escape

         // закрываем все блоки (окна) с классами "Escape_closable", которые следует закрыть при нажатии на Escape.
         var Escape_closable = document.getElementsByClassName('Escape_closable');
         for(var i=0; i < Escape_closable.length; i++){
             Escape_closable[i].style.display = 'none';
         }

     }
};


// возвращает куки с указанным name или undefined, если ничего не найдено
function getCookie(name) {
  var matches = document.cookie.match(new RegExp("(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"));
  return matches ? decodeURIComponent(matches[1]) : undefined;
}


function function_SHOW_CLOSE_After(serverText, arg_Arr, Server) { // Функция - переключатель видимости блока, в потомок которого приходит ответ сервера

    if(arg_Arr) {
        var tag = arg_Arr[0];
        var to_do = arg_Arr[1]; // Например, 'block'
        var style = tag.style;

        if (!to_do) { // Если НЕ указано требуемое свойство для display
            if (style.display === 'none') {
                style.display = 'block';
            } else {
                style.display = 'none';
            }

        } else { // Если указано
            style.display = arg_Arr[1];
        }

return style.display;
    }
return -1;
}



function sender(method, OS_charset, todo, id_to_RESPONSE, data, URL_PHP, functionAfter, arg_Arr) { // Функция отправляет сообщение на сервер  и ждет того или иного ответа, выводя потом его в alert
    var xhr = new XMLHttpRequest();

    var body = 'data=' + data + '&OS_charset='+OS_charset+'&todo='+todo;

    xhr.open(method, URL_PHP, true); // Имена всех методов посылаем только методом POST
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function xhr_state() {
        if (xhr.readyState != 4) return;
        if (xhr.status == 200) {
            var response = xhr.responseText;

console.log(response);

                var script_begin = '<?php echo $script_begin; ?>'; // Видимо, PHP сам экранирует \, превращая их в \\
                var script_end = '<?php echo $script_end; ?>';     // А так, в JS 'new RegExp' нужно ОБЯЗАТЕЛЬНО(!) экранировать вручную

                var script_reg_begin = new RegExp(script_begin, 'g');
                var script_reg_end = new RegExp(script_end, 'g');

                var script_begin_matched = response.match(script_reg_begin);
                var script_end_matched = response.match(script_reg_end);

                if(script_begin_matched && script_end_matched){ // Если в ответе сервера есть псевдотеги для JS
                    if(script_begin_matched.length !== script_end_matched.length){
alert("Некорректный ответ сервера: число содержащихся в нем псевдотегов "+ script_end.replace('\\', '') +" не совпадает:\n" + "Число "+ script_begin +" равно "+ script_begin_matched.length + ", а число "+ script_end.replace('\\', '') +" равно "+ script_end_matched.length);
                        return;
                    }
                }

            try {

                var script_reg = new RegExp(script_begin+ '([\\s\\S]*?)' + script_end, 'g'); // ДВОЙНОЕ экранирование для RegExp
                var serverText = response.replace(script_reg, ''); // Содержимое ответа сервера, за исключением вырезанных из него псевдоскриптов
                var serverScripts = response.match(script_reg); // Массив псевдоскриптов или null


                if(serverScripts){ // Если в ответе сервера есть псевдотеги script_begin...script_end
                    console.log('В ответе сервера есть следующие JS-скрипты:');
                    var script_body = '';
                        for (var i=0; i < serverScripts.length; i++){
                            script_body += serverScripts[i].replace(script_reg, '$1') + "\n"; // Вырезаем основную часть псевдоскрипта (без псевдотегов)
                        }
                    console.log(script_body);

                    var y = document.createElement('script');
                    y.defer = true; //Даём разрешение на исполнение скрипта после его "приживления" на странице
                    y.text = script_body;
                    document.body.appendChild(y);
                }

                // Окончательно, вставляем текстовую часть ответа сервера в требуемый блок:
                document.getElementById(id_to_RESPONSE).innerHTML = serverText;
//                console.log(xhr.responseText)

                if(functionAfter != ''){ // Выполняем функцию с именем functionAfter в зависимости от request и ответа сервера
//                        var func = new Function('return ' + functionAfter)();
//                    func.call(window.document, request, scriptBODY);
                    var Server = xhr.getResponseHeader("Server"); // Пока ненужная переменная, на запас

                    functionAfter(serverText, arg_Arr, Server);
                }


            }catch (er){
                console.log(er);
                alert('Ошибка! См. консоль.');
            }

        }else {
            alert('xhr message: '+xhr.statusText); // Сообщение об ошибке на транспортном (ТСР) уровне. Обычно вызвано проблемами  с доступом к сети или неправильной работой РНР на сервере, т.п.
        }
//            document.getElementById(id_to_RESPONSE).innerHTML = xhr.responseText; // Ответ придет в блок с id=id_to_RESPONSE
    };
        xhr.send(body);
        return false;
 }


function setQookies(name) {
    var cookie_date = new Date();
    cookie_date.setYear(cookie_date.getFullYear() + 1);
    document.cookie = name + "="+ encodeURIComponent(OS_charset) + ";expires=" + cookie_date.toUTCString();
}

})();


</script>


<?php

// Иконка для кнопка вызова Проводника Windows
function butt_win_explorer(){

return 'data:image/png;base64,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';
 }


