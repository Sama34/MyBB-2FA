<?php
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Run/Add Hooks
if(!defined('IN_ADMINCP'))
{
	global $templatelist;

	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	else
	{
		$templatelist = '';
	}

	$plugins->add_hook("usercp_start", "mybb2fa_usercp");
	$plugins->add_hook("usercp_menu_built", "mybb2fa_usercp_menu_built");
	$plugins->add_hook("datahandler_login_complete_end", "mybb2fa_do_login");
	$plugins->add_hook("global_start", "mybb2fa_check_block");
	$plugins->add_hook("misc_start", "mybb2fa_check");

	$templatelist .= '';
}
else
{
	$plugins->add_hook("admin_load", "mybb2fa_admin_do_login");
}

// PLUGINLIBRARY
defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');

function mybb2fa_info()
{
	global $lang;

	mybb2fa_lang_load();

	return array(
		"name"			=> "MyBB 2FA",
		"description"	=> $lang->setting_group_mybb2fa_desc,
		"website"		=> "http://jonesboard.de/",
		"author"		=> "Jones",
		"authorsite"	=> "http://jonesboard.de/",
		"version"		=> "1.0",
		"compatibility" => "18*",
		"codename"		=> "ougc_mybbfa"
	);
}

function mybb2fa_install()
{
	global $PL;

	mybb2fa_pluginlibrary();

	mybb2fa_db_verify_tables();

	mybb2fa_db_verify_columns();

	mybb2fa_update_task_file();
}

function mybb2fa_is_installed()
{
	global $db;

	foreach(mybb2fa_db_tables() as $name => $table)
	{
		$is_installed = $db->table_exists($name);

		break;
	}

	return $is_installed;
}

function mybb2fa_uninstall()
{
	global $db, $PL, $cache;

	mybb2fa_pluginlibrary();

	$PL->settings_delete('mybb2fa');

	$PL->templates_delete('mybb2fa');

	// Drop DB entries
	mybb2fa_db_verify_tables(true);

	mybb2fa_db_verify_columns(true);

	mybb2fa_update_task_file(-1);

	// Delete version from cache
	$plugins = (array)$cache->read('ougc_plugins');

	if(isset($plugins['mybb2fa']))
	{
		unset($plugins['mybb2fa']);
	}

	if(!empty($plugins))
	{
		$cache->update('ougc_plugins', $plugins);
	}
	else
	{
		$delete->delete('ougc_plugins');
	}
}

function mybb2fa_activate()
{
	global $cache, $PL, $lang;

	mybb2fa_pluginlibrary();

	// Add our settings
	$PL->settings('mybb2fa', 'MyBB 2FA', $lang->setting_group_mybb2fa_desc, array(
		'forceacp'	=> array(
			'title'			=> $lang->setting_mybb2fa_forceacp,
			'description'	=> $lang->setting_mybb2fa_forceacp_desc,
			'optionscode'	=> 'yesno',
			'value'			=> 0,
		),
	));

	// Insert template/group
	$PL->templates('mybb2fa', 'MyBB 2FA', array(
		'form'	=> '<html>
	<head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->mybb2fa}</title>
		{$headerinclude}
	</head>
	<body>
		{$header}
		<table width="100%" border="0" align="center">
			<tr>
				<td valign="top">
					<form action="misc.php" method="post">
					<input type="hidden" name="action" value="mybb2fa" />
					<input type="hidden" name="uid" value="{$loginhandler->login_data[\'uid\']}" />
					<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
						<tr>
							<td class="thead"><strong>{$lang->mybb2fa}</strong></td>
						</tr>
						<tr>
							<td class="trow1">{$lang->mybb2fa_code}: <input type="text" class="textbox" name="code" /></td>
						</tr>
						<tr>
							<td class="trow2"><input type="submit" class="button" value="{$lang->mybb2fa_check}" /></td>
						</tr>
					</table>
					</form>
				</td>
			</tr>
		</table>
		{$footer}
	</body>
</html>',
		'usercp_activated'	=> '<html>
	<head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->mybb2fa}</title>
		{$headerinclude}
	</head>
	<body>
		{$header}
		<table width="100%" border="0" align="center">
			<tr>
				{$usercpnav}
				<td valign="top">
					<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
						<tr>
							<td class="thead"><strong>{$lang->mybb2fa}</strong></td>
						</tr>
						<tr>
							<td class="trow">{$lang->mybb2fa_activated_desc} <a href="usercp.php?action=mybb2fa&do=deactivate">{$lang->mybb2fa_deactivate}</a></td>
						</tr>
						<tr>
						<td class="trow2"><img src="{$qr}" /></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		{$footer}
	</body>
</html>',
		'usercp_deactivated'	=> '<html>
	<head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->mybb2fa}</title>
		{$headerinclude}
	</head>
	<body>
		{$header}
		<table width="100%" border="0" align="center">
			<tr>
				{$usercpnav}
				<td valign="top">
					<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
						<tr>
							<td class="thead" colspan="2"><strong>{$lang->mybb2fa}</strong></td>
						</tr>
						<tr>
							<td class="trow" colspan="2">{$lang->mybb2fa_deactivated_desc} <a href="usercp.php?action=mybb2fa&do=activate">{$lang->mybb2fa_activate}</a></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		{$footer}
	</body>
