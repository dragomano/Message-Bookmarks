<?php

/**
 * Class-MessageBookmarks.php
 *
 * @package Message Bookmarks
 * @link https://dragomano.ru/mods/message-bookmarks
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2013-2023 Bugo
 * @license https://opensource.org/licenses/MIT MIT
 *
 * @version 0.9.4
 */

if (!defined('SMF'))
	die('No direct access...');

final class MessageBookmarks
{
	public function hooks()
	{
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme#', false, __FILE__);
		add_integration_function('integrate_actions', __CLASS__ . '::actions#', false, __FILE__);
		add_integration_function('integrate_load_illegal_guest_permissions', __CLASS__ . '::loadIllegalGuestPermissions#', false, __FILE__);
		add_integration_function('integrate_load_permissions', __CLASS__ . '::loadPermissions#', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas#', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch#', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications#', false, __FILE__);
		add_integration_function('integrate_query_message', __CLASS__ . '::queryMessage#', false, __FILE__);
		add_integration_function('integrate_prepare_display_context', __CLASS__ . '::prepareDisplayContext#', false, __FILE__);
		add_integration_function('integrate_profile_areas', __CLASS__ . '::profileAreas#', false, __FILE__);
		add_integration_function('integrate_profile_popup', __CLASS__ . '::profilePopup#', false, __FILE__);
		add_integration_function('integrate_delete_members', __CLASS__ . '::deleteMembers#', false, __FILE__);
		add_integration_function('integrate_remove_message', __CLASS__ . '::removeMessage#', false, __FILE__);
		add_integration_function('integrate_remove_topics', __CLASS__ . '::removeTopics#', false, __FILE__);
		add_integration_function('integrate_forum_stats', __CLASS__ . '::forumStats#', false, __FILE__);
	}

	/**
	 * @hook integrate_load_theme
	 */
	public function loadTheme()
	{
		loadLanguage('MessageBookmarks/');
	}

	/**
	 * @hook integrate_actions
	 */
	public function actions(array &$actionArray)
	{
		$actionArray['mb'] = [false, [$this, 'init']];
	}

	/**
	 * @return mixed|void
	 */
	public function init()
	{
		isAllowedTo('use_message_bookmarks');

		$subActions = [
			'add'  => [__CLASS__, 'addBookmark'],
			'edit' => [__CLASS__, 'editBookmark'],
			'del'  => [__CLASS__, 'delBookmark']
		];

		if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) && !isset($_REQUEST['xml']))
			return call_user_func($subActions[$_REQUEST['sa']]);
	}

	/**
	 * @hook integrate_load_illegal_guest_permissions
	 */
	public function loadIllegalGuestPermissions()
	{
		global $context;

		$context['non_guest_permissions'][] = 'use_message_bookmarks';
	}

	/**
	 * @hook integrate_load_permissions
	 */
	public function loadPermissions(array &$permissionGroups, array &$permissionList)
	{
		$permissionGroups['membergroup']['simple']  = ['message_bookmarks'];
		$permissionGroups['membergroup']['classic'] = ['message_bookmarks'];

		$permissionList['membergroup']['use_message_bookmarks']  = [false, 'message_bookmarks', 'message_bookmarks'];
	}

	/**
	 * @hook integrate_admin_areas
	 */
	public function adminAreas(array &$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['mb'] = [$txt['mb_settings']];
	}

	public function adminSearch(array &$language_files, array &$include_files, array &$settings_search)
	{
		$settings_search[] = [[$this, 'settings'], 'area=modsettings;sa=mb'];
	}

	/**
	 * @hook integrate_modify_modifications
	 */
	public function modifyModifications(array &$subActions)
	{
		$subActions['mb'] = [$this, 'settings'];
	}

	/**
	 * @return array|void
	 */
	public function settings(bool $return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings, $smcFunc;

		$context['page_title'] = $context['settings_title'] = $txt['mb_settings'];
		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=mb';

		$context['permissions_excluded'] = [-1];

		$txt['select_boards_from_list'] = $txt['mb_ignored_boards_desc'];

		$addSettings = [];
		if (!isset($modSettings['mb_class']))
			$addSettings['mb_class'] = 'sticky';
		if (empty($modSettings['mb_add_icon']))
			$addSettings['mb_add_icon'] = '&#128154;';
		if (empty($modSettings['mb_del_icon']))
			$addSettings['mb_del_icon'] = '&#128148;';
		if (!isset($modSettings['mb_show_top_messages_count']))
			$addSettings['mb_show_top_messages_count'] = 10;
		if (!isset($modSettings['mb_show_top_members_count']))
			$addSettings['mb_show_top_members_count'] = 10;
		updateSettings($addSettings);

		$config_vars = [
			['boards', 'mb_ignore_boards'],
			['text', 'mb_class'],
			[
				'text',
				'mb_add_icon',
				'subtext' => $txt['mb_icon_subtext'],
				'value' => un_htmlspecialchars($modSettings['mb_add_icon'] ?? ''),
				'postinput' => strpos($modSettings['mb_add_icon'], ' ') !== false ? '<i class="' . $modSettings['mb_add_icon'] . '"></i>' : ''
			],
			[
				'text',
				'mb_del_icon',
				'subtext' => $txt['mb_icon_subtext'],
				'value' => un_htmlspecialchars($modSettings['mb_del_icon'] ?? ''),
				'postinput' => strpos($modSettings['mb_del_icon'], ' ') !== false ? '<i class="' . $modSettings['mb_del_icon'] . '"></i>' : ''
			],
			['permissions', 'use_message_bookmarks'],
			['title', 'spider_stats'],
			['check', 'mb_show_top_messages_stats'],
			['int', 'mb_show_top_messages_count'],
			['check', 'mb_show_top_members_stats'],
			['int', 'mb_show_top_members_count'],
		];

		if ($return_config)
			return $config_vars;

		$context[$context['admin_menu_name']]['tab_data']['description'] = $txt['mb_mod_desc'];

		if (isset($_GET['save'])) {
			checkSession();

			if (isset($_POST['mb_add_icon']))
				$_POST['mb_add_icon'] = $smcFunc['htmlspecialchars']($_POST['mb_add_icon']);

			if (isset($_POST['mb_del_icon']))
				$_POST['mb_del_icon'] = $smcFunc['htmlspecialchars']($_POST['mb_del_icon']);

			saveDBSettings($config_vars);
			redirectexit('action=admin;area=modsettings;sa=mb');
		}

		prepareDBSettingContext($config_vars);
	}

	/**
	 * @hook integrate_query_message
	 */
	public function queryMessage(array &$msg_selects, array &$msg_tables, array &$msg_parameters)
	{
		global $user_info;

		if (empty(allowedTo('use_message_bookmarks')) || $this->isIgnoredBoard())
			return;

		$msg_selects[] = 'mb.bookmark_id';
		$msg_tables[] = 'LEFT JOIN {db_prefix}message_bookmarks AS mb ON (mb.msg_id = id_msg AND mb.user_id = {int:current_user})';
		$msg_parameters['current_user'] = $user_info['id'];
	}

	/**
	 * @hook integrate_prepare_display_context
	 */
	public function prepareDisplayContext(array &$output, array $message)
	{
		global $context, $modSettings, $txt, $scripturl;

		if (empty(allowedTo('use_message_bookmarks')) || empty($context['user']['id']) || $this->isIgnoredBoard())
			return;

		$add_label = empty($modSettings['mb_add_icon']) ? '&#128154;' : (strpos($modSettings['mb_add_icon'], ' ') !== false ? ('<i class="' . $modSettings['mb_add_icon'] . '"></i>') : un_htmlspecialchars($modSettings['mb_add_icon']));
		$del_label = empty($modSettings['mb_del_icon']) ? '&#128148;' : (strpos($modSettings['mb_del_icon'], ' ') !== false ? ('<i class="' . $modSettings['mb_del_icon'] . '"></i>') : un_htmlspecialchars($modSettings['mb_del_icon']));

		$buttons = array(
			'mb_add' => array(
				'label' => $add_label,
				'javascript' => ' title="' . $txt['mb_add_bookmark'] . '"',
				'href' => $scripturl . '?action=mb;sa=add;topic=' . $context['current_topic'] . ';msg=' . $output['id'],
				'show' => empty($message['bookmark_id'])
			),
			'mb_remove' => array(
				'label' => $del_label,
				'javascript' => ' title="' . $txt['mb_remove_bookmark'] . '"',
				'href' => $scripturl . '?action=mb;sa=del;item=' . $message['bookmark_id'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'show' => !empty($message['bookmark_id'])
			)
		);

		$output['quickbuttons'] = array_merge($buttons, $output['quickbuttons']);

		if (!empty($message['bookmark_id']) && !empty($modSettings['mb_class'])) {
			$output['css_class'] .= ' ' . $modSettings['mb_class'];
		}
	}

	/**
	 * @hook integrate_profile_areas
	 */
	public function profileAreas(array &$profile_areas)
	{
		global $txt, $context;

		$profile_areas['info']['areas']['bookmarks'] = array(
			'label'      => $txt['mb_show_bookmarks'],
			'function'   => __CLASS__ . '::showBookmarks#',
			'icon'       => 'sticky',
			'enabled'    => $context['user']['is_owner'],
			'permission' => [
				'own' => 'use_message_bookmarks'
			]
		);
	}

	public function showBookmarks(int $memID)
	{
		global $context, $txt, $user_profile, $scripturl, $sourcedir;

		$context['current_member'] = $memID;

		$context['page_title'] = $txt['mb_profile_title'] . ' - ' . $user_profile[$memID]['real_name'];

		$context[$context['profile_menu_name']]['tab_data'] = [
			'title'       => $txt['mb_show_bookmarks'],
			'description' => $txt['mb_profile_desc'],
			'icon_class'  => 'main_icons sticky icon'
		];

		$listOptions = array(
			'id' => 'message_bookmarks',
			'items_per_page' => 30,
			'title' => $txt['mb_settings'],
			'no_items_label' => $txt['mb_no_items'],
			'base_href' => $scripturl . '?action=profile;area=bookmarks;u=' . $context['current_member'],
			'default_sort_col' => 'title',
			'get_items' => [
				'function' => [$this, 'getAll']
			],
			'get_count' => [
				'function' => [$this, 'getTotalCount']
			],
			'columns' => array(
				'id' => array(
					'header' => array(
						'value' => '#',
						'style' => 'width: 8%'
					),
					'data' => array(
						'db'    => 'id',
						'class' => 'centertext'
					),
					'sort' => array(
						'default' => 'mb.bookmark_id DESC',
						'reverse' => 'mb.bookmark_id'
					)
				),
				'date' => array(
					'header' => array(
						'value' => $txt['date']
					),
					'data' => array(
						'db'    => 'date',
						'class' => 'centertext'
					),
					'sort' => array(
						'default' => 'mb.created_at DESC',
						'reverse' => 'mb.created_at'
					)
				),
				'title' => array(
					'header' => array(
						'value' => $txt['title']
					),
					'data' => array(
						'function' => function ($entry) use ($scripturl) {
							return '<a href="' . $scripturl . '?msg=' . $entry['msg'] . '">' . $entry['title'] . '</a>' . ($entry['note'] ? '<br><details><p>' . $entry['note'] . '</p></details>' : '');
						},
					),
					'sort' => array(
						'default' => 'mb.bookmark_title DESC',
						'reverse' => 'mb.bookmark_title'
					)
				),
				'board' => array(
					'header' => array(
						'value' => $txt['board']
					),
					'data' => array(
						'function' => function ($entry) use ($scripturl) {
							return '<a href="' . $scripturl . '?board=' . $entry['board_id'] . '.0">' . $entry['board_name'] . '</a>';
						}
					),
					'sort' => array(
						'default' => 'name DESC',
						'reverse' => 'name'
					)
				),
				'actions' => array(
					'header' => array(
						'value' => $txt['profileAction']
					),
					'data' => array(
						'function' => function ($entry) use ($scripturl, $txt, $context) {
							return $entry['can_manage'] ? '
							<a
								class="button"
								href="' . $scripturl . '?action=mb;sa=edit;item=' . $entry['id'] . '"
								title="' . $txt['modify'] . '"
							>
								<span class="main_icons modify_button"></span>
							</a>
							<a
								class="button"
								href="' . $scripturl . '?action=mb;sa=del;item=' . $entry['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '"
								title="' . $txt['remove'] . '"
							>
								<span class="main_icons remove_button"></span>
							</a>' : '';
						},
						'class' => 'centertext'
					)
				)
			)
		);

		require_once($sourcedir . '/Subs-List.php');
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'message_bookmarks';
	}

	public function getAll(int $start, int $items_per_page, string $sort): array
	{
		global $smcFunc, $context, $txt;

		$request = $smcFunc['db_query']('', '
			SELECT mb.bookmark_id, mb.msg_id, mb.bookmark_title, mb.bookmark_note, mb.user_id, mb.created_at, b.id_board, b.name AS name
			FROM {db_prefix}message_bookmarks AS mb
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = mb.topic_id)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE mb.user_id = {int:user_id}
				AND t.approved = {int:approved}
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:limit}',
			[
				'user_id'  => $context['current_member'],
				'approved' => 1,
				'sort'     => $sort,
				'start'    => $start,
				'limit'    => $items_per_page
			]
		);

		$items = [];
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			censorText($row['bookmark_note']);

			$items[] = [
				'id'         => $row['bookmark_id'],
				'date'       => $row['created_at'] ? timeformat($row['created_at']) : $txt['not_applicable'],
				'msg'        => $row['msg_id'],
				'title'      => $row['bookmark_title'],
				'note'       => parse_bbc($row['bookmark_note']),
				'board_name' => $row['name'],
				'board_id'   => $row['id_board'],
				'can_manage' => $context['user']['id'] == $row['user_id']
			];
		}

		$smcFunc['db_free_result']($request);

		return $items;
	}

	public function getTotalCount(): int
	{
		global $smcFunc, $context;

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(mb.bookmark_id)
			FROM {db_prefix}message_bookmarks AS mb
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = mb.topic_id)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE mb.user_id = {int:user_id}
				AND t.approved = {int:approved}',
			[
				'user_id'  => $context['current_member'],
				'approved' => 1
			]
		);

		[$num_entries] = $smcFunc['db_fetch_row']($request);

		$smcFunc['db_free_result']($request);

		return (int) $num_entries;
	}

	/**
	 * @hook integrate_profile_popup
	 */
	public function profilePopup(array &$profile_items)
	{
		global $txt;

		$counter = 0;
		foreach ($profile_items as $item) {
			$counter++;

			if ($item['area'] === 'showdrafts')
				break;
		}

		$profile_items = array_merge(
			array_slice($profile_items, 0, $counter, true),
			[
				[
					'menu'  => 'info',
					'area'  => 'bookmarks',
					'title' => $txt['mb_popup_bookmarks']
				]
			],
			array_slice($profile_items, $counter, null, true)
		);
	}

	/**
	 * @hook integrate_delete_members
	 */
	public function deleteMembers(array $users)
	{
		global $smcFunc;

		if (empty($users))
			return;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}message_bookmarks
			WHERE user_id IN ({array_int:users})',
			[
				'users' => $users
			]
		);

		clean_cache();
	}

	/**
	 * @hook integrate_remove_message
	 */
	public function removeMessage(int $message)
	{
		global $smcFunc;

		if (empty($message))
			return;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}message_bookmarks
			WHERE msg_id = {int:id_msg}',
			[
				'id_msg' => $message
			]
		);

		clean_cache();
	}

	/**
	 * @hook integrate_remove_topics
	 */
	public function removeTopics(array $topics)
	{
		global $smcFunc;

		if (empty($topics))
			return;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}message_bookmarks
			WHERE topic_id IN ({array_int:topics})',
			[
				'topics' => $topics
			]
		);

		clean_cache();
	}

	public function forumStats()
	{
		global $context, $modSettings, $smcFunc, $scripturl;

		if ($context['current_action'] !== 'stats' || empty(allowedTo('use_message_bookmarks')))
			return;

		// Most frequently bookmarked posts
		if (!empty($modSettings['mb_show_top_messages_stats']) && ($context['stats_blocks']['replies'] = cache_get_data('stats_top_mb_messages', 3600)) == null) {
			$result = $smcFunc['db_query']('', '
				SELECT m.id_msg, m.subject, COUNT(mb.msg_id) AS num_items
				FROM {db_prefix}message_bookmarks AS mb
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = mb.msg_id AND m.approved = {int:is_approved})
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				WHERE {query_see_board}
				GROUP BY m.id_msg, m.subject, mb.msg_id
				ORDER BY num_items DESC
				LIMIT 10',
				[
					'is_approved' => 1
				]
			);

			if ($smcFunc['db_num_rows']($result) > 0) {
				$max_items = 1;

				$context['stats_blocks']['replies'] = [];
				while ($row = $smcFunc['db_fetch_assoc']($result)) {
					if ($row['num_items'] < (empty($modSettings['mb_show_top_messages_count']) ? 10 : $modSettings['mb_show_top_messages_count']))
						continue;

					censorText($row['subject']);

					$context['stats_blocks']['replies'][] = array(
						'id'   => $row['id_msg'],
						'name' => $row['subject'],
						'num'  => $row['num_items'],
						'link' => '<a href="' . $scripturl . '?msg=' . $row['id_msg'] . '">' . $row['subject'] . '</a>'
					);

					if ($max_items < $row['num_items'])
						$max_items = $row['num_items'];
				}

				$smcFunc['db_free_result']($result);

				if (!empty($context['stats_blocks']['replies'])) {
					foreach ($context['stats_blocks']['replies'] as $i => $reply) {
						$context['stats_blocks']['replies'][$i]['percent'] = round(($reply['num'] * 100) / $max_items);
						$context['stats_blocks']['replies'][$i]['num'] = comma_format($context['stats_blocks']['replies'][$i]['num']);
					}
				}
			}

			cache_put_data('stats_top_mb_messages', $context['stats_blocks']['replies'], 3600);
		}

		if (empty($context['stats_blocks']['replies']))
			unset($context['stats_blocks']['replies']);

		// Members with the most bookmarks
		if (!empty($modSettings['mb_show_top_members_stats']) && ($context['stats_blocks']['members'] = cache_get_data('stats_top_mb_members', 3600)) == null) {
			$result = $smcFunc['db_query']('', /** @lang text */ '
				SELECT m.id_member, m.real_name, COUNT(mb.user_id) AS num_items
				FROM {db_prefix}message_bookmarks AS mb
					INNER JOIN {db_prefix}members AS m ON (m.id_member = mb.user_id)
				GROUP BY m.id_member, m.real_name, mb.user_id
				ORDER BY num_items DESC
				LIMIT 10',
				[]
			);

			if ($smcFunc['db_num_rows']($result) > 0) {
				$max_items = 1;

				$context['stats_blocks']['members'] = [];
				while ($row = $smcFunc['db_fetch_assoc']($result)) {
					if ($row['num_items'] < (empty($modSettings['mb_show_top_members_count']) ? 10 : $modSettings['mb_show_top_members_count']))
						continue;

					$context['stats_blocks']['members'][] = array(
						'id'   => $row['id_member'],
						'name' => $row['real_name'],
						'num'  => $row['num_items'],
						'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>'
					);

					if ($max_items < $row['num_items'])
						$max_items = $row['num_items'];
				}

				$smcFunc['db_free_result']($result);

				if (!empty($context['stats_blocks']['members'])) {
					foreach ($context['stats_blocks']['members'] as $i => $member)	{
						$context['stats_blocks']['members'][$i]['percent'] = round(($member['num'] * 100) / $max_items);
						$context['stats_blocks']['members'][$i]['num'] = comma_format($context['stats_blocks']['members'][$i]['num']);
					}
				}
			}

			cache_put_data('stats_top_mb_members', $context['stats_blocks']['members'], 3600);
		}

		if (empty($context['stats_blocks']['members']))
			unset($context['stats_blocks']['members']);
	}

	private function addBookmark()
	{
		global $context, $smcFunc, $txt;

		$context['robot_no_index'] = true;

		loadTemplate('MessageBookmarks');

		$context['form_hidden_vars'] = [];
		$msg = (int) $_REQUEST['msg'];

		$request = $smcFunc['db_query']('', '
			SELECT subject, id_topic
			FROM {db_prefix}messages
			WHERE id_msg = {int:id}',
			[
				'id' => $msg
			]
		);

		if (empty($request) || $smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('error_no_subject', false);

		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		$context['form_hidden_vars']['msg'] = $msg;
		$context['form_hidden_vars'][$context['session_var']] = $context['session_id'];

		$context['mb_title'] = $row['subject'];
		$context['mb_note']  = '';

		if (isset($_POST['make_bookmark']) && !empty($_POST['title'])) {
			checkSession();

			$title = $smcFunc['htmlspecialchars']($_POST['title']);
			$note  = isset($_POST['note']) ? $smcFunc['htmlspecialchars']($_POST['note']) : '';

			$smcFunc['db_insert']('',
				'{db_prefix}message_bookmarks',
				array(
					'msg_id'         => 'int',
					'topic_id'       => 'int',
					'bookmark_title' => 'string-255',
					'bookmark_note'  => 'string-255',
					'user_id'        => 'int',
					'created_at'     => 'int'
				),
				array(
					$msg,
					$row['id_topic'],
					$title,
					$note,
					$context['user']['id'],
					time()
				),
				array('bookmark_id')
			);

			redirectexit(empty($msg) ? 'topic=' . $row['id_topic'] . '.0' : 'msg=' . $msg);
		}

		$context['sub_template'] = 'bookmark';
		$context['page_title']   = $txt['mb_title'];
	}

	private function editBookmark()
	{
		global $context, $smcFunc, $txt;

		$context['robot_no_index'] = true;

		loadTemplate('MessageBookmarks');

		$context['form_hidden_vars'] = [];
		$item = (int) $_REQUEST['item'];

		$request = $smcFunc['db_query']('', '
			SELECT bookmark_title, bookmark_note
			FROM {db_prefix}message_bookmarks
			WHERE bookmark_id = {int:item}
			LIMIT 1',
			[
				'item' => $item
			]
		);

		[$title, $note] = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$context['form_hidden_vars']['item'] = $item;
		$context['form_hidden_vars'][$context['session_var']] = $context['session_id'];

		$context['mb_title'] = $title;
		$context['mb_note']  = $note;

		if (isset($_POST['make_bookmark']) && !empty($_POST['title']))	{
			checkSession();

			$title = $smcFunc['htmlspecialchars']($_POST['title']);
			$note  = isset($_POST['note']) ? $smcFunc['htmlspecialchars']($_POST['note']) : $context['mb_note'];

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}message_bookmarks
				SET bookmark_title = {string:title}, bookmark_note = {string:note}, created_at = {int:time}
				WHERE bookmark_id = {int:item}
					AND user_id = {int:user}',
				array(
					'title' => $title,
					'note'  => $note,
					'time'  => time(),
					'item'  => $item,
					'user'  => $context['user']['id']
				)
			);

			redirectexit('action=profile;area=bookmarks;u=' . $context['user']['id']);
		}

		$context['sub_template'] = 'bookmark';
		$context['page_title']   = $txt['mb_title'];
	}

	private function delBookmark()
	{
		global $context, $smcFunc;

		if ($context['session_id'] !== $_REQUEST[$context['session_var']])
			return;

		$item = (int) $_REQUEST['item'];

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}message_bookmarks
			WHERE bookmark_id = {int:item}
				AND user_id = {int:user}',
			array(
				'item' => $item,
				'user' => $context['user']['id']
			)
		);

		if ($context['current_action'] == 'profile')
			redirectexit('action=profile;area=bookmarks;u=' . $context['user']['id']);

		if (!empty($_SERVER['HTTP_REFERER']))
			redirectexit($_SERVER['HTTP_REFERER']);
	}

	private function isIgnoredBoard(): bool
	{
		global $modSettings, $context;

		$ignoreBoards = [];

		if (!empty($modSettings['mb_ignore_boards']))
			$ignoreBoards = explode(',', $modSettings['mb_ignore_boards']);

		if (!empty($modSettings['recycle_board']))
			$ignoreBoards[] = $modSettings['recycle_board'];

		return in_array($context['current_board'], $ignoreBoards);
	}
}
