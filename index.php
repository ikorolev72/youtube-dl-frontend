<?php
/*
Split and stitch video
Author Korolev Igor
https://github.com/ikorolev72
2018.08.02
version 1.0
 */
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Split and stitch video</title>
	<LINK href='main.css' type=text/css rel=stylesheet>
<script>
  function confirm_prompt( text,url ) {
     if (confirm( text )) {
      window.location = url ;
    }
  }

function checkTime(t) {
  let re = /^(\d\d):(\d\d):(\d\d(\.\d*)?)$/;
  if (re.test(t)) {
    return (true);
  }
  re = /^(\d\d):(\d\d(\.\d*)?)$/;
  if (re.test(t)) {
    return (true);
  }
  alert( "incorrect time value "+t+ ".  Must be in [HH:]MM:SS[.m...] format. Eg 00:00:15.02")
  return (false);
}


</script>
</head>
<body>



<?php
echo "
<a href=" . $_SERVER['PHP_SELF'] . "> Home </a>
<h2>youtube-dl web interface</h2>
";

include_once "common.php";

//$debug = true;
$debug = false;
$basedir = dirname(__FILE__);
$main_upload_dir = "$basedir/uploads/";
$main_upload_url = "./uploads";
$bin_dir = "$basedir/bin";
$tmp_dir = "/tmp";

$today = date("F j, Y, g:i a");
$dt = date("U");

$in = array();
$data = null;

$json_in = common::get_param('in');
if ($json_in) {
    $in = json_decode($json_in, true);
    if (!$in) {
        common::$errors[] = "Incorrect json string in parameter 'in'";
        echo common::showErrors();
        echo common::showMessages();
        exit(1);
    }
} else {
    $in = array();
    $in['step'] = 0;
}

foreach ($_REQUEST as $k => $val) {
    if ('in' == $k) {
        continue;
    }

    $in[$k] = common::get_param($k);
}
if ($debug) {
    echo "<pre>";
    echo var_dump($in);
    echo "</pre>";
}

$string = json_encode($in, JSON_PRETTY_PRINT);

// check if all data is right
if (10 == $in['step']) {
    $countOfSelectedFormat = 0;
    foreach ($in["format"] as $format) {
        if ($format) {
            $countOfSelectedFormat++;
        }
    }
    if (0 === $countOfSelectedFormat) {
        common::$errors[] = "Please select one or several sources for downloading";
        $in['step'] = 5;
    }
}

if (10 == $in['step']) {
    common::$messages[] = "Processing url '" . $in['input'] . "'";
    echo common::showErrors();
    echo common::showMessages();
    $availableSubtitles=common::getAvailableSubtitles( $in['input'] );
    echo "<h3>Please, select subtitles you would like to download</h3>
      <form action='index.php' method='post' multipart='' enctype='multipart/form-data'>
      <table border=1>
      <tr>
      <td></td>
      <td>Type</td>
      <td>Language</td>
      <td>Formats</td>
      </tr>
      <tr>
      <td>  <input type='radio' value='' name='subtitles' id='subtitles' selected> </td>
      <td colspan=3>  Do not download subtitles </td>
    </tr>";      
    $i = 0;
    foreach ( $availableSubtitles as $line) {
        $subType = ( $line["type"]==="subtitles")? " --write-sub " : " --write-auto-sub "  ;
        $lang = $line["language"];
        $formats = $line["formats"];
        echo "<tr>
        <td>  <input type='radio' value='$formatCode' name='subtitles'  id='subtitles'> </td>
        <td>  $formatCode </td>
        <td>  $extension </td>
        <td>  $resolution </td>
        <td>  $note </td>
      </tr>";
        $i++;
    }
    echo "</table>
    <br>
    <input type='submit'  name='save' id='save' value='Go'> </td>
      <input type='hidden'  name='step' id='step' value='10'>
      <input type='hidden'  name='in' id='in' value='$string'>
      </form>
      </body>
      </html>
      ";
    exit(0);    
}




if (5 == $in['step']) {
    $formats = common::getAvailableFormats($in['input']);
    common::$messages[] = "Processing url '" . $in['input'] . "'";
    echo common::showErrors();
    echo common::showMessages();
    if (!$formats) {
        echo " <br><br>Please, <a href=''> try another url or try again </a> </body> </html> ";
        exit(0);
    }

    echo "<h3>Please, select sources you would like to download</h3>
      <form action='index.php' method='post' multipart='' enctype='multipart/form-data'>
      <table border=1>
      <tr>
      <td></td>
      <td>format code</td>
      <td>extension </td>
      <td>resolution</td>
      <td>note</td>
      </tr>
      ";
    $i = 0;
    foreach ($formats as $line) {
        $formatCode = $line[1];
        $extension = $line[2];
        $resolution = $line[3];
        $note = $line[4];
        echo "<tr>
        <td>  <input type='checkbox' value='$formatCode' name='format[$i]'  id='format[$i]'> </td>
        <td>  $formatCode </td>
        <td>  $extension </td>
        <td>  $resolution </td>
        <td>  $note </td>
      </tr>";
        $i++;
    }
    echo "</table>
    <br>
    <input type='submit'  name='save' id='save' value='Go'> </td>
      <input type='hidden'  name='step' id='step' value='10'>
      <input type='hidden'  name='in' id='in' value='$string'>
      </form>
      </body>
      </html>
      ";
    exit(0);
}

// if step 0
if (!$in['step']) {
    echo common::showErrors();
    echo common::showMessages();
    if (!isset($in['input'])) {
        $in['input'] = '';
    }
    echo "<h3>Please, enter youtube video url</h3>
    <form action='index.php' method='post' multipart='' enctype='multipart/form-data'>
    <table>
    <tr>
      <td><input type='text' name='input' value='" . $in['input'] . "' size=50></td>
    </tr>
    <tr>
      <td><input type='submit'  name='save' id='save' value='Go'> </td>
    </tr>
    </table>
    <input type='hidden'  name='step' id='step' value='5'>
    </form>
  </body>
</html>
";
    exit(0);
}