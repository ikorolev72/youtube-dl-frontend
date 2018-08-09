<?php
/*
web interface for youtube-dl
Author Korolev Igor
https://github.com/ikorolev72
2018.08.02
version 1.0
 */

class common
{
    public static $youtube_dl = "youtube-dl --no-color  ";
    public static $ffmpeg = "ffmpeg";
    public static $ffprobe = "ffprobe";
    public static $ffmpegLogLevel = 'info';
    public static $errors = array();
    public static $messages = array();

    public static $utilDir = "/home/osboxes/php/";
    public static $listS3 = "list_s3.php";
    public static $awsYoutubeSubtitles = "youtube_subtitles.php";
    public static $youtubeToS3 = "youtube_s3.php";

    public static function showErrors()
    {
        $msg = "<font color='red'>" . join("<br>", self::$errors) . "</font><hr>";
        return $msg;
    }

    public static function showMessages()
    {
        $msg = "<font color='green'>" . join("<br>", self::$messages) . "</font><hr>";
        return $msg;
    }

    public static function reArrayFiles($file)
    {
        $file_ary = array();
        $file_count = count($file['name']);
        $file_key = array_keys($file);

        for ($i = 0; $i < $file_count; $i++) {
            foreach ($file_key as $val) {
                $file_ary[$i][$val] = $file[$val][$i];
            }
        }
        return $file_ary;
    }

    public static function get_param($val)
    {
        global $_POST;
        global $_GET;
        $ret = isset($_POST[$val]) ? $_POST[$val] :
        (isset($_GET[$val]) ? $_GET[$val] : null);
        return $ret;
    }

    public static function copy_files($src, $dst, $allowed)
    {
        $dir = opendir($src);
        #@mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if (in_array($ext, $allowed)) {
                link("$src/$file", "$dst/$file");
                # copy($src . '/' . $file,$dst . '/' . $file);
            }
        }
        closedir($dir);
        return true;
    }

    public static function save_settings($item, $keys)
    {
        $saved_values = array();
        foreach ($keys as $k) {
            if (isset($item[1][$k])) {
                $saved_values[$k] = $item[1][$k];
            }

        }
        return ($saved_values);
    }

/**
 * getStreamInfo
 * function get info about video or audio stream in the file
 *
 * @param    string $fileName
 * @param    string $streamType    must be  'audio' or 'video'
 * @param    array &$data          return data
 * @return    integer 1 for success, 0 for any error
 */
    public static function getStreamInfo($fileName, $streamType, &$data)
    {
        # parameter - 'audio' or 'video'
        $ffprobe = self::$ffprobe;

        if (!$probeJson = json_decode(`"$ffprobe" $fileName -v quiet -hide_banner -show_streams -of json`, true)) {
            self::writeToLog("Cannot get info about file $fileName");
            return 0;
        }
        if (empty($probeJson["streams"])) {
            self::writeToLog("Cannot get info about streams in file $fileName");
            return 0;
        }
        foreach ($probeJson["streams"] as $stream) {
            if ($stream["codec_type"] == $streamType) {
                $data = $stream;
                break;
            }
        }

        if (empty($data)) {
            self::writeToLog("File $fileName :  stream not found");
            return 0;
        }
        if ('video' == $streamType) {
            if (empty($data["height"]) || !intval($data["height"]) || empty($data["width"]) || !intval($data["width"])) {
                self::writeToLog("File $fileName : invalid or corrupt dimensions");
                return 0;
            }
        }

        return 1;
    }

/**
 * writeToLog
 * function print messages to console
 *
 * @param    string $message
 * @return    string
 */
    public static function writeToLog($message)
    {
        echo "$message\n";
        #fwrite(STDERR, "$message\n");
    }

/**
 * doExec
 * @param    string    $Command
 * @return integer 0-error, 1-success
 */

    public static function doExec($Command)
    {
        $outputArray = array();
        exec($Command, $outputArray, $execResult);
        if ($execResult) {
            self::writeToLog(join("\n", $outputArray));
            return 0;
        }
        return 1;
    }

    public static function generateOutputFilename($filename)
    {
        $path_parts = pathinfo($filename);
        $dir = $path_parts['dirname'];
        $file = $path_parts['filename'];
        $ext = $path_parts['extension'];
        $date = date("U");
        return ("$dir/${file}_${date}.${ext}");
    }

