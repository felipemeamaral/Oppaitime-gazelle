<?
global $LoggedUser;
define('FOOTER_FILE',SERVER_ROOT.'/design/publicfooter.php');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
  <title><?=display_str($PageTitle)?></title>
  <meta http-equiv="X-UA-Compatible" content="chrome=1; IE=edge" />
  <link rel="shortcut icon" href="favicon.ico?v=<?=md5_file('favicon.ico');?>" />
  <link href="<?=STATIC_SERVER ?>styles/public/style.css?v=<?=filemtime(SERVER_ROOT.'/static/styles/public/style.css')?>" rel="stylesheet" type="text/css" />
<?
  $Scripts = ['jquery', 'script_start', 'ajax.class', 'cookie.class', 'storage.class', 'global', 'public'];
  foreach($Scripts as $Script) {
    if (($ScriptStats = G::$Cache->get_value("script_stats_$Script")) === false || $ScriptStats['mtime'] != filemtime(SERVER_ROOT.STATIC_SERVER."functions/$Script.js")) {
      $ScriptStats['mtime'] = filemtime(SERVER_ROOT.STATIC_SERVER."functions/$Script.js");
      $ScriptStats['hash'] = base64_encode(hash_file(INTEGRITY_ALGO, SERVER_ROOT.STATIC_SERVER."functions/$Script.js", true));
      $ScriptStats['algo'] = INTEGRITY_ALGO;
      G::$Cache->cache_value("script_stats_$Script", $ScriptStats);
    }
?>
    <script src="<?=STATIC_SERVER."functions/$Script.js?v=$ScriptStats[mtime]"?>" type="text/javascript" integrity="<?="$ScriptStats[algo]-$ScriptStats[hash]"?>"></script>
<?
  }
  $img = array_diff(scandir(SERVER_ROOT.'/misc/bg', 1), array('.', '..')); ?>
  <meta id="bg_data" bg="<?=$img[rand(0,count($img)-1)]?>">
</head>
<body>
<div id="head">
</div>
<div id="content">
  <table class="layout" id="maincontent">
    <tr>
      <td align="center" valign="middle">
        <div id="logo">
          <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="login.php">Log in</a></li>
<? if (OPEN_REGISTRATION) { ?>
            <li><a href="register.php">Register</a></li>
<? } ?>
          </ul>
        </div>
<?
