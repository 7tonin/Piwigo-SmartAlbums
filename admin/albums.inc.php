<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

if (isset($_GET['hide_messages']))
{
  $conf['SmartAlbums']['show_list_messages'] = false;
  conf_update_param('SmartAlbums', serialize($conf['SmartAlbums']));
}

// +-----------------------------------------------------------------------+
// |                            initialization                             |
// +-----------------------------------------------------------------------+
$base_url = get_root_url().'admin.php?page=';
$self_url = SMART_ADMIN.'-albums';

$categories = array();
$query = '
SELECT id, name, permalink, dir, rank, status
  FROM '.CATEGORIES_TABLE.' AS cat
  INNER JOIN '.CATEGORY_FILTERS_TABLE.' AS cf
    ON cf.category_id = cat.id
  ORDER BY rank ASC
;';
$categories = hash_from_query($query, 'id');

// +-----------------------------------------------------------------------+
// |                    virtual categories management                      |
// +-----------------------------------------------------------------------+
// request to delete a album
if (isset($_GET['delete']) and is_numeric($_GET['delete']))
{
  delete_categories(array($_GET['delete']));
  $_SESSION['page_infos'] = array(l10n('SmartAlbum deleted'));
  update_global_rank();
  redirect($self_url);
}
// request to add a album
else if (isset($_POST['submitAdd']))
{
  $output_create = create_virtual_category(
    $_POST['virtual_name'],
    @$_POST['parent_id']
    );

  if (isset($output_create['error']))
  {
    array_push($page['errors'], $output_create['error']);
  }
  else
  {
    $_SESSION['page_infos'] = array(l10n('SmartAlbum added'));
    $redirect_url = $base_url.'cat_modify&amp;cat_id='.$output_create['id'].'&amp;new_smart';
    redirect($redirect_url);
  }
}
// request to regeneration
else if (isset($_GET['smart_generate']))
{
  /* regenerate photo list | all (sub) categories */
  if ($_GET['smart_generate'] == 'all')
  {
    foreach ($categories as $category)
    {
      $associated_images = smart_make_associations($category['id']);
      array_push(
        $page['infos'], 
        l10n_args(get_l10n_args(
          '%d photos associated to album &laquo;%s&raquo;', 
          array(
            count($associated_images), 
            trigger_event(
              'render_category_name',
              $category['name'],
              'admin_cat_list'
              )
            )
          ))
        );
    }
  }
  /* regenerate photo list | one category */
  else
  {
    $associated_images = smart_make_associations($_GET['smart_generate']);    
    array_push(
      $page['infos'], 
      l10n_args(get_l10n_args(
        '%d photos associated to album &laquo;%s&raquo;', 
        array(
          count($associated_images), 
          trigger_event(
            'render_category_name',
            $categories[$_GET['smart_generate']]['name'],
            'admin_cat_list'
            )
          )
        ))
      );
  }
  
  define('SMART_NOT_UPDATE', 1);
  invalidate_user_cache();
}

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+
$template->assign(array(
  'F_ACTION' => $self_url,
  'PWG_TOKEN' => get_pwg_token(),
 ));
 
// retrieve all existing categories for album creation
$query = '
SELECT id,name,uppercats,global_rank
  FROM '.CATEGORIES_TABLE.'
;';

display_select_cat_wrapper(
  $query,
  null,
  'category_options'
  );
  
if ($conf['SmartAlbums']['show_list_messages'])
{
  array_push($page['warnings'], l10n('Only SmartAlbums are displayed on this page'));
  array_push($page['warnings'], l10n('To order albums please go the main albums management page'));
  array_push($page['warnings'], '<a href="'.$self_url.'&hide_messages">['.l10n('Don\'t show this message again').']</a>');
}

// +-----------------------------------------------------------------------+
// |                          Categories display                           |
// +-----------------------------------------------------------------------+

// get the categories containing images directly 
$categories_with_images = array();
if ( count($categories) )
{
  $query = '
SELECT DISTINCT category_id 
  FROM '.IMAGE_CATEGORY_TABLE.'
  WHERE category_id IN ('.implode(',', array_keys($categories)).')';
  $categories_with_images = array_flip( array_from_query($query, 'category_id') );
}

$template->assign('categories', array());

foreach ($categories as $category)
{
  $tpl_cat =
    array(
      'NAME'       => get_cat_display_name_from_id($category['id'], $base_url.'cat_modify&amp;cat_id='),
      'ID'         => $category['id'],
      'RANK'       => $category['rank']*10,

      'U_JUMPTO'   => make_index_url(
        array(
          'category' => $category
          )
        ),

      'U_EDIT'     => $base_url.'cat_modify&amp;cat_id='.$category['id'],
      'U_DELETE'   => $self_url.'&amp;delete='.$category['id'].'&amp;pwg_token='.get_pwg_token(),
      'U_SMART'    => $self_url.'&amp;smart_generate='.$category['id'],
    );

  if ( array_key_exists($category['id'], $categories_with_images) )
  {
    $tpl_cat['U_MANAGE_ELEMENTS'] =
      $base_url.'batch_manager&amp;cat='.$category['id'];
  }

  if ('private' == $category['status'])
  {
    $tpl_cat['U_MANAGE_PERMISSIONS'] =
      $base_url.'cat_perm&amp;cat='.$category['id'];
  }
  
  $template->append('categories', $tpl_cat);
}

?>