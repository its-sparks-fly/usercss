<?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

$plugins->add_hook("usercp_start", "usercss_usercp_start");

function usercss_info()
{
	global $lang;
	$lang->load('usercss');
	
	return array(
		"name"			=> $lang->usercss_name,
		"description"	=> $lang->usercss_description,
		"website"		=> "https://github.com/its-sparks-fly",
		"author"		=> "sparks fly",
		"authorsite"	=> "https://sparks-fly.info",
		"version"		=> "1.0",
		"compatibility" => "18*"
	);
}

function usercss_install()
{
    global $db, $lang;
	
	$setting_group = [
		'name' => 'usercss',
		'title' => $lang->usercss_settings,
		'description' => $lang->usercss_settings_description,
		'disporder' => 5,
		'isdefault' => 0
	];

	$gid = $db->insert_query("settinggroups", $setting_group);
	
	$setting_array = [
		'usercss_fid' => [
			'title' => $lang->usercss_field,
			'description' => $lang->usercss_field_description,
			'optionscode' => 'text',
			'value' => '1', // Default
			'disporder' => 1
		]
	];

	foreach($setting_array as $name => $setting)
	{
		$setting['name'] = $name;
		$setting['gid'] = $gid;

		$db->insert_query('settings', $setting);
	}

    rebuild_settings();
    
    $css = array(
        'name' => 'usercss.css',
        'tid' => 1,
        "stylesheet" => '',
        'cachefile' => $db->escape_string(str_replace('/', '', "usercss.css")),
        'lastmodified' => time()
    );

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    $sid = $db->insert_query("themestylesheets", $css);
    $db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=".$sid), "sid = '".$sid."'", 1);

    $tids = $db->simple_select("themes", "tid");
    while($theme = $db->fetch_array($tids)) {
        update_theme_stylesheet_list($theme['tid']);
    }

}

function usercss_is_installed()
{
	global $mybb;
	if(isset($mybb->settings['usercss_fid']))
	{
		return true;
	}

	return false;
}

function usercss_uninstall()
{
	global $db;

	$db->delete_query('settings', "name IN ('usercss_fid')");
	$db->delete_query('settinggroups', "name = 'usercss'");

    rebuild_settings();
    
    // drop css
    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    $db->delete_query("themestylesheets", "name = 'usercss.css'");
    $query = $db->simple_select("themes", "tid");
    while($theme = $db->fetch_array($query)) {
        update_theme_stylesheet_list($theme['tid']);
    }

}

