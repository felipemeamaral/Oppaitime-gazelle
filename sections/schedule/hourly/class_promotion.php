<?
//------------- Promote users -------------------------------------------//

// Disabled in favor of store-based promotions
/*
sleep(5);
$GiB = 1024*1024*1024;
$Criteria = array();
$Criteria[] = array(
  'From' => USER,
  'To' => MEMBER,
  'MinUpload' => 10 * $GiB,
  'MinRatio' => 0.7,
  'MinUploads' => 0,
  'MaxTime' => time_minus(3600 * 24 * 7)
);
$Criteria[] = array(
  'From' => MEMBER,
  'To' => POWER,
  'MinUpload' => 100 * $GiB,
  'MinRatio' => 1.1,
  'MinUploads' => 5,
  'MaxTime' => time_minus(3600 * 24 * 7 * 2)
);
$Criteria[] = array(
  'From' => POWER,
  'To' => ELITE,
  'MinUpload' => 500 * $GiB,
  'MinRatio' => 1.2,
  'MinUploads' => 50,
  'MaxTime' => time_minus(3600 * 24 * 7 * 4)
);
$Criteria[] = array(
  'From' => ELITE,
  'To' => TORRENT_MASTER,
  'MinUpload' => 1024 * $GiB,
  'MinRatio' => 1.3,
  'MinUploads' => 200,
  'MaxTime' => time_minus(3600 * 24 * 7 * 8)
);
$Criteria[] = array(
  'From' => TORRENT_MASTER,
  'To' => POWER_TM,
  'MinUpload' => 1.5 * 1024 * $GiB,
  'MinRatio' => 1.3,
  'MinUploads' => 200,
  'MaxTime' => time_minus(3600 * 24 * 7 * 8),
  'Extra' => '
      (
        SELECT COUNT(DISTINCT GroupID)
        FROM torrents
        WHERE UserID = users_main.ID
      ) >= 300');
/*$Criteria[] = array(
  'From' => POWER_TM,
  'To' => ELITE_TM,
  'MinUpload' => 2 * 1024 * 1024 * 1024 * 1024,
  'MinRatio' => 1.3,
  'MinUploads' => 400,
  'MaxTime' => time_minus(3600 * 24 * 7 * 8),
  'Extra' => '
      (
        SELECT COUNT(DISTINCT GroupID)
        FROM torrents
        WHERE UserID = users_main.ID
      ) >= 400');
  'Extra' => "
      (
        SELECT COUNT(ID)
        FROM torrents
        WHERE ((LogScore = 100 AND Format = 'FLAC')
          OR (Media = 'Vinyl' AND Format = 'FLAC')
          OR (Media = 'WEB' AND Format = 'FLAC')
          OR (Media = 'DVD' AND Format = 'FLAC')
          OR (Media = 'Soundboard' AND Format = 'FLAC')
          OR (Media = 'Cassette' AND Format = 'FLAC')
          OR (Media = 'SACD' AND Format = 'FLAC')
          OR (Media = 'Blu-ray' AND Format = 'FLAC')
          OR (Media = 'DAT' AND Format = 'FLAC')
          )
          AND UserID = users_main.ID
      ) >= 500");

 foreach ($Criteria as $L) { // $L = Level
  $Query = "
      SELECT ID
      FROM users_main
        JOIN users_info ON users_main.ID = users_info.UserID
      WHERE PermissionID = ".$L['From']."
        AND Warned = NULL
        AND Uploaded >= '$L[MinUpload]'
        AND (Uploaded / Downloaded >= '$L[MinRatio]' OR (Uploaded / Downloaded IS NULL))
        AND JoinDate < '$L[MaxTime]'
        AND (
          SELECT COUNT(ID)
          FROM torrents
          WHERE UserID = users_main.ID
          ) >= '$L[MinUploads]'
        AND Enabled = '1'";
  if (!empty($L['Extra'])) {
    $Query .= ' AND '.$L['Extra'];
  }

  $DB->query($Query);

  $UserIDs = $DB->collect('ID');

  if (count($UserIDs) > 0) {
    $DB->query("
      UPDATE users_main
      SET PermissionID = ".$L['To']."
      WHERE ID IN(".implode(',', $UserIDs).')');
    foreach ($UserIDs as $UserID) {
      /*$Cache->begin_transaction("user_info_$UserID");
      $Cache->update_row(false, array('PermissionID' => $L['To']));
      $Cache->commit_transaction(0);
      $Cache->delete_value("user_info_$UserID");
      $Cache->delete_value("user_info_heavy_$UserID");
      $Cache->delete_value("user_stats_$UserID");
      $Cache->delete_value("enabled_$UserID");
      $DB->query("
        UPDATE users_info
        SET AdminComment = CONCAT('".sqltime()." - Class changed to ".Users::make_class_string($L['To'])." by System\n\n', AdminComment)
        WHERE UserID = $UserID");
      Misc::send_pm($UserID, 0, 'You have been promoted to '.Users::make_class_string($L['To']), 'Congratulations on your promotion to '.Users::make_class_string($L['To'])."!\n\nTo read more about ".SITE_NAME."'s user classes, read [url=".site_url()."wiki.php?action=article&amp;name=userclasses]this wiki article[/url].");
    }
  }

  // Demote users with less than the required uploads

  $Query = "
    SELECT ID
    FROM users_main
      JOIN users_info ON users_main.ID = users_info.UserID
    WHERE PermissionID = '$L[To]'
      AND ( Uploaded < '$L[MinUpload]'
        OR (
          SELECT COUNT(ID)
          FROM torrents
          WHERE UserID = users_main.ID
          ) < '$L[MinUploads]'";
    if (!empty($L['Extra'])) {
      $Query .= ' OR NOT '.$L['Extra'];
    }
    $Query .= "
        )
      AND Enabled = '1'";

  $DB->query($Query);
  $UserIDs = $DB->collect('ID');

  if (count($UserIDs) > 0) {
    $DB->query("
      UPDATE users_main
      SET PermissionID = ".$L['From']."
      WHERE ID IN(".implode(',', $UserIDs).')');
    foreach ($UserIDs as $UserID) {
      /*$Cache->begin_transaction("user_info_$UserID");
      $Cache->update_row(false, array('PermissionID' => $L['From']));
      $Cache->commit_transaction(0);
      $Cache->delete_value("user_info_$UserID");
      $Cache->delete_value("user_info_heavy_$UserID");
      $Cache->delete_value("user_stats_$UserID");
      $Cache->delete_value("enabled_$UserID");
      $DB->query("
        UPDATE users_info
        SET AdminComment = CONCAT('".sqltime()." - Class changed to ".Users::make_class_string($L['From'])." by System\n\n', AdminComment)
        WHERE UserID = $UserID");
      Misc::send_pm($UserID, 0, 'You have been demoted to '.Users::make_class_string($L['From']), "You now only qualify for the \"".Users::make_class_string($L['From'])."\" user class.\n\nTo read more about ".SITE_NAME."'s user classes, read [url=".site_url()."wiki.php?action=article&amp;name=userclasses]this wiki article[/url].");
    }
  }
 }
*/
?>