/**
 * splitVideoFade
 * cut video part
 *
 * @param string   $input
 * @param string   $output
 * @param string   $start
 * @param string   $end
 * @return string  Command ffmpeg
 */

    public static function splitVideoFade(
        $input,
        $output,
        $start,
        $end,
        $fadeIn,
        $fadeOut,
        $fadeDuration
    ) {
        $ffmpeg = self::$ffmpeg;
        $ffmpegLogLevel = self::$ffmpegLogLevel;
        //$duration = $end - $start;
        //$fadeOutStart = self::time2float($end) - self::time2float($start) - $fadeDuration;
        $begin = self::time2float($start);
        $finish = self::time2float($end);
        $fadeOutStart = $finish - $fadeDuration;
        $fadeInFilter = "null";
        $fadeOutFilter = "null";
        if ($fadeIn) {
            $fadeInFilter = "fade=in:st=$begin:d=$fadeDuration";
        }
        if ($fadeOut) {
            $fadeOutFilter = "fade=out:st=$fadeOutStart:d=$fadeDuration";
        }
        $cmd = join(" ", [
            "$ffmpeg -loglevel $ffmpegLogLevel  -y  ",
            " -i $input -ss $start -to $end ",
            " -filter_complex \" ",
            " setpts=PTS-STARTPTS, $fadeInFilter, $fadeOutFilter [v]; asetpts=PTS-STARTPTS [a] \" ",
            " -map \"[v]\" -map \"[a]\" -c:v h264 -crf 18 -preset veryfast -f mpegts $output",
        ]
        );
        return $cmd;
    }

/**
 * splitVideo
 * cut video part
 *
 * @param string   $input
 * @param string   $output
 * @param string   $start
 * @param string   $end
 * @return string  Command ffmpeg
 */

    public static function splitVideo(
        $input,
        $output,
        $start,
        $end
    ) {
        $ffmpeg = self::$ffmpeg;
        $ffmpegLogLevel = self::$ffmpegLogLevel;
        //$duration = $end - $start;

        $cmd = join(" ", [
            "$ffmpeg -loglevel $ffmpegLogLevel  -y  ",
            " -i $input -ss $start -to $end ",
            " -filter_complex \" ",
            " setpts=PTS-STARTPTS [v]; asetpts=PTS-STARTPTS [a] \" ",
            " -map \"[v]\" -map \"[a]\" -c:v h264 -crf 18 -preset veryfast -f mpegts $output",
        ]
        );
        return $cmd;
    }

/**
 * stitchVideo
 * stitch video part
 *
 * @param array    $input
 * @param string   $output
 * @return string  Command ffmpeg
 */

    public static function stitchVideo(
        $input,
        $output
    ) {
        $ffmpeg = self::$ffmpeg;
        $ffmpegLogLevel = self::$ffmpegLogLevel;

        //$duration = $end - $start;

        $cmd = join(" ", [
            "$ffmpeg -loglevel $ffmpegLogLevel  -y  ",
            " -i \"concat:" . join('|', $input) . "\"",
            " -c:v copy -c:a copy -f mp4 $output",
        ]
        );
        return $cmd;
    }

/**
 * time2float
 * this function translate time in format 00:00:00.00 to seconds
 *
 * @param    string $t
 * @return    float
 */

    public static function time2float($t)
    {
        $matches = preg_split("/:/", $t, 3);
        if (array_key_exists(2, $matches)) {
            list($h, $m, $s) = $matches;
            return ($s + 60 * $m + 3600 * $h);
        }
        $h = 0;
        list($m, $s) = $matches;
        return ($s + 60 * $m);
    }

