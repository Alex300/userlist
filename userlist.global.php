<?php
/* ====================
[BEGIN_COT_EXT]
Hooks=global
[END_COT_EXT]
==================== */

defined('COT_CODE') or die('Wrong URL');

require_once cot_incfile('users', 'module');

/**
 * Generates page list widget
 * @param string            $tpl        Template code
 * @param string            $condition  Custom selection filter (SQL)
 * @param int               $limit
 * @param string            $order      Sorting order (SQL)
 * @param string|int|array  $group  Groups list, semicolon separated or array
 * @param string|int|array  $blackGrpLlist
 * @param string|int|array  $whiteGrpList
 * @param string            $pagination Pagination parameter name for the URL, e.g. 'pld'. Make sure it does
 *                               not conflict with other paginations.
 * @return string              Parsed HTML
 */
function userlist($tpl = 'userlist', $condition = '', $limit = 0, $order = '',  $group = '', $blackGrpLlist = '1;2;3',
                  $whiteGrpList = '', $pagination = 'ulp'){
	global $db, $db_users, $env;

    $where = array();
    $join_columns = '';
    $join_condition = '';

    if (!empty($condition)) $where['cond'] = $condition;

	// Compile lists
	if (!empty($blackGrpLlist)){
        if (!is_array($blackGrpLlist)) $blackGrpLlist = explode(';', $blackGrpLlist);
        foreach($blackGrpLlist as $key => $val){
            $blackGrpLlist[$key] = intval(trim($blackGrpLlist[$key]));
            if ($blackGrpLlist[$key] == 0) unset($blackGrpLlist[$key]);
        }
	}
	if (!empty($whitelist)){
		$wl = explode(';', $whitelist);
	}

    if (!empty($group)){
        if (!is_array($group)) $group = explode(';', $group);
        foreach($group as $key => $val){
            $group[$key] = intval(trim($group[$key]));
            if ($group[$key] == 0) unset($group[$key]);
        }
        if (!empty($blackGrpLlist)) $group = array_diff($group, $blackGrpLlist);
    }
    // Подводим итого группам
    if (!empty($blackGrpLlist)) $where['blk_grp'] = "u.user_maingrp NOT IN (".implode(',', $blackGrpLlist).")";
    if (!empty($group)) $where['grp'] = "u.user_maingrp IN (".implode(',', $group).")";

	// Get pagination number if necessary
	if (!empty($pagination)){
		list($pg, $d, $durl) = cot_import_pagenav($pagination, $limit);
	}else{
		$d = 0;
	}

	// Display the items
	$t = new XTemplate(cot_tplfile($tpl, 'plug'));

	/* === Hook === */
	foreach (array_merge(cot_getextplugins('userlist.query')) as $pl)
	{
		include $pl;
	}
	/* ===== */

    $sql_order = empty($order) ? '' : "ORDER BY $order";
    $sql_limit = ($limit > 0) ? "LIMIT $d, $limit" : '';

    $where = (count($where) > 0) ? "WHERE ".implode(' AND ', $where) : '';

    // Todo implement $totalitems
    $totalitems = 0;

    $sqlusers = $db->query(
        "SELECT u.* $join_columns FROM $db_users AS u $join_condition
	      $where $sql_order $sql_limit"
    );

	$jj = 1;
	while ($row = $sqlusers->fetch()){
		$t->assign(cot_generate_usertags($row, 'USER_ROW_'));

		$t->assign(array(
			'USER_ROW_NUM'     => $jj,
			'USER_ROW_ODDEVEN' => cot_build_oddeven($jj),
            'USER_ROW_GENDER_RAW' => $row['user_gender'],
			'USER_ROW_RAW'     => $row
		));

		/* === Hook === */
		foreach (cot_getextplugins('userlist.loop') as $pl)
		{
			include $pl;
		}
		/* ===== */

		$t->parse("MAIN.USER_ROW");
		$jj++;
	}

	// Render pagination
	$url_area = defined('COT_PLUG') ? 'plug' : $env['ext'];
	$url_params[$pagination] = $durl;
	$pagenav = cot_pagenav($url_area, $url_params, $d, $totalitems, $limit, $pagination);

	$t->assign(array(
		'USER_TOP_PAGINATION'  => $pagenav['main'],
		'USER_TOP_PAGEPREV'    => $pagenav['prev'],
		'USER_TOP_PAGENEXT'    => $pagenav['next'],
		'USER_TOP_FIRST'       => isset($pagenav['first']) ? $pagenav['first'] : '',
		'USER_TOP_LAST'        => $pagenav['last'],
		'USER_TOP_CURRENTPAGE' => $pagenav['current'],
		'USER_TOP_TOTALLINES'  => $totalitems,
		'USER_TOP_MAXPERPAGE'  => $limit,
		'USER_TOP_TOTALPAGES'  => $pagenav['total']
	));

	/* === Hook === */
	foreach (cot_getextplugins('userlist.tags') as $pl)
	{
		include $pl;
	}
	/* ===== */

	$t->parse();
	return $t->text();
}
