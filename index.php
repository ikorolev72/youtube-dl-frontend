<?php
/*
web interface for youtube-dl
Author Korolev Igor
https://github.com/ikorolev72
2018.08.02
version 1.3
 */
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>youtube-dl frontend</title>
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
[<a href=" . $_SERVER['PHP_SELF'] . "> Home </a>]
[<a href='list.php'> S3 file list </a>]
<h2>youtube-dl web interface</h2>
";

include_once "common.php";

//$debug = true;
$debug = false;
$basedir = dirname(__FILE__);
$bin_dir = "$basedir/bin";
$tmpDir = "/tmp/youtube-dl";
$logDir = "$basedir/logs";
$logUrl = "./logs";


$today = date("F j, Y, g:i a");
$dt = date("U");

$in = array();
$data = null;
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir);
}

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

// check if all data is right and download
if (20 == $in['step']) {
    $youtubeId = common::getYoutubeIdFromUrl($in['input']);
    $fileName = "${youtubeId}_" . date("Y-m-d_H_i_s");
    $logFile = "$logDir/$fileName.html";
    $logUrl = "$logUrl/$fileName.html";

    $input = $in["input"];
    $outputFileMask = $in["outputFileMask"];
    if (!isset($in["subtitles"])) {
        $in["subtitles"] = '';
    }

    $formats = array();
    foreach ($in["format"] as $format) {
        if ($format) {
            $formats[] = $format;
        }
    }

    $options = " -f " . join(",", $formats);
    $options .= " -m $outputFileMask ";
    if ($in["subtitles"] !== "external") {
        $options .= $in["subtitles"];
    }

    $cmd = '';
    if ($in["subtitles"] === "external") {
        $cmd .= "/usr/bin/php " . common::$utilDir . common::$awsYoutubeSubtitles . " -i $input -s $tmpDir/$outputFileMask.vtt && ";
    }
    $cmd .= "/usr/bin/php " . common::$utilDir . common::$youtubeToS3 . " -i $input $options  ";
    $cmd = " ( $cmd ) >>$logFile 2>&1 & ";
    $html = getTemplateYoutubeDl($in['input']);
    if ($debug) {
        $html .= "<br><pre>Execute command " . htmlentities($cmd) . "</pre>";
    }

    try {
        file_put_contents($logFile, $html);
    } catch (Exception $e) {
        common::$errors[] = "Error: Cannot save file $logFile. " . $e->getMessage();
        echo common::showErrors();
        echo common::showMessages();
        echo "</body></html>";
        exit(1);
    }
    exec($cmd, $output, $return);
    sleep(2);
    header("Location: $logUrl");
    exit(0);
}

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
    $availableSubtitles = common::getAvailableSubtitles($in['input']);
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
        <td>  <input type='radio' value='' name='subtitles' id='subtitles' checked> </td>
        <td colspan=3>  Do not download subtitles </td>
      </tr>
      <tr>
        <td>  <input type='radio' value='external' name='subtitles' id='subtitles'> </td>
        <td colspan=3>  Use AWS Amazon Transcribe for subtitles ( may take a long time ) </td>
      </tr>";
    $i = 0;
    foreach ($availableSubtitles as $line) {
        $subType = ($line["type"] === "subtitles") ? " -s " : " -a ";
        $type = $line["type"];
        $lang = $line["language"];
        // $formats = $line["formats"]; // Array !!! to do
        $formats = $line["formats"][0];
        echo "<tr>
        <td>  <input type='radio' value=' $subType -l $lang ' name='subtitles'  id='subtitles'> </td>
        <td>  $lang </td>
        <td>  $formats </td>
        <td>  $type </td>
      </tr>";
        $i++;
    }
    echo "</table>
    <br>
    <input type='submit'  name='save' id='save' value='Go'> </td>
      <input type='hidden'  name='step' id='step' value='20'>
      <input type='hidden'  name='in' id='in' value='$string'>
      </form>
      </body>
      </html>
      ";
    exit(0);
}

// check that
if (5 == $in['step']) {
    if (preg_match("/[^-\w]/", $in['outputFileMask'], $matches)) {
        common::$errors[] = "Please, do not use spaces, special or national chars";
        $in['step'] = 3;
    }
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

if (3 == $in['step']) {
    if (!isset($in["outputFileMask"])) {
        $in["outputFileMask"] = common::getOutputFileMask($in['input']);
    }
    common::$messages[] = "Processing url '" . $in['input'] . "'";
    echo common::showErrors();
    echo common::showMessages();

    echo "<h3>What file name will be used for downloaded files ( videos/audio/subtitles) </h3>
  Please, do not use spaces, special or national chars
    <form action='index.php' method='post' multipart='' enctype='multipart/form-data'>
    <table>
    <tr>
    <td><input type='text' name='outputFileMask' id='outputFileMask' value='" . $in["outputFileMask"] . "' size=50></td>
    </tr>
    ";
    echo "</table>
    <br>
    <input type='submit'  name='save' id='save' value='Go'> </td>
      <input type='hidden'  name='step' id='step' value='5'>
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
      <td><input type='text' name='input' id='input' value='" . $in['input'] . "' size=50></td>
    </tr>
    <tr>
      <td><input type='submit'  name='save' id='save' value='Go'> </td>
    </tr>
    </table>
    <input type='hidden'  name='step' id='step' value='3'>
    </form>
  </body>
</html>
";
    exit(0);
}

function getTemplateYoutubeDl($youtubeUrl)
{
    $code = "<!doctype html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <title>Video and subtitles download</title>
        <style>
        body {
          font-family: verdana, arial, sans-serif;
        }
        </style>
      <meta http-equiv='refresh' content='20'>

    </head>
    <body>
    [<a href='./../index.php'> Home </a>]
    [<a href='./../list.php'> S3 file list </a>]
    <h2>youtube-dl web interface</h2>
    <h3> Video and subtitles download for <a href='$youtubeUrl'>$youtubeUrl</a>  </h3>
    <pre>";
    return ($code);
}