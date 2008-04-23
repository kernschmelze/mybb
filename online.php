<?php
/**
 * MyBB 1.4
 * Copyright � 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

define("IN_MYBB", 1);

$templatelist = "online,online_row,online_row_ip,online_today,online_today_row,online_iplookup,mostonline";
require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_online.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;
// Load global language phrases
$lang->load("online");

if($mybb->usergroup['canviewonline'] == 0)
{
	error_no_permission();
}

// Make navigation
add_breadcrumb($lang->nav_online, "online.php");

if($mybb->input['action'] == "today")
{
	add_breadcrumb($lang->nav_onlinetoday);

	$plugins->run_hooks("online_today_start");

	$todaycount = 0;
	$stime = TIME_NOW-(60*60*24);
	$todayrows = '';
	$query = $db->query("
		SELECT u.*
		FROM ".TABLE_PREFIX."users u
		LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup)
		WHERE u.lastactive > $stime
		ORDER BY u.lastactive DESC
	");
	while($online = $db->fetch_array($query))
	{
		if($online['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $online['uid'] == $mybb->user['uid'])
		{
			if($online['invisible'] == 1)
			{
				$invisiblemark = "*";
			}
			else
			{
				$invisiblemark = "";
			}
			$username = $online['username'];
			$username = format_name($username, $online['usergroup'], $online['displaygroup']);
			$online['profilelink'] = build_profile_link($username, $online['uid']);
			$onlinetime = my_date($mybb->settings['timeformat'], $online['lastactive']);
			eval("\$todayrows .= \"".$templates->get("online_today_row")."\";");
		}
		++$todaycount;
	}
	if($todaycount == 1)
	{
		$onlinetoday = $lang->member_online_today;
	}
	else
	{
		$onlinetoday = $lang->sprintf($lang->members_were_online_today, $todaycount);
	}

	$plugins->run_hooks("online_today_end");

	eval("\$today = \"".$templates->get("online_today")."\";");
	output_page($today);
}
else
{
	$plugins->run_hooks("online_start");

	// Custom sorting options
	if($mybb->input['sortby'] == "username")
	{
		$sql = "u.username ASC, s.time DESC";
		$refresh_string = "?sortby=username";
	}
	elseif($mybb->input['sortby'] == "location")
	{
		$sql = "s.location, s.time DESC";
		$refresh_string = "?sortby=location";
	}
	// Otherwise sort by last refresh
	else
	{
		$sql = "s.time DESC";
		$refresh_string = '';
	}
	
	$timesearch = TIME_NOW - $mybb->settings['wolcutoffmins']*60;

	// Exactly how many users are currently online?
	$query = $db->simple_select("sessions", "COUNT(DISTINCT sid) as online", "time > {$timesearch}");
	$online_count = $db->fetch_field($query, "online");
	
	// How many pages are there?
	$perpage = $mybb->settings['threadsperpage'];

	if(intval($mybb->input['page']) > 0)
	{
		$page = intval($mybb->input['page']);
		$start = ($page-1) * $perpage;
		$pages = ceil($online_count / $perpage);
		if($page > $pages)
		{
			$start = 0;
			$page = 1;
		}
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	// Assemble page URL
	$multipage = multipage($online_count, $perpage, $page, "online.php".$refresh_string);	

	// Query for active sessions
	$query = $db->query("
		SELECT DISTINCT s.sid, s.ip, s.uid, s.time, s.location, u.username, s.nopermission, u.invisible, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."sessions s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.time>'$timesearch'
		ORDER BY $sql
		LIMIT {$start}, {$perpage}
	");

	$user_count = 0;
	$guest_count = 0;
	$invisible_count = 0;

	// Fetch spiders
	$spiders = $cache->read("spiders");

	while($user = $db->fetch_array($query))
	{
		$plugins->run_hooks("online_user");

		// Fetch the WOL activity
		$user['activity'] = fetch_wol_activity($user['location']);

		$botkey = my_strtolower(str_replace("bot=", '', $user['sid']));

		// Have a registered user
		if($user['uid'] > 0)
		{
			if($users[$user['uid']]['time'] < $user['time'] || !$users[$user['uid']])
			{
				if($user['invisible'] == 1)
				{
					++$invisible_count;
				}
				++$user_count;
				$users[$user['uid']] = $user;
			}
		}
		// Otherwise this session is a bot
		else if(my_strpos($user['sid'], "bot=") !== false && $spiders[$botkey])
		{
			$user['bot'] = $spiders[$botkey]['name'];
			$user['usergroup'] = $spiders[$botkey]['usergroup'];
			$guests[] = $user;
			++$botcount;
		}
		// Or a guest
		else
		{
			$guests[] = $user;
			++$guest_count;
		}
	}

	// Now we build the actual online rows - we do this separately because we need to query all of the specific activity and location information
	$online_rows = '';
	if(is_array($users))
	{
		reset($users);
		foreach($users as $user)
		{
			$online_rows .= build_wol_row($user);
		}
	}
	if(is_array($guests))
	{
		reset($guests);
		foreach($guests as $user)
		{
			$online_rows .= build_wol_row($user);
		}
	}

	$online_count = $user_count + $guest_count;

	// Fetch the most online information
	$most_online = $cache->read("mostonline");
	$record_count = $most_online['numusers'];
	$record_date = my_date($mybb->settings['dateformat'], $most_online['time']);
	$record_time = my_date($mybb->settings['timeformat'], $most_online['time']);

	// Set automatic refreshing if enabled
	if($mybb->settings['refreshwol'] > 0)
	{
		$refresh_time = $mybb->settings['refreshwol'] * 60;
		$refresh = "<meta http-equiv=\"refresh\" content=\"{$refresh_time};URL=online.php{$refresh_string}\" />";
	}

	// Fetch language strings depending on counts being plural or singular
	if($online_count != 1)
	{
		$total_bit = $lang->online_online_plural;
	}
	else
	{
		$total_bit = $lang->online_online_singular;
	}
	if($user_count != 1)
	{
		$user_bit = $lang->online_member_plural;
	}
	else
	{
		$user_bit = $lang->online_member_singular;
	}
	if($invisible_count != 1)
	{
		$invisible_bit = $lang->online_anon_plural;
	}
	else
	{
		$invisible_bit = $lang->online_anon_singular;
	}
	if($guest_count != 1)
	{
		$guest_bit = $lang->online_guest_plural;
	}
	else
	{
		$guest_bit = $lang->online_guest_singular;
	}
	$lang->online_count = $lang->sprintf($lang->online_count, my_number_format($online_count), $total_bit, $mybb->settings['wolcutoffmins'], my_number_format($user_count), $user_bit, my_number_format($invisible_count), $invisible_bit, my_number_format($guest_count), $guest_bit);

	$plugins->run_hooks("online_end");

	eval("\$online = \"".$templates->get("online")."\";");
	output_page($online);
}
?>