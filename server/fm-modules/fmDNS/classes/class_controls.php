<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013 The facileManager Team                               |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | facileManager: Easy System Administration                               |
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

class fm_dns_controls {
	
	/**
	 * Displays the control list
	 */
	function rows($result) {
		global $fmdb;
		
		if (!$result) {
			echo '<p id="table_edits" class="noresult" name="controls">There are no controls.</p>';
		} else {
			$num_rows = $fmdb->num_rows;
			$results = $fmdb->last_result;
			
			$table_info = array(
							'class' => 'display_results',
							'id' => 'table_edits',
							'name' => 'controls'
						);

			$title_array = array('IP Address', 'Port', 'Address List', 'Keys', 'Comment');
			if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => 'Actions', 'class' => 'header-actions');

			echo displayTableHeader($table_info, $title_array);
			
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x]);
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new control
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}controls`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'server_id');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not add the control because a database error occurred.';

		addLogEntry("Added control:\nIP: {$post['control_ip']}\nAddresses: $control_addresses\nComment: {$post['control_comment']}");
		return true;
	}

	/**
	 * Updates the selected control
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		/** Cleans up control_addresses for future parsing **/
//		$post['control_addresses'] = verifyAndCleanAddresses($post['control_addresses']);
//		if ($post['control_addresses'] === false) return 'Invalid address(es) specified.';
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'server_id');

		$sql_edit = null;
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		// Update the control
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}controls` SET $sql WHERE `control_id`={$post['control_id']}";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not update the control because a database error occurred.';

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		$control_addresses = $post['control_predefined'] == 'as defined:' ? $post['control_addresses'] : $post['control_predefined'];
		addLogEntry("Updated control '$old_name' to the following:\nName: {$post['control_name']}\nAddresses: $control_addresses\nComment: {$post['control_comment']}");
		return true;
	}
	
	
	/**
	 * Deletes the selected control
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'controls', 'control_', 'control_id', 'control_name');
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'controls', $id, 'control_', 'deleted', 'control_id') === false) {
			return 'This control could not be deleted because a database error occurred.';
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry("Deleted control '$tmp_name'.");
			return true;
		}
	}


	function displayRow($row) {
		global $__FM_CONFIG, $fm_dns_acls, $fm_dns_keys;
		
		if (!class_exists('fm_dns_acls')) {
			include(ABSPATH . 'fm-modules/fmDNS/classes/class_acls.php');
		}
		
		if (!class_exists('fm_dns_keys')) {
			include(ABSPATH . 'fm-modules/fmDNS/classes/class_keys.php');
		}
		
		$disabled_class = ($row->control_status == 'disabled') ? ' class="disabled"' : null;
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td id="edit_delete_img">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a href="' . $GLOBALS['basename'] . '?action=edit&id=' . $row->control_id . '&status=';
			$edit_status .= ($row->control_status == 'active') ? 'disabled' : 'active';
			$edit_status .= $row->server_serial_no ? '&server_serial_no=' . $row->server_serial_no : null;
			$edit_status .= '">';
			$edit_status .= ($row->control_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
		} else {
			$edit_status = null;
		}
		
		$control_port = !empty($row->control_port) ? $row->control_port : 953;
		$control_addresses = strpos($row->control_addresses, 'acl_') !== false ? $fm_dns_acls->parseACL($row->control_addresses) : $row->control_addresses;
		$control_keys = $fm_dns_keys->parseKey($row->control_keys);
		
		$comments = nl2br($row->control_comment);

		echo <<<HTML
		<tr id="$row->control_id"$disabled_class>
			<td>$row->control_ip</td>
			<td>$control_port</td>
			<td>$control_addresses</td>
			<td>$control_keys</td>
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new control
	 */
	function printForm($data = '', $action = 'add') {
		global $__FM_CONFIG, $fm_dns_acls, $fm_module_servers;
		
		$control_id = 0;
		$control_ip = $control_addresses = $control_comment = null;
		$control_port = $control_keys = null;
		$ucaction = ucfirst($action);
		$server_serial_no = (isset($_REQUEST['server_serial_no']) && $_REQUEST['server_serial_no'] > 0) ? sanitize($_REQUEST['server_serial_no']) : 0;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$control_addresses = str_replace(';', "\n", rtrim(str_replace(' ', '', $control_addresses), ';'));
		$control_keys = buildSelect('control_keys', 'control_keys', $fm_module_servers->availableKeys('nonempty'), explode(';', $control_keys), 1, null, true, null, null, 'Select one or more keys');

		$available_acls = $fm_dns_acls->buildACLJSON($control_addresses, $server_serial_no);
		
		$popup_header = buildPopup('header', $ucaction . ' Control');
		$popup_footer = buildPopup('footer');
		
		$return_form = <<<FORM
		<form name="manage" id="manage" method="post" action="">
		$popup_header
			<input type="hidden" name="action" value="$action" />
			<input type="hidden" name="control_id" value="$control_id" />
			<input type="hidden" name="server_serial_no" value="$server_serial_no" />
			<input type="hidden" name="control_keys" value="" />
			<table class="form-table">
				<tr>
					<th width="33%" scope="row"><label for="control_ip">IP Address</label></th>
					<td width="67%"><input name="control_ip" id="control_ip" type="text" value="$control_ip" size="40" placeholder="127.0.0.1" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="control_port">Port</label></th>
					<td width="67%"><input name="control_port" id="control_port" type="text" value="$control_port" size="40" placeholder="953" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="control_predefined">Allowed Address List</label></th>
					<td width="67%">
						<input type="hidden" name="control_addresses" id="address_match_element" data-placeholder="Define allowed hosts" value="$control_addresses" /><br />
						( address_match_element )
					</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="control_keys">Keys</label></th>
					<td width="67%">$control_keys</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="control_comment">Comment</label></th>
					<td width="67%"><textarea id="control_comment" name="control_comment" rows="4" cols="30">$control_comment</textarea></td>
				</tr>
			</table>
		$popup_footer
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: '200px',
					minimumResultsForSearch: 10,
					allowClear: true
				});
				$("#address_match_element").select2({
					createSearchChoice:function(term, data) { 
						if ($(data).filter(function() { 
							return this.text.localeCompare(term)===0; 
						}).length===0) 
						{return {id:term, text:term};} 
					},
					multiple: true,
					width: '200px',
					tokenSeparators: [",", " ", ";"],
					data: $available_acls
				});
			});
		</script>
FORM;

		return $return_form;
	}
	
	
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (!$post['control_id']) unset($post['control_id']);
		
		$post['control_comment'] = trim($post['control_comment']);
		
		if (is_array($post['control_keys'])) $post['control_keys'] = join(',', $post['control_keys']);
		
		if (!empty($post['control_ip']) && $post['control_ip'] != '*') {
			if (!verifyIPAddress($post['control_ip'])) $post['control_ip'] . ' is not a valid IP address.';
		} else $post['control_ip'] = '*';
		
		if (empty($post['control_addresses'])) {
			return "Allowed addresses not defined.";
		}
		
		if (!empty($post['control_port'])) {
			if (!verifyNumber($post['control_port'], 0, 65535)) return $post['control_port'] . ' is not a valid port number.';
		} else $post['control_port'] = 953;
		
		return $post;
	}
	
	
}

if (!isset($fm_dns_controls))
	$fm_dns_controls = new fm_dns_controls();

?>