</html>',
		'usercp_nav'	=> '<div><a href="{$mybb->settings[\'bburl\']}/usercp.php?action=mybb2fa" class="usercp_nav_item usercp_nav_password">{$lang->mybb2fa_usercp_nav}</a></div>',
		''	=> ''
	));

	// Insert/update version into cache
	$plugins = $cache->read('ougc_plugins');

	if(!$plugins)
	{
		$plugins = array();
	}

	$info = mybb2fa_info();

	if(!isset($plugins['mybb2fa']))
	{
		$plugins['mybb2fa'] = $info['versioncode'];
	}

	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	find_replace_templatesets('usercp_nav_profile', '#'.preg_quote('{$changenameop}').'#', '{$changenameop}<!--mybb2fa-->');

	mybb2fa_update_task_file();

	/*~*~* RUN UPDATES START *~*~*/

	/*~*~* RUN UPDATES END *~*~*/

	$plugins['mybb2fa'] = $info['versioncode'];

	$cache->update('ougc_plugins', $plugins);
}

// List of tables
function mybb2fa_db_tables()
{
	$tables = array(
		'mybb2fa_log'		=> array(
			'id'			=> "int UNSIGNED NOT NULL AUTO_INCREMENT",
			'secret'		=> "varchar(16) NOT NULL",
			'code'			=> "varchar(6) NOT NULL",
			'time'			=> "int(10) NOT NULL DEFAULT '0'",
			'prymary_key'	=> 'id'
		)
	);

	return $tables;
}

// List of columns
function mybb2fa_db_columns()
{
	$tables = array(
		'users'	=> array(
			'secret' => "VARCHAR(16) NOT NULL default ''"
		),
		'sessions'	=> array(
			'mybb2fa_block' => "TINYINT(1) NOT NULL default '0'"
		),
	);

	return $tables;
}

// Verify DB columns
function mybb2fa_db_verify_columns($uninstall=false)
{
	global $db;

	foreach(mybb2fa_db_columns() as $table => $columns)
	{
		foreach($columns as $field => $definition)
		{
			if($db->field_exists($field, $table))
			{
				if($uninstall)
				{
					$db->drop_column($table, $field);
				}
				else
				{
					$db->modify_column($table, "`{$field}`", $definition);
				}
			}
			elseif(!$uninstall)
			{
				$db->add_column($table, $field, $definition);
			}
		}
	}
}

