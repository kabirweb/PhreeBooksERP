<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2013 PhreeSoft, LLC (www.PhreeSoft.com)       |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/phreemail/pages/main/pre_process.php
//
$security_level = validate_user(SECURITY_PHREEMAIL_MGT);
/**************  include page specific files    *********************/
require_once(DIR_FS_WORKING . 'classes/phreemail.php');
require_once(DIR_FS_WORKING . 'defaults.php');
/**************   page specific initialization  *************************/
$error       = false;
$processed   = false;
$mail		 = new phreemail();
if(!isset($_REQUEST['list'])) $_REQUEST['list'] = 1;
if ($_REQUEST['search_text'] == TEXT_SEARCH) $_REQUEST['search_text'] = '';
if (!$_REQUEST['action'] && $_REQUEST['search_text'] <> '') $_REQUEST['action'] = 'search'; // if enter key pressed and search not blank
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/main/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'create':
	if ($security_level < 2) {
		$messageStack->add_session(ERROR_NO_PERMISSION, 'error');
		gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
		break;
	}
	
	break;
  case 'delete':
	if ($security_level < 4) {
		$messageStack->add_session(ERROR_NO_PERMISSION,'error');
		gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
		break;
	}
	
  case 'save':
	if ($security_level < 3) {
		$messageStack->add_session(ERROR_NO_PERMISSION,'error');
		gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
		break;
	}
	
	break;
  case 'copy':
	if ($security_level < 2) {
		$messageStack->add_session(ERROR_NO_PERMISSION,'error');
		gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
		break;
	}
	
  case 'edit':
    $mail->getEmailFromDb($_POST['rowSeq']);
	break;
  case 'download':
	$cID   = db_prepare_input($_POST['id']);
	$imgID = db_prepare_input($_POST['rowSeq']);
	$filename = 'assets_'.$cID.'_'.$imgID.'.zip';
	if (file_exists(ASSETS_DIR_ATTACHMENTS . $filename)) {
		require_once(DIR_FS_MODULES . 'phreedom/classes/backup.php');
		$backup = new backup();
		$backup->download(ASSETS_DIR_ATTACHMENTS, $filename, true);
	}
	ob_end_flush();
	session_write_close();
	die;
  case 'dn_attach': // download from list, assume the first document only
	$cID   = db_prepare_input($_POST['rowSeq']);
	$result = $db->Execute("select attachments from " . TABLE_PHREEMAIL . " where id = " . $cID);
	$attachments = unserialize($result->fields['attachments']);
	foreach ($attachments as $key => $value) {
	  	$filename = 'mail_'.$cID.'_'.$key.'.zip';
	  	if (file_exists(ASSETS_DIR_ATTACHMENTS . $filename)) {
			require_once(DIR_FS_MODULES . 'phreedom/classes/backup.php');
			$backup = new backup();
			$backup->download(ASSETS_DIR_ATTACHMENTS, $filename, true);
			ob_end_flush();
			session_write_close();
			die;
	  	}
	}
	break;

  case 'go_first':    $_REQUEST['list'] = 1;     break;
  case 'go_previous': $_REQUEST['list']--;       break;
  case 'go_next':     $_REQUEST['list']++;       break;
  case 'go_last':     $_REQUEST['list'] = 99999; break;
  case 'search':
  case 'search_reset':
  case 'go_page':
  default:
}

/*****************   prepare to display templates  *************************/

$include_header   = true;
$include_footer   = true;
switch ($_REQUEST['action']) {
  case 'new':
    define('PAGE_TITLE', BOX_ASSET_MODULE);
    $include_template = 'template_id.php';
    break;
  case 'edit':
	
    define('PAGE_TITLE', BOX_ASSET_MODULE);
    $include_template = 'template_detail.php';
    break;
  default:
    // build the list header
	$heading_array = array(
		'EmailFromP'  	=> TEXT_FROM,
	  	'Subject'    	=> TEXT_MESSAGE_SUBJECT,
		'DateE'     	=> TEXT_DATE,  
	);
	$result      = html_heading_bar($heading_array);
	$list_header = $result['html_code'];
	$disp_order  = $result['disp_order'];
	// build the list for the page selected
    if (isset($_REQUEST['search_text']) && $_REQUEST['search_text'] <> '') {
      $search_fields = array('Subject', 'EmailFromP', 'Message');
	  // hook for inserting new search fields to the query criteria.
	  if (is_array($extra_search_fields)) $search_fields = array_merge($search_fields, $extra_search_fields);
	  $search = ' where ' . implode(' like \'%' . $_REQUEST['search_text'] . '%\' or ', $search_fields) . ' like \'%' . $_REQUEST['search_text'] . '%\'';
    } else {
	  $search = '';
	}
	$field_list = array('id', 'EmailFromP', 'Subject', 'DateE');
	// hook to add new fields to the query return results
	if (is_array($extra_query_list_fields) > 0) $field_list = array_merge($field_list, $extra_query_list_fields);

    $query_raw    = "select SQL_CALC_FOUND_ROWS " . implode(', ', $field_list)  . " from " . TABLE_PHREEMAIL . $search . " order by $disp_order";
    $query_result = $db->Execute($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".  MAX_DISPLAY_SEARCH_RESULTS);
    // the splitPageResults should be run directly after the query that contains SQL_CALC_FOUND_ROWS
    $query_split  = new splitPageResults($_REQUEST['list'], '');
	define('PAGE_TITLE', BOX_ASSET_MODULE);
    $include_template = 'template_main.php';
	break;
}

?>