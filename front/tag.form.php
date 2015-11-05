<?php
include ('../../../inc/includes.php');

Session::checkRight("config", UPDATE);

Plugin::load('tag', true);

$plugin = new Plugin();
if (! $plugin->isInstalled("tag") || ! $plugin->isActivated("tag")) {
   Html::displayNotFoundError();
}

if (isset($_POST['add']) || isset($_REQUEST['update'])) {
   if (isset($_POST['add'])) {
      $item = new PluginTagTagItem();
      
      // Check unicity :
      if (isset($_REQUEST['plugin_tag_tags_id'])) {
         $found = $item->find('plugin_tag_tags_id = '. $_REQUEST['plugin_tag_tags_id'] .'
                               AND items_id = ' . $_REQUEST['items_id'].'
                               AND itemtype = "' . $_REQUEST['itemtype'].'"');
         
         if (count($found) == 0) {
            $item->add($_REQUEST);
         }
      } else {
         $item->add($_REQUEST);
      }
   }
}

$dropdown = new PluginTagTag();

include (GLPI_ROOT . "/front/dropdown.common.form.php");
