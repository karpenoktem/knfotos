<?php
	require('header.php');

	$limit = $thumbs_per_row * $rows_of_thumbs;
	$page = (isset($_GET['page']) ? intval($_GET['page']) : 0);
	$offset = $limit * $page;

	if(isset($_GET['album'])) {
		$album = $_GET['album'];
	} else {
		$album = '/';
	}
	if(substr($album, -1) != '/') {
		$album .= '/';
	}
	$visibility = false;
	if($album == '/') {
		$album = '';
		$order = 'id DESC';
		$humanname = 'Fotoalbum';
		$displayname = $humanname;
		$parentalbums = array();
	} else {
		$row = getUnitByFullPath(substr($album, 0, -1));
		if(!$row) {
			header('HTTP/1.1 404 Not Found');
			die('Album not found');
		}

		$parentalbums = loadPathAlbums($row['path']);

		if(!in_array($row['visibility'], getVisibleVisibilities()) || !isPathVisible($parentalbums)) {
			header('HTTP/1.1 403 Access denied');
			die('Access denied');
		}

		$id = $row['id'];
		$visibility = $row['visibility'];
		$humanname = $row['humanname'];
		$displayname = $humanname ?: $row['name'];
		$order = 'name';
	}

	$extra_params = '';
	if(!empty($_GET['search_album'])) {
		// Zoeken op naam album
		$keyword = trim($_GET['search_album']);
		$res = sql_query("SELECT SQL_CALC_FOUND_ROWS 'album'
			AS type, fa_albums.*
			FROM fa_albums
			WHERE path LIKE %s
				AND visibility IN (%S)
				AND (name LIKE %s
					OR humanname LIKE %s)
			ORDER BY ". $order ."
			LIMIT %i, %i",
				$album.'%', getVisibleVisibilities(),
				'%'.$keyword.'%', '%'.$keyword.'%',
				$offset, $limit);
		$extra_params .= '&search_album='. $keyword;
	} elseif(!empty($_GET['search_tag'])) {
		// Zoeken op tags
		$keyword = trim($_GET['search_tag']);
		$res = sql_query("SELECT SQL_CALC_FOUND_ROWS 'photo' AS type, fa_photos.*
			FROM fa_photos, fa_tags
			WHERE fa_tags.username = %s
				AND fa_tags.photo_id = fa_photos.id
				AND fa_photos.path LIKE %s
				AND fa_photos.visibility IN (%S)
			ORDER BY ". $order ."
			LIMIT %i, %i",
			$keyword, $album.'%', getVisibleVisibilities(),
			$offset, $limit);
		$extra_params .= '&search_tag='. $keyword;
	} else {
		$keyword = '';
		$res = sql_query("
			(SELECT SQL_CALC_FOUND_ROWS
				'photo' as `type`, `id`, name,
				path, visibility, name as humanname
			FROM fa_photos WHERE path=%s AND visibility IN (%S))
			UNION (SELECT
				'album' as `type`, `id`, name,
				path, visibility, humanname
			FROM fa_albums WHERE path=%s AND visibility IN (%S))
			ORDER BY ". $order ."
			LIMIT %i, %i",
			$album, getVisibleVisibilities(),
			$album, getVisibleVisibilities(), $offset, $limit);
	}

	$albums = array();
	$photos = array();
	while($row = mysql_fetch_assoc($res)) {
		if($row['type'] == 'album') {
			$albums[] = array(
				'name' => $row['name'],
				'fullpath' => $row['path'] . $row['name'],
				'displayname' => $row['humanname'] ?: $row['name'],
			);
		} else {
			$photos[] = array(
				'name' => $row['name'],
				'fullpath' => $row['path'] . $row['name'],
			);
		}
	}

	$res = sql_query('SELECT FOUND_ROWS()');
	$row = mysql_fetch_row($res);
	$last = floor($row[0] / $limit);

	$mode = 'index';
	template_assign('mode');

	template_assign('albums');
	template_assign('photos');
	if($album != '') {
		$parentalbum = dirname($album);
		if($parentalbum == '.') {
			$parentalbum = '';
		}
		template_assign('parentalbum');
	}
	template_assign('parentalbums');
	template_assign('title', $displayname);	// XXX
	template_assign('id');
	template_assign('visibility');
	template_assign('humanname');
	template_assign('displayname');

	// Paging
	$url = './?album='. urlencode($album) . $extra_params;
	template_assign('url');
	template_assign('page');
	template_assign('last');

	show_template('index.tpl');
	require('footer.php');
?>
