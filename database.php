<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif(!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify that you put this file in the same place as SMF\'s index.php and SSI.php files.');

if ((SMF === 'SSI') && !$user_info['is_admin'])
	die('Admin privileges required.');

$tables[] = array(
	'name' => 'message_bookmarks',
	'columns' => array(
		array(
			'name'     => 'bookmark_id',
			'type'     => 'int',
			'size'     => 10,
			'unsigned' => true,
			'auto'     => true
		),
		array(
			'name'     => 'msg_id',
			'type'     => 'int',
			'size'     => 10,
			'unsigned' => true,
			'null'     => false,
			'not_null' => true
		),
		array(
			'name'     => 'topic_id',
			'type'     => 'mediumint',
			'size'     => 8,
			'unsigned' => true,
			'null'     => false,
			'not_null' => true
		),
		array(
			'name'     => 'bookmark_title',
			'type'     => 'varchar',
			'size'     => 255,
			'default'  => '',
			'null'     => false,
			'not_null' => true
		),
		array(
			'name'    => 'bookmark_note',
			'type'    => 'varchar',
			'size'    => 255,
			'default' => '',
			'null'    => true
		),
		array(
			'name'     => 'user_id',
			'type'     => 'mediumint',
			'size'     => 8,
			'unsigned' => true,
			'null'     => false,
			'not_null' => true
		),
		array(
			'name'     => 'created_at',
			'type'     => 'int',
			'size'     => 10,
			'unsigned' => true
		)
	),
	'indexes' => array(
		array(
			'type'    => 'primary',
			'columns' => array('bookmark_id')
		),
		array(
			'name'    => 'bookmark',
			'type'    => 'unique',
			'columns' => array('msg_id', 'user_id')
		)
	)
);

db_extend('packages');

foreach ($tables as $table) {
	$smcFunc['db_create_table']('{db_prefix}' . $table['name'], $table['columns'], $table['indexes'], array(), 'update');
}

$smcFunc['db_add_column'](
	'{db_prefix}message_bookmarks',
	array(
		'name'     => 'created_at',
		'type'     => 'int',
		'size'     => 10,
		'unsigned' => true
	),
	array(),
	'do_nothing'
);

if (SMF === 'SSI')
	echo 'Database changes are complete! Please wait...';
