<?php
/*
web interface for youtube-dl
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
    <title>youtube-dl frontend. List S3 uploaded files</title>
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
<h2>youtube-dl web interface. List S3 uploaded files</h2>
";

include_once "common.php";

//$debug = true;
$debug = false;
$basedir = dirname(__FILE__);
$bin_dir = "$basedir/bin";
$tmpDir = "/tmp/youtube-dl";
$logDir = "$basedir/logs/";
$logUrl = "./logs/";
$dataDir = "$basedir/data/";
$awsYoutubeSubtitles = "/home/ubuntu/php/youtube_subtitles.php";
$youtubeToS3 = "/home/ubuntu/php/youtube_s3.php";
$listS3 = "/home/ubuntu/php/list_s3.php";

$today = date("F j, Y, g:i a");
$dt = date("U");

$in = array();
$data = null;
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir);
}




$json_in = common::getListS3();
if ($json_in) {
    $contents = json_decode($json_in, true);
    if (!$contents) {
        common::$errors[] = "Incorrect json string recived";
        echo "<pre>".var_dump($json_in)."</pre>";
        echo common::showErrors();
        echo common::showMessages();
        exit(1);
    }
} 


echo "<table border=1>
        <tr>
          <td>Filename</td>
          <td>Last Modified</td>
          <td>Size</td>
        </tr>
";

$bucket=$contents["Name"] ;
$region=$contents["x-amz-bucket-region"] ;


foreach( $contents["Contents"] as $item ){
  $fileName=$item["Key"];
  $lastModified=$item["LastModified"];
  $size=$item["Size"];
  $fileNameEncoded=urlencode($fileName);
  echo "
  <tr>
    <td><a href='https://$bucket.s3.$region.amazonaws.com/$fileNameEncoded'>$fileName</a></td>
    <td>$lastModified</td>
    <td>$size</td>
  </tr>
";
}

echo "
</table>
</body>
</html>
";