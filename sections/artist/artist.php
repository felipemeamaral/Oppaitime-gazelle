<?
//~~~~~~~~~~~ Main artist page ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//

//For sorting tags
function compare($X, $Y) {
  return($Y['count'] - $X['count']);
}

// Similar Artist Map
include(SERVER_ROOT.'/classes/artists_similar.class.php');

$UserVotes = Votes::get_user_votes($LoggedUser['ID']);

$ArtistID = $_GET['id'];
if (!is_number($ArtistID)) {
  error(0);
}


if (!empty($_GET['revisionid'])) { // if they're viewing an old revision
  $RevisionID = $_GET['revisionid'];
  if (!is_number($RevisionID)) {
    error(0);
  }
  $Data = $Cache->get_value("artist_{$ArtistID}_revision_$RevisionID", true);
} else { // viewing the live version
  $Data = $Cache->get_value("artist_$ArtistID", true);
  $RevisionID = false;
}

if ($Data) {
  #list($K, list($Name, $Image, $Body, $NumSimilar, $SimilarArray, , , $VanityHouseArtist)) = each($Data);
  list($K, list($Name, $Image, $Body, $NumSimilar, $SimilarArray)) = each($Data);
} else {
  if ($RevisionID) {
    $sql = "
      SELECT
        a.Name,
        wiki.Image,
        wiki.body
      FROM wiki_artists AS wiki
        LEFT JOIN artists_group AS a ON wiki.RevisionID = a.RevisionID
      WHERE wiki.RevisionID = '$RevisionID' ";
  } else {
    $sql = "
      SELECT
        a.Name,
        wiki.Image,
        wiki.body
      FROM artists_group AS a
        LEFT JOIN wiki_artists AS wiki ON wiki.RevisionID = a.RevisionID
      WHERE a.ArtistID = '$ArtistID' ";
  }
  $sql .= "
      GROUP BY a.ArtistID";
  $DB->query($sql);

  if (!$DB->has_results()) {
    error(404);
  }

  list($Name, $Image, $Body) = $DB->next_record(MYSQLI_NUM, array(0));
}


//----------------- Build list and get stats

ob_start();