// Verify DB tables
function mybb2fa_db_verify_tables($uninstall=false)
{
	global $db;

	$collation = $db->build_create_table_collation();

	foreach(mybb2fa_db_tables() as $table => $fields)
	{
		if($db->table_exists($table))
		{
			if($uninstall)
			{
				$db->drop_table($table);

				continue;
			}

			foreach($fields as $field => $definition)
			{
				if($field == 'prymary_key')
				{
					continue;
				}

				if($db->field_exists($field, $table))
				{
					$db->modify_column($table, "`{$field}`", $definition);
				}
				else
				{
					$db->add_column($table, $field, $definition);
				}
			}
		}
		elseif(!$uninstall)
		{
			$query = "CREATE TABLE IF NOT EXISTS `".TABLE_PREFIX."{$table}` (";
			foreach($fields as $field => $definition)
			{
				if($field == 'prymary_key')
				{
					$query .= "PRIMARY KEY (`{$definition}`)";
				}
				else
				{
					$query .= "`{$field}` {$definition},";
				}
			}
			$query .= ") ENGINE=MyISAM{$collation};";
			$db->write_query($query);
		}
	}
}

// Install/update task file
function mybb2fa_update_task_file($action=1)
{
	global $db, $lang;

	mybb2fa_lang_load();

	if($action == -1)
	{
		$db->delete_query('tasks', "file='ougc_awards'");

		return;
	}

	$query = $db->simple_select('tasks', '*', "file='mybb2fa'", array('limit' => 1));
	$task = $db->fetch_array($query);

	if($task)
	{
		$db->update_query('tasks', array('enabled' => $action), "file='mybb2fa'");
	}
	else
	{
		include_once MYBB_ROOT.'inc/functions_task.php';

		$_ = $db->escape_string('*');

		$new_task = array(
			'title'			=> $db->escape_string($lang->setting_group_mybb2fa),
			'description'	=> $db->escape_string($lang->mybb2fa_task_desc),
			'file'			=> $db->escape_string('mybb2fa'),
			'minute'		=> '0,30',
			'hour'			=> $_,
			'day'			=> $_,
			'weekday'		=> $_,
			'month'			=> $_,
			'enabled'		=> 1,
			'logging'		=> 1
		);

		$new_task['nextrun'] = fetch_next_run($new_task);

		$db->insert_query('tasks', $new_task);
	}
}

function mybb2fa_deactivate()
{
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	find_replace_templatesets('usercp_nav_profile', '#'.preg_quote('<div><a href="usercp.php?action=mybb2fa" class="usercp_nav_item usercp_nav_password">MyBB 2FA</a></div>').'#i', '', 0);

	find_replace_templatesets('usercp_nav_profile', '#'.preg_quote('<!--mybb2fa-->').'#i', '', 0);

	mybb2fa_update_task_file(0);
}

// PluginLibrary dependency check & load
function mybb2fa_pluginlibrary()
{
	global $lang;

	mybb2fa_lang_load();

	$info = mybb2fa_info();

	if($file_exists = file_exists(PLUGINLIBRARY))
	{
		global $PL;
	
		$PL or require_once PLUGINLIBRARY;
	}

	if(!$file_exists || $PL->version < $info['pl']['version'])
	{
		flash_message($lang->sprintf($lang->ougc_chartstats_pluginlibrary, $info['pl']['url'], $info['pl']['version']), 'error');

		admin_redirect('index.php?module=config-plugins');
	}
}

function mybb2fa_usercp()
{
	global $db, $mybb, $headerinclude, $header, $usercpnav, $theme, $footer, $templates, $lang;

	if($mybb->input['action'] != "mybb2fa")
	    return;

	$lang->load("mybb2fa");

	require_once MYBB_ROOT."inc/3rdparty/2fa/GoogleAuthenticator.php";
	require_once MYBB_ROOT."inc/plugins/mybb2fa/AuthWrapper.php";
	$auth = new Authenticator;

	if(isset($mybb->input['do']))
	{
		if($mybb->input['do'] == "deactivate")
		{
			// Deactivating 2FA
			$mybb->user['secret'] = "";
			$db->update_query("users", array("secret" => ""), "uid={$mybb->user['uid']}");
		}
		else
		{
			// Activating 2FA
			$secret = $auth->createSecret();
			$mybb->user['secret'] = $secret;
			$db->update_query("users", array("secret" => $secret), "uid={$mybb->user['uid']}");
			// Redirect to avoid multiple different secrets
			redirect("usercp.php?action=mybb2fa", $lang->mybb2fa_activated);
		}
	}

	if(empty($mybb->user['secret']))
	{
		// 2FA is deactivated
		$mybb2fa = eval($templates->render("mybb2fa_usercp_deactivated"));
	}
	else
	{
		// 2FA is activated
		$qr = $auth->getQRCodeGoogleUrl($mybb->user['username']."@".str_replace(" ", "", $mybb->settings['bbname']), $mybb->user['secret']);

		$mybb2fa = eval($templates->render("mybb2fa_usercp_activated"));
	}
	
	output_page($mybb2fa);
}