/**
 * float2time
 * this function translate time from seconds to format 00:00:00.00
 *
 * @param    float $i
 * @return    string
 */
    public function float2time($i)
    {
        $h = intval($i / 3600);
        $m = intval(($i - 3600 * $h) / 60);
        $s = $i - 60 * floatval($m) - 3600 * floatval($h);
        return sprintf("%02d:%02d:%05.2f", $h, $m, $s);
    }

    public static function getAvailableFormats($youtubeUrl)
    {

        $cmd = self::$youtube_dl . " -F $youtubeUrl 2>&1";
        $out = array();
        exec($cmd, $output, $return);
        // Something wrong
        if ($return != 0) {
            self::$errors = array_merge(self::$errors, $output);
            self::writeToLog("Something wrong: " . join(PHP_EOL, $output));
            return (false);
        }
        foreach ($output as $line) {
            if (preg_match("/^(\d+)\s+(\w+)\s+(\w+)\s+(.+)$/", $line, $matches)) {
                $out[] = $matches;
            }
        }
        return ($out);
    }

    public static function getAvailableSubtitles($youtubeUrl)
    {

        $cmd = self::$youtube_dl . " --list-subs  $youtubeUrl 2>&1";
        $out = array();
        exec($cmd, $output, $return);
        // Something wrong
        if ($return != 0) {
            self::$errors = array_merge(self::$errors, $output);
            self::writeToLog("Something wrong: " . join(PHP_EOL, $output));
            return (false);
        }
        // check subtitles
        $foundSubtitles = false;
        foreach ($output as $line) {
            if (preg_match("/^Available subtitles for /", $line, $matches)) {
                $foundSubtitles = true;
                continue;
            }
            if ($foundSubtitles) {
                if (preg_match("/^Language\s+formats/i", $line, $matches)) {
                    continue;
                }
                if (preg_match("/^(\S+)\s+(.+)\s*$/", $line, $matches)) {
                    $lang = $matches[1];
                    $formats = preg_split("/,\s+/", $matches[2]);
                    $out[] = array("type" => "subtitles", "language" => $lang, "formats" => $formats);
                } else {
                    $foundSubtitles = false;
                    continue;
                }
            }
        }

        // check auto captions subtitles
        $foundSubtitles = false;
        foreach ($output as $line) {
            if (preg_match("/^Available automatic captions for /", $line, $matches)) {
                $foundSubtitles = true;
                continue;
            }
            if ($foundSubtitles) {
                if (preg_match("/^Language\s+formats/i", $line, $matches)) {
                    continue;
                }
                if (preg_match("/^(\S+)\s+(.+)\s*$/", $line, $matches)) {
                    $lang = $matches[1];
                    $formats = preg_split("/,\s+/", $matches[2]);
                    $out[] = array("type" => "automatic_captions", "language" => $lang, "formats" => $formats);
                } else {
                    $foundSubtitles = false;
                    continue;
                }
            }
        }
        return ($out);
    }

    public static function downloadVideo($youtubeUrl, $options, $logFile)
    {

        $cmd = self::$youtube_dl . " $options $youtubeUrl >> $logFile 2>&1";
        $out = array();
        exec($cmd, $output, $return);
        // Something wrong
        if ($return != 0) {
            self::$errors = array_merge(self::$errors, $output);
            self::writeToLog("Something wrong: " . join(PHP_EOL, $output));
            return (false);
        }

        return ($out);
    }

    public static function getOutputFileMask($youtubeUrl)
    {

        $cmd = self::$youtube_dl . " --restrict-filenames --get-filename -o '%(title)s-%(id)s'  $youtubeUrl 2>&1";
        //$out = array();
        exec($cmd, $output, $return);
        // Something wrong
        if ($return != 0) {
            self::$errors = array_merge(self::$errors, $output);
            self::writeToLog("Something wrong: " . join(PHP_EOL, $output));
            return (false);
        }
        $out = join("", $output);
        return ($out);
    }

    public static function getYoutubeIdFromUrl($url)
    {
        // https://www.youtube.com/watch?v=nfGQyKrRpyM
        if (preg_match("/v=([-\w]+)/", $url, $matches)) {
            return ($matches[1]);
        }
        if (preg_match("/^([-\w]+)$/", $url, $matches)) {
            return ($matches[1]);
        }
        return (false);
    }

    public static function getListS3()
    {

        $cmd = "php " . self::$utilDir . self::$listS3 . " 2>&1";
        //$cmd = "php " . self::$listS3 . " 2>/dev/null";
        //$out = array();
        exec($cmd, $output, $return);
        // Something wrong
        if ($return != 0) {
            self::$errors = array_merge(self::$errors, $output);
            self::writeToLog("Something wrong: " . join(PHP_EOL, $output));
            return (false);
        }
        $out = join("", $output);
        return ($out);
    }

}