// Requests
$Requests = array();
if (empty($LoggedUser['DisableRequests'])) {
  $Requests = $Cache->get_value("artists_requests_$ArtistID");
  if (!is_array($Requests)) {
    $DB->query("
      SELECT
        r.ID,
        r.CategoryID,
        r.Title,
        r.TitleRJ,
        r.TitleJP,
        r.CatalogueNumber,
        r.DLSiteID,
        r.TimeAdded,
        COUNT(rv.UserID) AS Votes,
        SUM(rv.Bounty) AS Bounty
      FROM requests AS r
        LEFT JOIN requests_votes AS rv ON rv.RequestID = r.ID
        LEFT JOIN requests_artists AS ra ON r.ID = ra.RequestID
      WHERE ra.ArtistID = $ArtistID
        AND r.TorrentID = 0
      GROUP BY r.ID
      ORDER BY Votes DESC");

    if ($DB->has_results()) {
      $Requests = $DB->to_array('ID', MYSQLI_ASSOC, false);
    } else {
      $Requests = array();
    }
    $Cache->cache_value("artists_requests_$ArtistID", $Requests);
  }
}
$NumRequests = count($Requests);


if (($GroupIDs = $Cache->get_value("artist_groups_$ArtistID")) === false) {
  $DB->query("
    SELECT
      DISTINCTROW ta.GroupID
    FROM torrents_artists AS ta
    WHERE ta.ArtistID = '$ArtistID'");
  $GroupIDs = $DB->collect('GroupID');
  $Cache->cache_value("artist_groups_$ArtistID", $GroupIDs, 0);
}

if (count($GroupIDs) > 0) {
  $TorrentList = Torrents::get_groups($GroupIDs, true, true);
} else {
  $TorrentList = array();
}
$NumGroups = count($TorrentList);

if (!empty($TorrentList)) {
?>
<div id="discog_table" class="box">
<?
}

// Deal with torrents without release types, which can end up here
// if they're uploaded with a non-grouping category ID
$UnknownRT = array_search('Unknown', $ReleaseTypes);
if ($UnknownRT === false) {
  $UnknownRT = 1025;
  $ReleaseTypes[$UnknownRT] = 'Unknown';
}

//Custom sorting for releases
if (!empty($LoggedUser['SortHide'])) {
  $SortOrder = array_flip(array_keys($LoggedUser['SortHide']));
} else {
  $SortOrder = $ReleaseTypes;
}
// If the $SortOrder array doesn't have all release types, put the missing ones at the end
$MissingTypes = array_diff_key($ReleaseTypes, $SortOrder);
if (!empty($MissingTypes)) {
  $MaxOrder = max($SortOrder);
  foreach (array_keys($MissingTypes) as $Missing) {
    $SortOrder[$Missing] = ++$MaxOrder;
  }
}
// Sort the anchors at the top of the page the same way as release types
reset($TorrentList);

$NumTorrents = 0;
$NumSeeders = 0;
$NumLeechers = 0;
$NumSnatches = 0;

foreach ($TorrentList as $GroupID => $Group) {
  // $Tags array is for the sidebar on the right.
  $TorrentTags = new Tags($Group['TagList'], true);

  foreach ($Group['Torrents'] as $TorrentID => $Torrent) {
    $NumTorrents++;

    $Torrent['Seeders'] = (int)$Torrent['Seeders'];
    $Torrent['Leechers'] = (int)$Torrent['Leechers'];
    $Torrent['Snatched'] = (int)$Torrent['Snatched'];

    $NumSeeders += $Torrent['Seeders'];
    $NumLeechers += $Torrent['Leechers'];
    $NumSnatches += $Torrent['Snatched'];
  }
}



$OpenTable = false;
$ShowGroups = !isset($LoggedUser['TorrentGrouping']) || $LoggedUser['TorrentGrouping'] == 0;
$HideTorrents = ($ShowGroups ? '' : ' hidden');
$OldGroupID = 0;
?>
<table class="torrent_table grouped release_table">
  <tr class="colhead_dark">
    <td class="small"><!-- expand/collapse --></td>
    <td width="70%"><a href="#">&uarr;</a>&nbsp;<strong>Name</strong></td>
    <td>Size</td>
    <td class="sign snatches"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
    <td class="sign seeders">
    <a><svg width="11" height="15" fill="white" class="tooltip" alt="Seeders" title="Seeders"><polygon points="0,7 5.5,0 11,7 8,7 8,15 3,15 3,7"></polygon></svg></a>
    </td>
    <td class="sign leechers">
      <a><svg width="11" height="15" fill="white" class="tooltip" alt="Leechers" title="Leechers"><polygon points="0,8 5.5,15 11,8 8,8 8,0 3,0 3,8"></polygon></svg></a>
    </td>
  </tr>
<?

foreach ($TorrentList as $Group) {
  extract(Torrents::array_group($TorrentList[$Group['ID']]), EXTR_OVERWRITE);

  if ($GroupID == $OldGroupID) {
    continue;
  } else {
    $OldGroupID = $GroupID;
  }

  if (count($Torrents) > 1) {
    $TorrentTags = new Tags($TagList, false);

    $DisplayName = Artists::display_artists(Artists::get_artist($GroupID), true, true);

    $DisplayName .= "<a href=\"torrents.php?id=$GroupID\" class=\"tooltip\" title=\"View torrent group\" ";
    if (!isset($LoggedUser['CoverArt']) || $LoggedUser['CoverArt']) {
      $DisplayName .= 'onmouseover="getCover(event)" cover="'.ImageTools::process($WikiImage, true).'" onmouseleave="ungetCover()" ';
    }

    $GroupName = empty($GroupName) ? (empty($GroupNameRJ) ? $GroupNameJP : $GroupNameRJ) : $GroupName;

    $DisplayName .= "dir=\"ltr\">$GroupName</a>";

    if ($GroupYear) {
      $DisplayName .= " [$GroupYear]";
    }
    if ($GroupStudio) {
      $DisplayName .= " [$GroupStudio]";
    }
    if ($GroupCatalogueNumber) {
      $DisplayName .= " [$GroupCatalogueNumber]";
    }
    if ($GroupPages) {
      $DisplayName .= " [{$GroupPages}p]";
    }
    if ($GroupDLSiteID) {
      $DisplayName .= " [$GroupDLSiteID]";
    }

    if (check_perms('users_mod') || check_perms('torrents_fix_ghosts')) {
      $DisplayName .= ' <a href="torrents.php?action=fix_group&amp;groupid='.$GroupID.'&amp;artistid='.$ArtistID.'&amp;auth='.$LoggedUser['AuthKey'].'" class="brackets tooltip" title="Fix ghost DB entry">Fix</a>';
    }

    $SnatchedGroupClass = ($GroupFlags['IsSnatched'] ? ' snatched_group' : '');
?>
        <tr class="group<?=$SnatchedGroupClass?>">
<?
    $ShowGroups = !(!empty($LoggedUser['TorrentGrouping']) && $LoggedUser['TorrentGrouping'] == 1);
?>
        <td class="center">
          <div id="showimg_<?=$GroupID?>" class="<?=($ShowGroups ? 'hide' : 'show')?>_torrents">
            <a class="tooltip show_torrents_link" onclick="toggle_group(<?=$GroupID?>, this, event);" title="Toggle this group (Hold &quot;Shift&quot; to toggle all groups)"></a>
          </div>
        </td>
        <td colspan="5" class="big_info">
          <div class="group_info clear">
            <strong><?=$DisplayName?></strong>
<?  if (Bookmarks::has_bookmarked('torrent', $GroupID)) { ?>
            <span class="remove_bookmark float_right">
              <a style="float: right;" href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Unbookmark('torrent', <?=$GroupID?>, 'Bookmark'); return false;">Remove bookmark</a>
            </span>
<?  } else { ?>
            <span class="add_bookmark float_right">
              <a style="float: right;" href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Bookmark('torrent', <?=$GroupID?>, 'Remove bookmark'); return false;">Bookmark</a>
            </span>
<?  }
    $VoteType = isset($UserVotes[$GroupID]['Type']) ? $UserVotes[$GroupID]['Type'] : '';
    Votes::vote_link($GroupID, $VoteType);
?>
            <div class="tags"><?=$TorrentTags->format('torrents.php?taglist=', $Name)?></div>
          </div>
        </td>
<?
    foreach($Torrents as $TorrentID => $Torrent) {
      $Reported = false;
      $Reports = Torrents::get_reports($TorrentID);
      if (count($Reports) > 0) {
        $Reported = true;
      }

      $SnatchedTorrentClass = $Torrent['IsSnatched'] ? ' snatched_torrent' : '';
      $TorrentDL = "torrents.php?action=download&amp;id=".$TorrentID."&amp;authkey=".$LoggedUser['AuthKey']."&amp;torrent_pass=".$LoggedUser['torrent_pass'];
      $TorrentMG = "magnet:?xt=urn:btih:".$Torrent['info_hash']."&as=https://".SITE_DOMAIN."/".str_replace('&amp;','%26',$TorrentDL)."&tr=".implode("/".$LoggedUser['torrent_pass']."/announce&tr=",ANNOUNCE_URLS[0])."/".$LoggedUser['torrent_pass']."/announce&xl=".$Torrent['Size'];
?>
    <tr class="torrent_row groupid_<?=$GroupID?> group_torrent discog<?=$SnatchedTorrentClass . $SnatchedGroupClass . $HideTorrents?>">
      <td colspan="2">
          <span>
          [ <a href="<?=$TorrentDL?>" class="tooltip" title="Download">DL</a>
          | <a href="<?=$TorrentMG?>" class="tooltip" title="Magnet Link">MG</a>
<? if (Torrents::can_use_token($Torrent)) { ?>
          | <a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>&amp;usetoken=1" class="tooltip" title="Use a FL Token" onclick="return confirm('Are you sure you want to use a freeleech token here?');">FL</a>
<?    } ?>
          | <a href="reportsv2.php?action=report&amp;id=<?=$TorrentID?>" class="tooltip" title="Report">RP</a> ]
        </span>
        &nbsp;&nbsp;&raquo;&nbsp; <a href="torrents.php?id=<?=$GroupID?>&amp;torrentid=<?=$TorrentID?>"><?=Torrents::torrent_info($Torrent)?></a>
      </td>
      <td class="number_column nobr"><?=Format::get_size($Torrent['Size'])?></td>
      <td class="number_column"><?=number_format($Torrent['Snatched'])?></td>
      <td class="number_column<?=(($Torrent['Seeders'] == 0) ? ' r00' : '')?>"><?=number_format($Torrent['Seeders'])?></td>
      <td class="number_column"><?=number_format($Torrent['Leechers'])?></td>
    </tr>

<?
    }
  } else {
    list($TorrentID, $Torrent) = each($Torrents);

    $TorrentTags = new Tags($TagList, false);

    $DisplayName = Artists::display_artists(Artists::get_artist($GroupID), true, true);

    $Reported = false;
    $Reports = Torrents::get_reports($TorrentID);
    if (count($Reports) > 0) {
      $Reported = true;
    }

    $DisplayName .= "<a class=\"torrent_name\" href=\"torrents.php?id=$GroupID&amp;torrentid=$TorrentID#torrent$TorrentID\" ";
    if (!isset($LoggedUser['CoverArt']) || $LoggedUser['CoverArt']) {
      $DisplayName .= "onmouseover=\"getCover(event)\" cover=\"".ImageTools::process($WikiImage, true)."\" onmouseleave=\"ungetCover(event)\" ";
    }

    $GroupName = empty($GroupName) ? (empty($GroupNameRJ) ? $GroupNameJP : $GroupNameRJ) : $GroupName;
    $DisplayName .= "dir=\"ltr\">$GroupName</a>";

    if ($GroupYear) {
      $DisplayName .= " [$GroupYear]";
    }
    if ($GroupStudio) {
      $DisplayName .= " [$GroupStudio]";
    }
    if ($GroupCatalogueNumber) {
      $DisplayName .= " [$GroupCatalogueNumber]";
    }
    if ($GroupPages) {
      $DisplayName .= " [{$GroupPages}p]";
    }
    if ($GroupDLSiteID) {
      $DisplayName .= " [$GroupDLSiteID]";
    }

    if (check_perms('users_mod') || check_perms('torrents_fix_ghosts')) {
      $DisplayName .= ' <a href="torrents.php?action=fix_group&amp;groupid='.$GroupID.'&amp;artistid='.$ArtistID.'&amp;auth='.$LoggedUser['AuthKey'].'" class="brackets tooltip" title="Fix ghost DB entry">Fix</a>';
    }

    $ExtraInfo = Torrents::torrent_info($Torrent, true, true);

    $SnatchedGroupClass = ($GroupFlags['IsSnatched'] ? ' snatched_group' : '');
    $SnatchedTorrentClass = $Torrent['IsSnatched'] ? ' snatched_torrent' : '';

    $TorrentDL = "torrents.php?action=download&amp;id=".$TorrentID."&amp;authkey=".$LoggedUser['AuthKey']."&amp;torrent_pass=".$LoggedUser['torrent_pass'];
    $TorrentMG = "magnet:?xt=urn:btih:".$Torrent['info_hash']."&as=https://".SITE_DOMAIN."/".str_replace('&amp;','%26',$TorrentDL)."&tr=".implode("/".$LoggedUser['torrent_pass']."/announce&tr=",ANNOUNCE_URLS[0])."/".$LoggedUser['torrent_pass']."/announce&xl=".$Torrent['Size'];

?>
    <tr class="torrent<?=$SnatchedTorrentClass . $SnatchedGroupClass?>">
      <td class="center">
      </td>
      <td class="big_info">
        <div class="group_info clear">
          <div class="float_right">
            <span>
            [ <a href="<?=$TorrentDL?>" class="tooltip" title="Download">DL</a>
            | <a href="<?=$TorrentMG?>" class="tooltip" title="Magnet Link">MG</a>
<? if (Torrents::can_use_token($Torrent)) { ?>
            | <a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>&amp;usetoken=1" class="tooltip" title="Use a FL Token" onclick="return confirm('Are you sure you want to use a freeleech token here?');">FL</a>
<?    } ?>
            | <a href="reportsv2.php?action=report&amp;id=<?=$TorrentID?>" class="tooltip" title="Report">RP</a> ]
          </span>
          <br />
<?  if (Bookmarks::has_bookmarked('torrent', $GroupID)) { ?>
          <span class="remove_bookmark float_right">
            <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Unbookmark('torrent', <?=$GroupID?>, 'Bookmark'); return false;">Remove bookmark</a>
          </span>
<?  } else { ?>
          <span class="add_bookmark float_right">
            <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Bookmark('torrent', <?=$GroupID?>, 'Remove bookmark'); return false;">Bookmark</a>
          </span>
<?  } ?>
        </div>
        <?=$DisplayName?>
        <br />
        <div style="display: inline;" class="torrent_info"><?=$ExtraInfo?><? if ($Reported) { ?> / <strong class="torrent_label tl_reported">Reported</strong><? } ?></div>
        <div class="tags"><?=$TorrentTags->format('torrents.php?taglist=', $Name)?></div>
      </div>
    </td>
    <td class="number_column nobr"><?=Format::get_size($Torrent['Size'])?></td>
    <td class="number_column"><?=number_format($Torrent['Snatched'])?></td>
    <td class="number_column<?=(($Torrent['Seeders'] == 0) ? ' r00' : '')?>"><?=number_format($Torrent['Seeders'])?></td>
    <td class="number_column"><?=number_format($Torrent['Leechers'])?></td>
  </tr>
<?
  }
}
?>
  </table>
</div>
<?

$TorrentDisplayList = ob_get_clean();

//----------------- End building list and getting stats

// Comments (must be loaded before View::show_header so that subscriptions and quote notifications are handled properly)
list($NumComments, $Page, $Thread, $LastRead) = Comments::load('artist', $ArtistID);

View::show_header($Name, 'browse,requests,bbcode,comments,voting,recommend,subscriptions');
?>
<div class="thin">
  <div class="header">
    <h2><?=display_str($Name)?><? if ($RevisionID) { ?> (Revision #<?=$RevisionID?>)<? } ?></h2>
    <div class="linkbox">
<?  if (check_perms('site_submit_requests')) { ?>
      <a href="requests.php?action=new&amp;artistid=<?=$ArtistID?>" class="brackets">Add request</a>
<?
  }

if (check_perms('site_torrents_notify')) {
  if (($Notify = $Cache->get_value('notify_artists_'.$LoggedUser['ID'])) === false) {
    $DB->query("
      SELECT ID, Artists
      FROM users_notify_filters
      WHERE UserID = '$LoggedUser[ID]'
        AND Label = 'Artist notifications'
      LIMIT 1");
    $Notify = $DB->next_record(MYSQLI_ASSOC, false);
    $Cache->cache_value('notify_artists_'.$LoggedUser['ID'], $Notify, 0);
  }
  if (stripos($Notify['Artists'], "|$Name|") === false) {
?>
      <a href="artist.php?action=notify&amp;artistid=<?=$ArtistID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Notify of new uploads</a>
<?  } else { ?>
      <a href="artist.php?action=notifyremove&amp;artistid=<?=$ArtistID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Do not notify of new uploads</a>
<?
  }
}

  if (Bookmarks::has_bookmarked('artist', $ArtistID)) {
?>
      <a href="#" id="bookmarklink_artist_<?=$ArtistID?>" onclick="Unbookmark('artist', <?=$ArtistID?>, 'Bookmark'); return false;" class="brackets">Remove bookmark</a>
<?  } else { ?>
      <a href="#" id="bookmarklink_artist_<?=$ArtistID?>" onclick="Bookmark('artist', <?=$ArtistID?>, 'Remove bookmark'); return false;" class="brackets">Bookmark</a>
<?  } ?>
      <a href="#" id="subscribelink_artist<?=$ArtistID?>" class="brackets" onclick="SubscribeComments('artist', <?=$ArtistID?>);return false;"><?=Subscriptions::has_subscribed_comments('artist', $ArtistID) !== false ? 'Unsubscribe' : 'Subscribe'?></a>
<!--  <a href="#" id="recommend" class="brackets">Recommend</a> -->
<?
  if (check_perms('site_edit_wiki')) {
?>
      <a href="artist.php?action=edit&amp;artistid=<?=$ArtistID?>" class="brackets">Edit</a>
<?  } ?>
      <a href="artist.php?action=history&amp;artistid=<?=$ArtistID?>" class="brackets">View history</a>
<?  if ($RevisionID && check_perms('site_edit_wiki')) { ?>
      <a href="artist.php?action=revert&amp;artistid=<?=$ArtistID?>&amp;revisionid=<?=$RevisionID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Revert to this revision</a>
<?  } ?>
      <a href="artist.php?id=<?=$ArtistID?>#info" class="brackets">Info</a>
      <a href="artist.php?id=<?=$ArtistID?>#artistcomments" class="brackets">Comments</a>
<?  if (check_perms('site_delete_artist') && check_perms('torrents_delete')) { ?>
      <a href="artist.php?action=delete&amp;artistid=<?=$ArtistID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Delete</a>
<?  } ?>
    </div>
  </div>
<? /* Misc::display_recommend($ArtistID, "artist"); */ ?>
  <div class="sidebar">
<?  if ($Image) { ?>
    <div class="box box_image">
      <div class="head"><strong><?=$Name?></strong></div>
      <div style="text-align: center; padding: 10px 0px;">
        <img style="max-width: 220px;" class="lightbox-init" src="<?=ImageTools::process($Image, true)?>" alt="<?=$Name?>" />
      </div>
    </div>
<?  } ?>

    <div class="box box_search">
      <div class="head"><strong>File Lists Search</strong></div>
      <ul class="nobullet">
        <li>
          <form class="search_form" name="filelists" action="torrents.php">
            <input type="hidden" name="artistname" value="<?=$Name?>" />
            <input type="hidden" name="action" value="advanced" />
            <input type="text" autocomplete="off" id="filelist" name="filelist" size="20" />
            <input type="submit" value="&gt;" />
          </form>
        </li>
      </ul>
    </div>
<?

/* if (check_perms('zip_downloader')) {
  if (isset($LoggedUser['Collector'])) {
    list($ZIPList, $ZIPPrefs) = $LoggedUser['Collector'];
    $ZIPList = explode(':', $ZIPList);
  } else {
    $ZIPList = array('00', '11');
    $ZIPPrefs = 1;
  }
?>
    <div class="box box_zipdownload">
      <div class="head colhead_dark"><strong>Collector</strong></div>
      <div class="pad">
        <form class="download_form" name="zip" action="artist.php" method="post">
          <input type="hidden" name="action" value="download" />
          <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
          <input type="hidden" name="artistid" value="<?=$ArtistID?>" />
          <ul id="list" class="nobullet">
<? foreach ($ZIPList as $ListItem) { ?>
            <li id="list<?=$ListItem?>">
              <input type="hidden" name="list[]" value="<?=$ListItem?>" />
              <span style="float: left;"><?=$ZIPOptions[$ListItem]['2']?></span>
              <span class="remove remove_collector"><a href="#" onclick="remove_selection('<?=$ListItem?>'); return false;" style="float: right;" class="brackets tooltip" title="Remove format from the Collector">X</a></span>
              <br style="clear: all;" />
            </li>
<? } ?>
          </ul>
          <select id="formats" style="width: 180px;">
<?
$OpenGroup = false;
$LastGroupID = -1;

foreach ($ZIPOptions as $Option) {
  list($GroupID, $OptionID, $OptName) = $Option;

  if ($GroupID != $LastGroupID) {
    $LastGroupID = $GroupID;
    if ($OpenGroup) { ?>
            </optgroup>
<?    } ?>
            <optgroup label="<?=$ZIPGroups[$GroupID]?>">
<?    $OpenGroup = true;
  }
?>
              <option id="opt<?=$GroupID.$OptionID?>" value="<?=$GroupID.$OptionID?>"<? if (in_array($GroupID.$OptionID, $ZIPList)) { echo ' disabled="disabled"'; } ?>><?=$OptName?></option>
<?
}
?>
            </optgroup>
          </select>
          <button type="button" onclick="add_selection()">+</button>
          <select name="preference" style="width: 210px;">
            <option value="0"<? if ($ZIPPrefs == 0) { echo ' selected="selected"'; } ?>>Prefer Original</option>
            <option value="1"<? if ($ZIPPrefs == 1) { echo ' selected="selected"'; } ?>>Prefer Best Seeded</option>
            <option value="2"<? if ($ZIPPrefs == 2) { echo ' selected="selected"'; } ?>>Prefer Bonus Tracks</option>
          </select>
          <input type="submit" style="width: 210px;" value="Download" />
        </form>
      </div>
    </div>
<?
} //if (check_perms('zip_downloader'))*/ ?>
    <div class="box box_tags">
      <div class="head"><strong>Tags</strong></div>
      <ul class="stats nobullet">
<?      Tags::format_top(50, 'torrents.php?taglist=', $Name); ?>
      </ul>
    </div>
<?
// Stats
?>
    <div class="box box_info box_statistics_artist">
      <div class="head"><strong>Statistics</strong></div>
      <ul class="stats nobullet">
        <li>Number of groups: <?=number_format($NumGroups)?></li>
        <li>Number of torrents: <?=number_format($NumTorrents)?></li>
        <li>Number of seeders: <?=number_format($NumSeeders)?></li>
        <li>Number of leechers: <?=number_format($NumLeechers)?></li>
        <li>Number of snatches: <?=number_format($NumSnatches)?></li>
      </ul>
    </div>
<?


if (empty($SimilarArray)) {
  $DB->query("
    SELECT
      s2.ArtistID,
      a.Name,
      ass.Score,
      ass.SimilarID
    FROM artists_similar AS s1
      JOIN artists_similar AS s2 ON s1.SimilarID = s2.SimilarID AND s1.ArtistID != s2.ArtistID
      JOIN artists_similar_scores AS ass ON ass.SimilarID = s1.SimilarID
      JOIN artists_group AS a ON a.ArtistID = s2.ArtistID
    WHERE s1.ArtistID = '$ArtistID'
    ORDER BY ass.Score DESC
    LIMIT 30
  ");
  $SimilarArray = $DB->to_array();
  $NumSimilar = count($SimilarArray);
}
?>
    <div class="box box_artists">
      <div class="head"><strong>Similar</strong></div>
      <ul class="stats nobullet">
<?  if ($NumSimilar == 0) { ?>
        <li><span style="font-style: italic;">None found</span></li>
<?
  }
  $First = true;
  foreach ($SimilarArray as $SimilarArtist) {
    list($Artist2ID, $Artist2Name, $Score, $SimilarID) = $SimilarArtist;
    $Score = $Score / 100;
    if ($First) {
      $Max = $Score + 1;
      $First = false;
    }

    $FontSize = (ceil(((($Score - 2) / $Max - 2) * 4))) + 8;

?>
        <li>
          <span class="tooltip" title="<?=$Score?>"><a href="artist.php?id=<?=$Artist2ID?>" style="float: left; display: block;"><?=$Artist2Name?></a></span>
          <div style="float: right; display: block; letter-spacing: -1px;">
            <a href="artist.php?action=vote_similar&amp;artistid=<?=$ArtistID?>&amp;similarid=<?=$SimilarID?>&amp;way=up" class="tooltip brackets vote_artist_up" title="Vote up this similar artist. Use this when you feel that the two artists are quite similar.">&and;</a>
            <a href="artist.php?action=vote_similar&amp;artistid=<?=$ArtistID?>&amp;similarid=<?=$SimilarID?>&amp;way=down" class="tooltip brackets vote_artist_down" title="Vote down this similar artist. Use this when you feel that the two artists are not all that similar.">&or;</a>
<?    if (check_perms('site_delete_tag')) { ?>
            <span class="remove remove_artist"><a href="artist.php?action=delete_similar&amp;similarid=<?=$SimilarID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="tooltip brackets" title="Remove this similar artist">X</a></span>
<?    } ?>
          </div>
          <br style="clear: both;" />
        </li>
<?    } ?>
      </ul>
    </div>
    <div class="box box_addartists box_addartists_similar">
      <div class="head"><strong>Add similar</strong></div>
      <ul class="nobullet">
        <li>
          <form class="add_form" name="similar_artists" action="artist.php" method="post">
            <input type="hidden" name="action" value="add_similar" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <input type="hidden" name="artistid" value="<?=$ArtistID?>" />
            <input type="text" autocomplete="off" id="artistsimilar" name="artistname" size="20"<? Users::has_autocomplete_enabled('other'); ?> />
            <input type="submit" value="+" />
          </form>
        </li>
      </ul>
    </div>
  </div>
  <div class="main_column">
    <div id="artist_information" class="box">
      <div id="info" class="head">
        <a href="#">&uarr;</a>&nbsp;
        <strong>Information</strong>
        <a class="brackets" toggle-target="#body">Toggle</a>
      </div>
      <div id="body" class="body"><?=Text::full_format($Body)?></div>
    </div>
<?

echo $TorrentDisplayList;

$Collages = $Cache->get_value("artists_collages_$ArtistID");
if (!is_array($Collages)) {
  $DB->query("
    SELECT c.Name, c.NumTorrents, c.ID
    FROM collages AS c
      JOIN collages_artists AS ca ON ca.CollageID = c.ID
    WHERE ca.ArtistID = '$ArtistID'
      AND Deleted = '0'
      AND CategoryID = '7'");
  $Collages = $DB->to_array();
  $Cache->cache_value("artists_collages_$ArtistID", $Collages, 3600 * 6);
}
if (count($Collages) > 0) {
  if (count($Collages) > MAX_COLLAGES) {
    // Pick some at random
    $Range = range(0,count($Collages) - 1);
    shuffle($Range);
    $Indices = array_slice($Range, 0, MAX_COLLAGES);
    $SeeAll = ' <a toggle-target=".collage_rows">(See all)</a>';
  } else {
    $Indices = range(0, count($Collages)-1);
    $SeeAll = '';
  }
?>
  <table class="collage_table" id="collages">
    <tr class="colhead">
      <td width="85%"><a href="#">&uarr;</a>&nbsp;This artist is in <?=number_format(count($Collages))?> collage<?=((count($Collages) > 1) ? 's' : '')?><?=$SeeAll?></td>
      <td># artists</td>
    </tr>
<?
      foreach ($Indices as $i) {
        list($CollageName, $CollageArtists, $CollageID) = $Collages[$i];
        unset($Collages[$i]);
?>
          <tr>
            <td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
            <td><?=number_format($CollageArtists)?></td>
          </tr>
<?
      }
      foreach ($Collages as $Collage) {
        list($CollageName, $CollageArtists, $CollageID) = $Collage;
?>
          <tr class="collage_rows hidden">
            <td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
            <td><?=number_format($CollageArtists)?></td>
          </tr>
<?      } ?>
  </table>
<?
}

if ($NumRequests > 0) {

?>
  <table cellpadding="6" cellspacing="1" border="0" class="request_table border" width="100%" id="requests">
    <tr class="colhead_dark">
      <td style="width: 48%;">
        <a href="#">&uarr;</a>&nbsp;
        <strong>Request Name</strong>
      </td>
      <td class="nobr">
        <strong>Vote</strong>
      </td>
      <td class="nobr">
        <strong>Bounty</strong>
      </td>
      <td>
        <strong>Added</strong>
      </td>
    </tr>
<?
  $Tags = Requests::get_tags(array_keys($Requests));
  foreach ($Requests as $RequestID => $Request) {
      $CategoryName = $Categories[$Request['CategoryID'] - 1];
      $Title = empty($Request['Title']) ? (empty($Request['TitleRJ']) ? display_str($Request['TitleJP']) : display_str($Request['TitleRJ'])) : display_str($Request['Title']);
      if ($CategoryName != 'Other') {
        $ArtistForm = Requests::get_artists($RequestID);
        $ArtistLink = Artists::display_artists($ArtistForm, true, true);
        $FullName = $ArtistLink."<a href=\"requests.php?action=view&amp;id=$RequestID\"><span dir=\"ltr\">$Title</span></a>";

        if ($Request['CatalogueNumber']) {
          $FullName .= " [$Request[CatalogueNumber]]";
        }
        if ($Request['DLSiteID']) {
          $FullName.= " [$Request[DLSiteID]]";
        }
      } else {
        $FullName = "<a href=\"requests.php?action=view&amp;id=$RequestID\" dir=\"ltr\">$Title</a>";
      }

      if (!empty($Tags[$RequestID])) {
        $ReqTagList = array();
        foreach ($Tags[$RequestID] as $TagID => $TagName) {
          $ReqTagList[] = "<a href=\"requests.php?tags=$TagName\">".display_str($TagName).'</a>';
        }
        $ReqTagList = implode(', ', $ReqTagList);
      } else {
        $ReqTagList = '';
      }
?>
    <tr class="row">
      <td>
        <?=$FullName?>
        <div class="tags"><?=$ReqTagList?></div>
      </td>
      <td class="nobr">
        <span id="vote_count_<?=$RequestID?>"><?=$Request['Votes']?></span>
<?    if (check_perms('site_vote')) { ?>
        <input type="hidden" id="auth" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        &nbsp;&nbsp; <a href="javascript:Vote(0, <?=$RequestID?>)" class="brackets"><strong>+</strong></a>
<?    } ?>
      </td>
      <td class="nobr">
        <span id="bounty_<?=$RequestID?>"><?=Format::get_size($Request['Bounty'])?></span>
      </td>
      <td>
        <?=time_diff($Request['TimeAdded'])?>
      </td>
    </tr>
<?  } ?>
  </table>
<?
}

// Similar Artist Map

if ($NumSimilar > 0) {
  if ($SimilarData = $Cache->get_value("similar_positions_$ArtistID")) {
    $Similar = new ARTISTS_SIMILAR($ArtistID, $Name);
    $Similar->load_data($SimilarData);
    if (!(current($Similar->Artists)->NameLength)) {
      unset($Similar);
    }
  }
  if (empty($Similar) || empty($Similar->Artists)) {
    include(SERVER_ROOT.'/classes/image.class.php');
    $Img = new IMAGE;
    $Img->create(WIDTH, HEIGHT);
    $Img->color(255, 255, 255, 127);

    $Similar = new ARTISTS_SIMILAR($ArtistID, $Name);
    $Similar->set_up();
    $Similar->set_positions();
    $Similar->background_image();

    $SimilarData = $Similar->dump_data();

    $Cache->cache_value("similar_positions_$ArtistID", $SimilarData, 3600 * 24);
  }
?>
    <div id="similar_artist_map" class="box">
      <div id="flipper_head" class="head">
        <a href="#">&uarr;</a>&nbsp;
        <strong id="flipper_title">Similar Map</strong>
        <a id="flip_to" class="brackets" href="#" onclick="flipView(); return false;">Switch to cloud</a>
      </div>
      <div id="flip_view_1" style="display: block; width: <?=(WIDTH)?>px; height: <?=(HEIGHT)?>px; position: relative; background-image: url(static/similar/<?=($ArtistID)?>.png?t=<?=(time())?>);">
<?
  $Similar->write_artists();
?>
      </div>
      <div id="flip_view_2" style="display: none; width: <?=WIDTH?>px; height: <?=HEIGHT?>px;">
        <canvas width="<?=WIDTH?>px" height="<?=(HEIGHT - 20)?>px" id="similarArtistsCanvas"></canvas>
        <div id="artistTags" style="display: none;">
          <ul><li></li></ul>
        </div>
        <strong style="margin-left: 10px;"><a id="currentArtist" href="#null">Loading...</a></strong>
      </div>
    </div>

<script type="text/javascript">//<![CDATA[
var cloudLoaded = false;

function flipView() {
  var state = document.getElementById('flip_view_1').style.display == 'block';

  if (state) {
    document.getElementById('flip_view_1').style.display = 'none';
    document.getElementById('flip_view_2').style.display = 'block';
    document.getElementById('flipper_title').innerHTML = 'Similar Cloud';
    document.getElementById('flip_to').innerHTML = 'Switch to map';

    if (!cloudLoaded) {
      require("static/functions/tagcanvas.js", function () {
        require("static/functions/artist_cloud.js", function () {
        });
      });
      cloudLoaded = true;
    }
  }
  else {
    document.getElementById('flip_view_1').style.display = 'block';
    document.getElementById('flip_view_2').style.display = 'none';
    document.getElementById('flipper_title').innerHTML = 'Similar Map';
    document.getElementById('flip_to').innerHTML = 'Switch to cloud';
  }
}

//TODO move this to global, perhaps it will be used elsewhere in the future
//http://stackoverflow.com/questions/7293344/load-javascript-dynamically
function require(file, callback) {
  var script = document.getElementsByTagName('script')[0],
  newjs = document.createElement('script');

  // IE
  newjs.onreadystatechange = function () {
    if (newjs.readyState === 'loaded' || newjs.readyState === 'complete') {
      newjs.onreadystatechange = null;
      callback();
    }
  };
  // others
  newjs.onload = function () {
    callback();
  };
  newjs.src = file;
  script.parentNode.insertBefore(newjs, script);
}
//]]>
</script>

<? }

// --- Comments ---
$Pages = Format::get_pages($Page, $NumComments, TORRENT_COMMENTS_PER_PAGE, 9, '#comments');

?>
  <div id="artistcomments">
    <div class="linkbox"><a name="comments"></a>
      <?=($Pages)?>
    </div>
<?

//---------- Begin printing
CommentsView::render_comments($Thread, $LastRead, "artist.php?id=$ArtistID");
?>
    <div class="linkbox">
      <?=($Pages)?>
    </div>
<?
  View::parse('generic/reply/quickreply.php', array(
    'InputName' => 'pageid',
    'InputID' => $ArtistID,
    'Action' => 'comments.php?page=artist',
    'InputAction' => 'take_post',
    'SubscribeBox' => true
  ));
?>
    </div>
  </div>
</div>
<?
View::show_footer();


// Cache page for later use

if ($RevisionID) {
  $Key = "artist_$ArtistID" . "_revision_$RevisionID";
} else {
  $Key = "artist_$ArtistID";
}

$Data = array(array($Name, $Image, $Body, $NumSimilar, $SimilarArray, array(), array(), $VanityHouseArtist));

$Cache->cache_value($Key, $Data, 3600);
?>
