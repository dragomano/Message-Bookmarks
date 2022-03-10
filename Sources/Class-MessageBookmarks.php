<?php

/**
 * Class-MessageBookmarks.php
 *
 * @package Message Bookmarks
 * @link https://dragomano.ru/mods/message-bookmarks
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2013-2022 Bugo
 * @license https://opensource.org/licenses/MIT MIT
 *
 * @version 0.5.1
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
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications#', false, __FILE__);
		add_integration_function('integrate_query_message', __CLASS__ . '::queryMessage#', false, __FILE__);
		add_integration_function('integrate_prepare_display_context', __CLASS__ . '::prepareDisplayContext#', false, __FILE__);
		add_integration_function('integrate_pre_profile_areas', __CLASS__ . '::preProfileAreas#', false, __FILE__);
		add_integration_function('integrate_profile_popup', __CLASS__ . '::profilePopup#', false, __FILE__);
		add_integration_function('integrate_delete_members', __CLASS__ . '::deleteMembers#', false, __FILE__);
		add_integration_function('integrate_remove_message', __CLASS__ . '::removeMessage#', false, __FILE__);
		add_integration_function('integrate_remove_topics', __CLASS__ . '::removeTopics#', false, __FILE__);
	}

	/**
	 * @hook integrate_load_theme
	 */
	public function loadTheme()
	{
		global $context;

		loadLanguage('MessageBookmarks');

		$context['use_message_bookmarks'] = allowedTo('use_message_bookmarks');
	}

	/**
	 * @hook integrate_actions
	 */
	public function actions(array &$actionArray)
	{
		$actionArray['mb'] = [false, [$this, 'init']];
	}

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

	/**
	 * @hook integrate_modify_modifications
	 */
	public function modifyModifications(array &$subActions)
	{
		$subActions['mb'] = [$this, 'settings'];
	}

	public function settings()
	{
		global $context, $txt, $scripturl, $modSettings;

		$context['page_title'] = $context['settings_title'] = $txt['mb_settings'];
		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=mb';
		$context[$context['admin_menu_name']]['tab_data']['tabs']['mb'] = ['description' => $txt['mb_mod_desc']];
		$context['permissions_excluded'] = [-1];

		$txt['select_boards_from_list'] = $txt['mb_ignored_boards_desc'];

		if (empty($modSettings['mb_class']))
			updateSettings(['mb_class' => 'sticky']);

		$config_vars = [
			['boards', 'mb_ignore_boards'],
			['text', 'mb_class'],
			['permissions', 'use_message_bookmarks']
		];

		if (isset($_GET['save'])) {
			checkSession();
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
		global $context, $modSettings;

		if (empty($context['use_message_bookmarks']) || $this->isIgnoredBoard())
			return;

		$msg_selects[] = 'mb.bookmark_id';
		$msg_tables[] = 'LEFT JOIN {db_prefix}message_bookmarks AS mb ON (mb.msg_id = id_msg AND mb.user_id = {int:current_user})';
		$msg_parameters['current_user'] = $context['user']['id'];
	}

	/**
	 * @hook integrate_prepare_display_context
	 */
	public function prepareDisplayContext(array &$output, array &$message)
	{
		global $context, $txt, $scripturl, $modSettings;

		if (empty($context['use_message_bookmarks']) || empty($context['user']['id']) || $this->isIgnoredBoard())
			return;

		$buttons = array(
			'mb_add' => array(
				'label' => $txt['mb_add_bookmark'],
				'href'  => $scripturl . '?action=mb;sa=add;topic=' . $context['current_topic'] . ';msg=' . $output['id'],
				'show'  => empty($message['bookmark_id'])
			),
			'mb_remove' => array(
				'label' => $txt['mb_remove_bookmark'],
				'href'  => $scripturl . '?action=mb;sa=del;item=' . $message['bookmark_id'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'show'  => !empty($message['bookmark_id'])
			)
		);

		ob_start();

		template_quickbuttons($buttons, 'mb_button_list');

		$output['custom_fields']['above_signature'][] = array(
			'col_name' => 'mb_buttons',
			'value'    => ob_get_clean()
		);

		if (!empty($message['bookmark_id']) && !empty($modSettings['mb_class'])) {
			$output['css_class'] .= ' ' . $modSettings['mb_class'];
		}
	}

	/**
	 * @hook integrate_pre_profile_areas
	 */
	public function preProfileAreas(array &$profile_areas)
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
			'base_href' => $scripturl . '?action=profile;area=bookmarks;u=1',
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
				'title' => array(
					'header' => array(
						'value' => $txt['title']
					),
					'data' => array(
						'function' => function ($entry) use ($scripturl) {
							return '<a href="' . $scripturl . '?msg=' . $entry['msg'] . '">' . $entry['title'] . '</a><br><p>' . $entry['note'] . '</p>';
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
		global $smcFunc, $context;

		$request = $smcFunc['db_query']('', '
			SELECT mb.bookmark_id, mb.msg_id, mb.bookmark_title, mb.bookmark_note, mb.user_id, b.id_board, b.name AS name
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
			array(
				'id' => $msg
			)
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

			$title = isset($_POST['title']) ? (string) $_POST['title'] : '';
			$note  = isset($_POST['note']) ? (string) $_POST['note'] : '';

			$smcFunc['db_insert']('',
				'{db_prefix}message_bookmarks',
				array(
					'msg_id'         => 'int',
					'topic_id'       => 'int',
					'bookmark_title' => 'string-255',
					'bookmark_note'  => 'string-255',
					'user_id'        => 'int'
				),
				array(
					$msg,
					$row['id_topic'],
					$title,
					$note,
					$context['user']['id']
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
			array(
				'item' => $item
			)
		);

		list ($title, $note) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$context['form_hidden_vars']['item'] = $item;
		$context['form_hidden_vars'][$context['session_var']] = $context['session_id'];

		$context['mb_title'] = $title;
		$context['mb_note']  = $note;

		if (isset($_POST['make_bookmark']) && !empty($_POST['title']))	{
			checkSession();

			$title = isset($_POST['title']) ? (string) $_POST['title'] : $context['mb_title'];
			$note  = isset($_POST['note']) ? (string) $_POST['note'] : $context['mb_note'];

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}message_bookmarks
				SET bookmark_title = {string:title}, bookmark_note = {string:note}
				WHERE bookmark_id = {int:item}
					AND user_id = {int:user}',
				array(
					'title' => $title,
					'note'  => $note,
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
