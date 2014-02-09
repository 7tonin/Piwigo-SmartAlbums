<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class SmartAlbums_maintain extends PluginMaintain
{
  private $installed = false;

  private $default_conf = array(
    'update_on_upload' => false,
    'update_on_date' => true,
    'update_timeout' => 3,
    'show_list_messages' => true,
    'smart_is_forbidden' => false,
    'last_update' => 0,
    );

  private $table;

  function __construct($plugin_id)
  {
    global $prefixeTable;

    parent::__construct($plugin_id);

    $this->table = $prefixeTable . 'category_filters';
  }

  function install($plugin_version, &$errors=array())
  {
    global $conf, $prefixeTable;

    if (empty($conf['SmartAlbums']))
    {
      $conf['SmartAlbums'] = serialize($this->default_conf);
      conf_update_param('SmartAlbums', $conf['SmartAlbums']);
    }
    else
    {
      $new_conf = is_string($conf['SmartAlbums']) ? unserialize($conf['SmartAlbums']) : $conf['SmartAlbums'];

      // new param in 2.0.2
      if (!isset($new_conf['smart_is_forbidden']))
      {
        $new_conf['smart_is_forbidden'] = true;
      }

      // new params in 2.1.0
      if (!isset($new_conf['update_on_date']))
      {
        $new_conf['update_on_date'] = true;
        $new_conf['update_timeout'] = 3;
        $new_conf['last_update'] = 0;
      }

      $conf['SmartAlbums'] = serialize($new_conf);
      conf_update_param('SmartAlbums', $conf['SmartAlbums']);
    }

    // new table
    pwg_query(
'CREATE TABLE IF NOT EXISTS `' . $this->table . '` (
  `category_id` smallint(5) unsigned NOT NULL,
  `type` varchar(16) NOT NULL,
  `cond` varchar(32) NULL,
  `value` text NULL,
  `updated` DATETIME NOT NULL DEFAULT "1970-01-01 00:00:00",
  KEY `category_id` (`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
;');

    // new column on image category table
    $result = pwg_query('SHOW COLUMNS FROM `' . IMAGE_CATEGORY_TABLE . '` LIKE "smart";');
    if (!pwg_db_num_rows($result))
    {
      pwg_query('ALTER TABLE `' . IMAGE_CATEGORY_TABLE . '` ADD `smart` ENUM(\'true\', \'false\') NOT NULL DEFAULT \'false\';');
    }

    // new column on category filters table (2.1.1)
    $result = pwg_query('SHOW COLUMNS FROM `' . $this->table . '` LIKE "updated";');
    if (!pwg_db_num_rows($result))
    {
      pwg_query('ALTER TABLE `' . $this->table . '` ADD `updated` DATETIME NOT NULL DEFAULT "1970-01-01 00:00:00"');
    }

    // remove column on category table, moved to category filters table (2.1.1)
    $result = pwg_query('SHOW COLUMNS FROM `' . CATEGORIES_TABLE . '` LIKE "smart_update";');
    if (pwg_db_num_rows($result))
    {
      pwg_query('UPDATE `' . $this->table . '` AS f SET updated = ( SELECT smart_update FROM `' . CATEGORIES_TABLE . '` AS c WHERE c.id = f.category_id );');
      pwg_query('ALTER TABLE `' . CATEGORIES_TABLE . '` DROP `smart_update`;');
    }

    // date filters renamed in 2.0
    $query = '
SELECT category_id
  FROM `' . $this->table . '`
  WHERE
    type = "date" AND
    cond IN ("the","before","after","the_crea","before_crea","after_crea")
;';

    if (pwg_db_num_rows(pwg_query($query)))
    {
      $name_changes = array(
        'the' => 'the_post',
        'before' => 'before_post',
        'after' => 'after_post',
        'the_crea' => 'the_taken',
        'before_crea' => 'before_taken',
        'after_crea' => 'after_taken',
        );
      foreach ($name_changes as $old => $new)
      {
        pwg_query('UPDATE `' . $this->table . '` SET cond = "' . $new . '" WHERE type = "date" AND cond = "' . $old . '";');
      }
    }
    
    // limit filter extended in 2.2.1
    pwg_query('UPDATE `' . $this->table . '` SET cond = "" WHERE type = "limit" AND cond = "limit";');
    pwg_query('ALTER TABLE `' . $this->table . '` CHANGE `cond` `cond` VARCHAR(32) NULL ;');

    $this->installed = true;
  }

  function activate($plugin_version, &$errors=array())
  {
    if (!$this->installed)
    {
      $this->install($plugin_version, $errors);
    }
  }

  function deactivate()
  {
  }

  function uninstall()
  {
    conf_delete_param('SmartAlbums');

    pwg_query('DROP TABLE `' . $this->table . '`;');

    pwg_query('ALTER TABLE `' . IMAGE_CATEGORY_TABLE . '` DROP `smart`;');
  }
}
