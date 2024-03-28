<?php

namespace Voucherly\Plugin\Admin;

use Voucherly\Plugin\Constants;

/**
 * Build the menu section
 */
class AdminMenu
{

  /**
   * Adds a menu inside the left bar admin side
   */
  public static function addMenu()
  {
    add_menu_page(
      Constants::PLUGIN_NAME,
      Constants::PLUGIN_NAME,
      'manage_options',
      Constants::PLUGIN_FOLDER_NAME,
      array(
        'Voucherly\Plugin\Admin\AdminMenu',
        'renderPluginDashboard'
      )
    );
  }

  /**
   * Renders the dashboard menu
   */
  public static function renderPluginDashboard()
  {
    include_once(Constants::PLUGIN_FOLDER_PATH.'includes/ui/view/partials/head_bootstrap.php');
    include_once(Constants::PLUGIN_FOLDER_PATH.'includes/ui/view/admin/index.php');
    include_once(Constants::PLUGIN_FOLDER_PATH.'includes/ui/view/partials/bottom_bootstrap.php');
  }
}
