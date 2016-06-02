<?
class RevisionHistoryView {
	/**
	 * Render the revision history
	 * @param array $RevisionHistory see RevisionHistory::get_revision_history
	 * @param string $BaseURL
	 */
	public static function render_revision_history($RevisionHistory, $BaseURL) {
?>
	<table cellpadding="6" cellspacing="1" border="0" width="100%" class="box">
		<tr class="colhead">
			<td>Revision</td>
			<td>Date</td>
			<td>User</td>
			<td>Summary</td>
		</tr>
<?
		foreach ($RevisionHistory as $Entry) {
			list($RevisionID, $Summary, $Time, $UserID) = $Entry;
?>
		<tr class="row">
			<td>
				<?= "<a href=\"$BaseURL&amp;revisionid=$RevisionID\">#$RevisionID</a>" ?>
			</td>
			<td>
				<?=$Time?>
			</td>
			<td>
				<?=Users::format_username($UserID, false, false, false)?>
			</td>
			<td>
				<?=($Summary ? $Summary : '(empty)')?>
			</td>
		</tr>
<?		} ?>
	</table>
<?
	}
}
