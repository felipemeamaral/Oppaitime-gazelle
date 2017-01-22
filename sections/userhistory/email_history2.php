<?
/************************************************************************
||------------|| User email history page ||---------------------------||

This page lists previous email addresses a user has used on the site. It
gets called if $_GET['action'] == 'email'.

It also requires $_GET['userid'] in order to get the data for the correct
user.

************************************************************************/

$UserID = $_GET['userid'];
if (!is_number($UserID)) {
  error(404);
}

$DB->query("
  SELECT
    ui.JoinDate,
    p.Level AS Class
  FROM users_main AS um
    JOIN users_info AS ui ON um.ID = ui.UserID
    JOIN permissions AS p ON p.ID = um.PermissionID
  WHERE um.ID = $UserID");
list($Joined, $Class) = $DB->next_record();

if (!check_perms('users_view_email', $Class)) {
  error(403);
}

// TODO: Is this even used?
$UsersOnly = $_GET['usersonly'] ?? false;

$DB->query("
  SELECT Username
  FROM users_main
  WHERE ID = $UserID");
list($Username) = $DB->next_record();
View::show_header("Email history for $Username");

// Get current email (and matches)
$DB->query("
  SELECT
    m.Email,
    '".sqltime()."' AS Time,
    m.IP,
    GROUP_CONCAT(h.UserID SEPARATOR '|') AS UserIDs,
    GROUP_CONCAT(h.Time SEPARATOR '|') AS UserSetTimes,
    GROUP_CONCAT(h.IP SEPARATOR '|') AS UserIPs,
    GROUP_CONCAT(m2.Username SEPARATOR '|') AS Usernames,
    GROUP_CONCAT(m2.Enabled SEPARATOR '|') AS UsersEnabled,
    GROUP_CONCAT(i.Donor SEPARATOR '|') AS UsersDonor,
    GROUP_CONCAT(i.Warned SEPARATOR '|') AS UsersWarned
  FROM users_main AS m
    LEFT JOIN users_history_emails AS h ON h.Email = m.Email
        AND h.UserID != m.ID
    LEFT JOIN users_main AS m2 ON m2.ID = h.UserID
    LEFT JOIN users_info AS i ON i.UserID = h.UserID
  WHERE m.ID = '$UserID'"
);
//$CurrentEmail = array_shift($DB->to_array());
$CurrentEmail = ($DB->to_array())[0]; // Only variables should be passed by reference

// Get historic emails (and matches)
$DB->query("
  SELECT
    h2.Email,
    h2.Time,
    h2.IP,
    h3.UserID AS UserIDs,
    h3.Time AS UserSetTimes,
    h3.IP AS UserIPs,
    m3.Username AS Usernames,
    m3.Enabled AS UsersEnabled,
    i2.Donor AS UsersDonor,
    i2.Warned AS UsersWarned
  FROM users_history_emails AS h2
    LEFT JOIN users_history_emails AS h3 ON h3.Email = h2.Email
        AND h3.UserID != h2.UserID
    LEFT JOIN users_main AS m3 ON m3.ID = h3.UserID
    LEFT JOIN users_info AS i2 ON i2.UserID = h3.UserID
  WHERE h2.UserID = '$UserID'
  ORDER BY Time DESC"
);
$History = $DB->to_array();

// Current email
$Current['Email'] = $CurrentEmail['Email'];
$Current['StartTime'] = $History[0]['Time'];
$Current['CurrentIP'] = $CurrentEmail['IP'];
$Current['IP'] = $History[(count($History) - 1)]['IP'];

// Matches for current email
if ($CurrentEmail['Usernames'] != '') {
  $UserIDs = explode('|', $CurrentEmail['UserIDs']);
  $Usernames = explode('|', $CurrentEmail['Usernames']);
  $UsersEnabled = explode('|', $CurrentEmail['UsersEnabled']);
  $UsersDonor = explode('|', $CurrentEmail['UsersDonor']);
  $UsersWarned = explode('|', $CurrentEmail['UsersWarned']);
  $UserSetTimes = explode('|', $CurrentEmail['UserSetTimes']);
  $UserIPs = explode('|', $CurrentEmail['UserIPs']);

  foreach ($UserIDs as $Key => $Val) {
    $CurrentMatches[$Key]['Username'] = '&nbsp;&nbsp;&#187;&nbsp;'.Users::format_username($Val, true, true, true);
    $CurrentMatches[$Key]['IP'] = $UserIPs[$Key];
    $CurrentMatches[$Key]['EndTime'] = $UserSetTimes[$Key];
  }
}

// Email history records
if (count($History) === 1) {
  $Invite['Email'] = $History[0]['Email'];
  $Invite['EndTime'] = $Joined;
  $Invite['AccountAge'] = date(time() + time() - strtotime($Joined)); // Same as EndTime but without ' ago'
  $Invite['IP'] = $History[0]['IP'];
  if (!$Current['StartTime']) {
    $Current['StartTime'] = $Joined;
  }
} else {
  foreach ($History as $Key => $Val) {
    if (isset($History[$Key + 1]) && !$History[$Key + 1]['Time'] && !$Val['Time']) {
      // Invited email
      $Invite['Email'] = $Val['Email'];
      $Invite['EndTime'] = $Joined;
      $Invite['AccountAge'] = date(time() + time() - strtotime($Joined)); // Same as EndTime but without ' ago'
      $Invite['IP'] = $Val['IP'];

    } elseif (isset($History[$Key - 1]) && $History[$Key - 1]['Email'] != $Val['Email'] && $Val['Time']) {
      // Old email
      $i = 1;
      while ($Val['Email'] == $History[$Key + $i]['Email']) {
        $i++;
      }
      $Old[$Key]['StartTime'] = (isset($History[$Key + $i]) && $History[$Key + $i]['Time']) ? $History[$Key + $i]['Time'] : $Joined;
      $Old[$Key]['EndTime'] = $Val['Time'];
      $Old[$Key]['IP'] = $Val['IP'];
      $Old[$Key]['ElapsedTime'] = date(time() + strtotime($Old[$Key]['EndTime']) - strtotime($Old[$Key]['StartTime']));
      $Old[$Key]['Email'] = $Val['Email'];
    }

    if ($Val['Usernames'] != '') {
      // Match with old email
      $OldMatches[$Key]['Email'] = $Val['Email'];
      $OldMatches[$Key]['Username'] = '&nbsp;&nbsp;&#187;&nbsp;'.Users::format_username($Val['UserIDs'], true, true, true);
      $OldMatches[$Key]['EndTime'] = $Val['UserSetTimes'];
      $OldMatches[$Key]['IP'] = $Val['UserIPs'];
    }
  }
}

// Clean up arrays
if ($Old ?? false) {
  $Old = array_reverse(array_reverse($Old));
  $LastOld = count($Old) - 1;
  if ($Old[$LastOld]['StartTime'] != $Invite['EndTime']) {
    // Make sure the timeline is intact (invite email was used as email for the account in the beginning)
    $Old[$LastOld + 1]['Email'] = $Invite['Email'];
    $Old[$LastOld + 1]['StartTime'] = $Invite['EndTime'];
    $Old[$LastOld + 1]['EndTime'] = $Old[$LastOld]['StartTime'];
    $Old[$LastOld + 1]['ElapsedTime'] = date(time() + strtotime($Old[$LastOld + 1]['EndTime'] ) - strtotime($Old[$LastOld + 1]['StartTime']));
    $Old[$LastOld + 1]['IP'] = $Invite['IP'];
  }
}

// Start page with current email
?>
<div class="thin">
  <div class="header">
    <h2>Email history for <a href="user.php?id=<?=$UserID ?>"><?=$Username ?></a></h2>
    <div class="linkbox center">
      <a href="userhistory.php?action=email&amp;userid=<?=$UserID?>" class="brackets">Old email history</a>
    </div>
  </div>
  <br />
  <table width="100%">
    <tr class="colhead">
      <td>Current email</td>
      <td>Start</td>
      <td>End</td>
      <td>Current IP <a href="userhistory.php?action=ips&amp;userid=<?=$UserID ?>" class="brackets">H</a></td>
      <td>Set from IP</td>
    </tr>
<?
$Current['Email'] = apc_exists('DBKEY') ? DBCrypt::decrypt($Current['Email']) : '[Encrypted]';
$Current['CurrentIP'] = apc_exists('DBKEY') ? DBCrypt::decrypt($Current['CurrentIP']) : '[Encrypted]';
$Current['IP'] = apc_exists('DBKEY') ? DBCrypt::decrypt($Current['IP']) : '[Encrypted]';
?>
    <tr class="row">
      <td><?=display_str($Current['Email'])?></td>
      <td><?=time_diff($Current['StartTime'])?></td>
      <td></td>
      <td>
        <?=display_str($Current['CurrentIP'])?>
        (<?=Tools::get_country_code_by_ajax($Current['CurrentIP'])?>)
        <a href="user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($Current['CurrentIP'])?>" class="brackets tooltip" title="Search">S</a>
        <a href="http://whatismyipaddress.com/ip/<?=display_str($Current['CurrentIP'])?>" class="brackets tooltip" title="Search WIMIA.com">WI</a>
        <br />
        <?=Tools::get_host_by_ajax($Current['CurrentIP'])?>
      </td>
      <td>
        <?=display_str($Current['IP'])?>
        (<?=Tools::get_country_code_by_ajax($Current['IP'])?>)
        <a href="user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($Current['IP'])?>" class="brackets tooltip" title="Search">S</a>
        <a href="http://whatismyipaddress.com/ip/<?=display_str($Current['IP'])?>" class="brackets tooltip" title="Search WIMIA.com">WI</a>
        <br />
        <?=Tools::get_host_by_ajax($Current['IP'])?>
      </td>
    </tr>
<?
if ($CurrentMatches ?? false) {
  // Match on the current email
  foreach ($CurrentMatches as $Match) {
    $Match['IP'] = apc_exists('DBKEY') ? DBCrypt::decrypt($Match['IP']) : '[Encrypted]';
?>
    <tr class="row">
      <td><?=$Match['Username']?></td>
      <td></td>
      <td><?=time_diff($Match['EndTime'])?></td>
      <td></td>
      <td>
        <?=display_str($Match['IP'])?>
        (<?=Tools::get_country_code_by_ajax($Match['IP'])?>)
        <a href="user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($Match['IP'])?>" class="brackets tooltip" title="Search">S</a>
        <a href="http://whatismyipaddress.com/ip/<?=display_str($Match['IP'])?>" class="brackets tooltip" title="Search WIMIA.com">WI</a>
        <br />
        <?=Tools::get_host_by_ajax($Match['IP'])?>
      </td>
    </tr>
<?
  }
}
// Old emails
if ($Old ?? false) {
?>
    <tr class="colhead">
      <td>Old emails</td>
      <td>Start</td>
      <td>End</td>
      <td>Elapsed</td>
      <td>Set from IP</td>
    </tr>
<?
  $j = 0;
  // Old email
  foreach ($Old as $Record) {
    ++$j;

    // Matches on old email
    ob_start();
    $i = 0;
    if ($OldMatches ?? false) {
      foreach ($OldMatches as $Match) {
        if ($Match['Email'] == $Record['Email']) {
          ++$i;
          // Email matches
  ?>
      <tr class="row hidden" id="matches_<?=$j?>">
        <td><?=$Match['Username']?></td>
        <td></td>
        <td><?=time_diff($Match['EndTime'])?></td>
        <td></td>
        <td>
          <?=display_str($Match['IP'])?>
          (<?=Tools::get_country_code_by_ajax($Match['IP'])?>)
          <a href="user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($Match['IP'])?>" class="brackets tooltip" title="Search">S</a>
          <a href="http://whatismyipaddress.com/ip/<?=display_str($Match['IP'])?>" class="brackets tooltip" title="Search WIMIA.com">WI</a>
          <br />
          <?=Tools::get_host_by_ajax($Match['IP'])?>
        </td>
      </tr>
  <?
        }
      }
    }

    // Save matches to variable
    $MatchCount = $i;
    $Matches = ob_get_contents();
    ob_end_clean();

    $Record['Email'] = apc_exists('DBKEY') ? DBCrypt::decrypt($Record['Email']) : '[Encrypted]';
    $Record['IP'] = apc_exists('DBKEY') ? DBCrypt::decrypt($Record['IP']) : '[Encrypted]';
?>
    <tr class="row">
      <td><?=display_str($Record['Email'])?><?=(($MatchCount > 0) ? ' <a toggle-target="#matches_'.$j.'">('.$MatchCount.')</a>' : '')?></td>
      <td><?=time_diff($Record['StartTime'])?></td>
      <td><?=time_diff($Record['EndTime'])?></td>
      <td><?=time_diff($Record['ElapsedTime'])?></td>
      <td>
        <?=display_str($Record['IP'])?>
        (<?=Tools::get_country_code_by_ajax($Record['IP'])?>)
        <a href="user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($Record['IP'])?>" class="brackets tooltip" title="Search">S</a>
        <a href="http://whatismyipaddress.com/ip/<?=display_str($Record['IP'])?>" class="brackets tooltip" title="Search WIMIA.com">WI</a>
        <br />
        <?=Tools::get_host_by_ajax($Record['IP'])?>
      </td>
    </tr>
<?
    if ($MatchCount > 0) {
      if (isset($Matches)) {
        echo $Matches;
        unset($Matches);
        unset($MatchCount);
      }
    }
  }
}
// Invite email (always there)
?>
    <tr class="colhead">
      <td>Invite email</td>
      <td>Start</td>
      <td>End</td>
      <td>Age of account</td>
      <td>Registration IP address</td>
    </tr>
<?
// Matches on invite email
$i = 0;
ob_start();
if ($OldMatches ?? false) {
  foreach ($OldMatches as $Match) {
    if ($Match['Email'] == $Invite['Email']) {
      ++$i;
      // Match email is the same as the invite email

      $Match['IP'] = apc_exists('DBKEY') ? DBCrypt::decrypt($Match['IP']) : '[Encrypted]';
?>
    <tr class="row hidden" id="matches_invite">
      <td><?=$Match['Username']?></td>
      <td></td>
      <td><?=time_diff($Match['EndTime'])?></td>
      <td></td>
      <td>
        <?=display_str($Match['IP'])?>
        (<?=Tools::get_country_code_by_ajax($Match['IP'])?>)
        <a href="user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($Match['IP'])?>" class="brackets tooltip" title="Search">S</a>
        <a href="http://whatismyipaddress.com/ip/<?=display_str($Match['IP'])?>" class="brackets tooltip" title="Search WIMIA.com">WI</a>
        <br />
        <?=Tools::get_host_by_ajax($Match['IP'])?>
      </td>
    </tr>
<?
    }
  }
}
$MatchCount = $i;
$Matches = ob_get_contents();
ob_end_clean();

$Invite['Email'] = apc_exists('DBKEY') ? DBCrypt::decrypt($Invite['Email']) : '[Encrypted]';
$Invite['IP'] = apc_exists('DBKEY') ? DBCrypt::decrypt($Invite['IP']) : '[Encrypted]';
?>
    <tr class="row">
      <td><?=display_str($Invite['Email'])?><?=(($MatchCount > 0) ? ' <a toggle-target="#matches_invite">('.$MatchCount.')</a>' : '')?></td>
      <td>Never</td>
      <td><?=time_diff($Invite['EndTime'])?></td>
      <td><?=time_diff($Invite['AccountAge'])?></td>
      <td>
        <?=display_str($Invite['IP'])?>
        (<?=Tools::get_country_code_by_ajax($Invite['IP'])?>)
        <a href="user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($Invite['IP'])?>" class="brackets tooltip" title="Search">S</a>
        <a href="http://whatismyipaddress.com/ip/<?=display_str($Invite['IP'])?>" class="brackets tooltip" title="Search WIMIA.com">WI</a>
        <br />
        <?=Tools::get_host_by_ajax($Invite['IP'])?>
      </td>
    </tr>
<?

if ($Matches) {
  echo $Matches;
}

?>
  </table>
</div>
<? View::show_footer(); ?>