function usercss_activate()
{
	global $db;
	
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("usercp_nav_editsignature", "#".preg_quote('</div>')."#i", '</div> {$usercss_nav}');

	$insert_array = array(
		'title'		=> 'usercss_usercp',
		'template'	=> $db->escape_string('<html>
        <head>
        <title>{$lang->user_cp} - {$lang->usercss}</title>
        {$headerinclude}
        </head>
        <body>
        {$header}
        <table width="100%" border="0" align="center">
        <tr>
        {$usercpnav}
        <td valign="top">
            <form method="post" action="usercp.php?action=do_usercss">
        <table border="0" cellspacing="2" cellpadding="10" class="tborder">
        <tr>
        <td class="thead" colspan="2"><strong>{$lang->usercss}</strong></td>
        </tr>
            {$style_bit}
        <tr>
        <td class="trow2" colspan="2" align="center">
            <input type="submit" class="button" value="{$lang->usercss_submit}" \>
        </td>
        </tr>
        </table>
            </form>
        </table>
        {$footer}
        </body>
        </html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);
	
	$insert_array = array(
		'title'		=> 'usercss_usercp_bit',
		'template'	=> $db->escape_string('<tr>
        <td width="30%" style="background: {$matches[1]};" align="center">
            <div style="padding: 10px; font-size: 10px;">
                <strong style="color: {$matching[1]};">{$style[\'name\']}</strong>
            </div>
        </td>
        <td align="center" class="trow1">
            <input type="text" value="{$matching[1]}" name="css_{$style[\'tid\']}" class="textfield" maxlength="7" \>
        </td>
    </tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
    $db->insert_query("templates", $insert_array);
    
	$insert_array = array(
		'title'		=> 'usercss_usercp_nav',
		'template'	=> $db->escape_string('<div><a href="usercp.php?action=usercss" class="usercp_nav_item usercp_nav_editsig">{$lang->usercss}</a></div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

}

function usercss_deactivate()
{
	global $db;

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets("usercp_nav_editsignature", "#".preg_quote('{$usercss_nav}')."#i", '', 0);

    $db->delete_query("templates", "title LIKE '%usercss%'");
}

function usercss_usercp_start() {
    global $db, $lang, $mybb, $templates, $theme, $header, $headerinclude, $footer, $usercpnav, $usercss_nav;
    $lang->load('usercss');
    $page = "";

    eval("\$usercss_nav = \"".$templates->get("usercss_usercp_nav")."\";");

    if($mybb->get_input('action') == "usercss") {
        $trowpattern = "/.*trow1.*\\n\\tbackground: (.*);/";

        $query = $db->simple_select("themes", "tid,name", "allowedgroups = 'all' AND name != 'MyBB Master Style'", [ "order_by" => 'name', "order_dir" => 'ASC' ]);
        while($style = $db->fetch_array($query)) {
            $matching[1] = "#";
            $globalcss = $db->fetch_field($db->simple_select("themestylesheets", "stylesheet", "tid='{$style['tid']}' AND name ='global.css'"), "stylesheet");   
            preg_match($trowpattern, $globalcss, $matches);

            $field = $mybb->user['fid'.$mybb->settings['usercss_fid']];
            $fieldpattern = "/{$field}.*color: (.*);/";
            $usercss = $db->fetch_field($db->simple_select("themestylesheets", "stylesheet", "tid='{$style['tid']}' AND name = 'usercss.css'"), "stylesheet");   
            preg_match($fieldpattern, $usercss, $matching);

            eval("\$style_bit .= \"".$templates->get("usercss_usercp_bit")."\";");
        }
        eval("\$page = \"".$templates->get("usercss_usercp")."\";");
        output_page($page);
    }

    if($mybb->get_input('action') == "do_usercss") {
        $fid = $mybb->settings['usercss_fid'];
        $uid = $mybb->user['uid'];
        $fid1 = "fid".$fid;

        // select field content
        $field = $db->fetch_field($db->simple_select("userfields", $fid1, "ufid = '{$uid}'"), $fid1);
        $query = $db->simple_select("themes", "tid", "allowedgroups = 'all' AND name != 'MyBB Master Style'", [ "order_by" => 'name', "order_dir" => 'ASC' ]);
        while($style = $db->fetch_array($query)) {
                $usercss = "";
                if(!empty($mybb->get_input('css_'.$style['tid']))) {
                
                $usercss = $db->fetch_field($db->simple_select("themestylesheets", "stylesheet", "tid='{$style['tid']}' AND name = 'usercss.css'"), "stylesheet");   
                
                // stylesheet not available yet? create it...
                if(empty($usercss)) {
                    $neweststylesheet = $db->fetch_field($db->simple_select("themestylesheets", "sid", "", ["order_by" => "sid", "order_dir" => "DESC", "limit" => "1"]), "sid");
                    $neweststylesheet++;
                    $new_record = [
                        "tid" => $style['tid'],
                        "name" => "usercss.css",
                        "cachefile" => "css.php?stylesheet=".$neweststylesheet,
                        "lastmodified" => time()
                    ];
                    $db->insert_query("themestylesheets", $new_record);
                }
                if(!preg_match("/$field/i", $usercss)) {
                    $usercss = $usercss . $field . " { font-weight: bold; color: " . $mybb->get_input('css_'.$style['tid']) . "; }" . "\n";
                } else {
                    $searchpattern = "/{$field}.*color: .*;/";
                    $replacement = "{$field} { font-weight: bold; color: {$mybb->get_input('css_'.$style['tid'])};";
                    $usercss = preg_replace($searchpattern, $replacement, $usercss);
                }

                $new_record = [
                    "stylesheet" => $usercss
                ];

                $db->update_query("themestylesheets", $new_record, "tid = '{$style['tid']}' AND name = 'usercss.css'");
            }
        }
        redirect("usercp.php?action=usercss", $lang->usercss_redirect);
    }
}

?>