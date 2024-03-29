<?php
	require 'info.php';
	
	function catalog_build($action, $settings, $board) {
		global $config;
		
		// Possible values for $action:
		//	- all (rebuild everything, initialization)
		//	- news (news has been updated)
		//	- boards (board list changed)
		//	- post (a reply has been made)
		//	- post-thread (a thread has been made)
		
		$boards = explode(' ', $settings['boards']);
				
		if ($action == 'all') {
			foreach ($boards as $board) {
				$b = new Catalog();
				$b->build($settings, $board);
			}
		} elseif ($action == 'post-thread' || ($settings['update_on_posts'] && $action == 'post') || ($settings['update_on_posts'] && $action == 'post-delete') && in_array($board, $boards)) {
			$b = new Catalog();
			$b->build($settings, $board);
		}
	}
	
	// Wrap functions in a class so they don't interfere with normal Tinyboard operations
	class Catalog {
		public function build($settings, $board_name) {
			global $config, $board;
			
			openBoard($board_name);
			
			$recent_images = array();
			$recent_posts = array();
			$stats = array();
			
                        $query = query(sprintf("SELECT *, `id` AS `thread_id`,
				(SELECT COUNT(`id`) FROM ``posts_%s`` WHERE `thread` = `thread_id`) AS `reply_count`,
				(SELECT SUM(`num_files`) FROM ``posts_%s`` WHERE `thread` = `thread_id` AND `num_files` IS NOT NULL) AS `image_count`,
				'%s' AS `board` FROM ``posts_%s`` WHERE `thread`  IS NULL ORDER BY `bump` DESC",
			$board_name, $board_name, $board_name, $board_name, $board_name)) or error(db_error());
			
			while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
				$post['link'] = $config['root'] . $board['dir'] . $config['dir']['res'] . sprintf($config['file_page'], ($post['thread'] ? $post['thread'] : $post['id']));
				$post['board_name'] = $board['name'];

				if ($post['embed'] && preg_match('/^https?:\/\/(\w+\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9\-_]{10,11})(&.+)?$/i', $post['embed'], $matches)) {
					$post['youtube'] = $matches[2];
				}				

				if (isset($post['files'])) {
					$files = json_decode($post['files']);
					if ($files[0]->file == 'deleted') continue;
					$post['file'] = $config['uri_thumb'] . $files[0]->thumb;
				}

				$recent_posts[] = $post;
			}
			
			$required_scripts = array('js/jquery.min.js', 'js/jquery.mixitup.min.js', 'js/catalog.js');

			foreach($required_scripts as $i => $s) {
				if (!in_array($s, $config['additional_javascript']))
					$config['additional_javascript'][] = $s;
			}

			file_write($config['dir']['home'] . $board_name . '/catalog.html', Element('themes/catalog/catalog.html', Array(
				'settings' => $settings,
				'config' => $config,
				'boardlist' => createBoardlist(),
				'recent_images' => $recent_images,
				'recent_posts' => $recent_posts,
				'stats' => $stats,
				'board' => $board_name,
				'link' => $config['root'] . $board['dir']
			)));
		}
	};