function mybb2fa_usercp_menu_built()
{
	global $usercpnav, $templates, $lang, $mybb;

	mybb2fa_lang_load();

	$usercpnav = str_replace('<!--mybb2fa-->', eval($templates->render('mybb2fa_usercp_nav')), $usercpnav);
}

function mybb2fa_do_login($loginhandler)
{
	global $mybb, $db, $headerinclude, $header, $theme, $footer, $templates, $lang;

	// Ok, everything is ok so far; let's figure out whether we need to show our form
	$query = $db->simple_select("users", "secret", "uid={$loginhandler->login_data['uid']}");
	$secret = $db->fetch_field($query, "secret");
	if(empty($secret))
	    // User doesn't use the plugin, nothing to do
		return;

	$lang->load("mybb2fa");

	// Though the user is logged in we want to block him till he really logs in
	$db->update_query("sessions", array("mybb2fa_block" => 1), "sid='".$db->escape_string($mybb->cookies['sid'])."'");

	// Show our nice form
	$mybb2fa = eval($templates->render("mybb2fa_form"));
	output_page($mybb2fa);
	exit;
}

function mybb2fa_check_block()
{
	global $session, $mybb, $db;

	$query = $db->simple_select("sessions", "mybb2fa_block", "sid='".$db->escape_string($mybb->cookies['sid'])."'");
	$block = $db->fetch_field($query, "mybb2fa_block");

	if($block == 1)
	{
	    $session->load_guest();
	}
}

function mybb2fa_check()
{
	global $mybb, $db, $lang;

	if($mybb->input['action'] != "mybb2fa")
	    return;

	// Nope, we don't want you here
	if(!isset($mybb->input['uid']) || $mybb->user['uid'] > 0)
	    return;

	$uid = (int)$mybb->input['uid'];
	$query = $db->simple_select("users", "secret", "uid={$uid}");
	$secret = $db->fetch_field($query, "secret");
	if(empty($secret))
		return;

	$lang->load("mybb2fa");

	require_once MYBB_ROOT."inc/3rdparty/2fa/GoogleAuthenticator.php";
	require_once MYBB_ROOT."inc/plugins/mybb2fa/AuthWrapper.php";
	$auth = new Authenticator;

	$test = $auth->verifyCode($secret, $mybb->input['code']);

	// No need to block the user anymore, either he failed (logout) or passed (login)
	$db->update_query("sessions", array("mybb2fa_block" => 0), "sid='".$db->escape_string($mybb->cookies['sid'])."'");

	if($test === true)
	{
		// Correct code, unblock the user
		redirect("index.php", $lang->mybb2fa_loggedin);
	}
	else
	{
		// Sorry little guy, you failed; unset everything
		my_unsetcookie("mybbuser");
		my_unsetcookie("sid");
		redirect("index.php", $lang->mybb2fa_failed);
	}
}

function mybb2fa_admin_do_login()
{
	global $mybb, $lang, $admin_options;

	if($mybb->get_input('module') == 'home-preferences')
	{
	    return;
	}

	if(!empty($admin_options['authsecret']) && !empty($admin_options['recovery_codes']))
	{
	    return;
	}

	if($mybb->user['uid'] && $mybb->settings['mybb2fa_forceacp'])
	{
		mybb2fa_lang_load();

		flash_message($lang->mybb2fa_required, 'error');

		admin_redirect('index.php?module=home-preferences');
	}
}

function mybb2fa_lang_load()
{
	global $lang;

	isset($lang->setting_group_mybb2fa) || $lang->load('mybb2fa');
}