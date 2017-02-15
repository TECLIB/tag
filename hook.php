<?php
/*
 -------------------------------------------------------------------------
 Tag plugin for GLPI
 Copyright (C) 2003-2017 by the Tag Development Team.

 https://github.com/pluginsGLPI/tag
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Tag.

 Tag is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Tag is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Tag. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

// Plugin hook after *Uninstall*
function plugin_uninstall_after_tag($item) {
   $tagitem = new PluginTagTagItem();
   $tagitem->deleteByCriteria([
      'itemtype' => $item->getType(),
      'items_id' => $item->getID()
   ]);
}

function plugin_datainjection_populate_tag() {
   global $INJECTABLE_TYPES;

   $INJECTABLE_TYPES['PluginTagTagInjection'] = 'tag';
}

function plugin_tag_getAddSearchOptions($itemtype) {
   if (!PluginTagTag::canItemtype($itemtype)) {
      return [];
   }

   return [
      PluginTagTag::S_OPTION => [
         'table'         => PluginTagTag::getTable(),
         'field'         => 'name',
         'name'          => PluginTagTag::getTypeName(2),
         'datatype'      => 'string',
         'searchtype'    => 'contains',
         'massiveaction' => false,
         'forcegroupby'  => true,
         'usehaving'     => true,
         'joinparams'    =>  [
            'beforejoin' => [
               'table'      => 'glpi_plugin_tag_tagitems',
               'joinparams' => [
                  'jointype' => 'itemtype_item'
               ]
            ]
         ]
      ]
   ];
}

function plugin_tag_giveItem($type, $field, $data, $num, $linkfield = "") {
   switch ($field) {
      case PluginTagTag::S_OPTION:
         $out = '<div id="s2id_tag_select" class="select2-container select2-container-multi chosen-select-no-results" style="width: 100%;">
                 <ul class="select2-choices">';
         $separator = '';
         $plugintagtag = new PluginTagTag();

         foreach ($data[$num] as $tag) {
            if (isset($tag['id']) && isset($tag['name'])) {
               $id    = $tag['id'];
               $name  = $tag['name'];

               $plugintagtag->getFromDB($id);
               $color = $plugintagtag->fields["color"];

               $style = "";
               if (!empty($color)) {
                  $style .= "border-width:2px; border-color:$color;";
               }

               $out .= '<li class="select2-search-choice" style="padding-left:5px;'.$style.'">'.$separator.$name.'</li>';
               $separator = '<span style="display:none">, </span>'; //For export (CSV, PDF) of GLPI core
            }
         }
         $out .= '</ul></div>';
         return $out;
         break;
      case 6: //Type de tag
         if ($type == 'PluginTagTag') { //for future
            $key = $data[$num][0]['name'];
            if (! is_array(json_decode($key))) { //for when $key value is "0"
               return __("None");
            }

            $itemtype_names = [];
            foreach (json_decode($key) as $itemtype) {
               $item = getItemForItemtype($itemtype);
               $itemtype_names[] = $item->getTypeName();
            }
            $out = implode(", ", $itemtype_names);
            return $out;
         }
   }

   return "";
}

function plugin_tag_addHaving($link, $nott, $type, $id, $val, $num) {

   $values = explode(",", $val);
   $where  = "$link `ITEM_$num` LIKE '%".$values[0]."%'";
   array_shift($values);
   foreach ($values as $value) {
      $value = trim($value);
      $where .= " OR `ITEM_$num` LIKE '%$value%'";
   }
   return $where;
}

function plugin_tag_addWhere($link, $nott, $itemtype, $ID, $val, $searchtype) {
   // "Types d'élément associé"
   if ($itemtype == 'PluginTagTag' && $ID == 6) {
      switch ($searchtype) {
         case 'equals':
            return "`glpi_plugin_tag_tags`.`type_menu` LIKE '%\"$val\"%'";

         case 'notequals':
            return "`glpi_plugin_tag_tags`.`type_menu` NOT LIKE '%\"$val\"%'";
      }
   }

   return "";
}


/**
 * Define Dropdown managed in GLPI
 *
 * @return  array the list of dropdowns (label => class)
 */
function plugin_tag_getDropdown() {
   return ['PluginTagTag' => PluginTagTag::getTypeName(2)];
}

/**
 * Define massive actions for other itemtype
 *
 * @param  string $itemtype
 * @return array the massive action list
 */
function plugin_tag_MassiveActions($itemtype = '') {
   if (PluginTagTag::canItemtype($itemtype)) {
      return ['PluginTagTagItem'.MassiveAction::CLASS_ACTION_SEPARATOR.'chooseTag'
               => __("Add tags", 'tag')];
   }

   return [];
}

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_tag_install() {
   $version   = plugin_version_tag();
   $migration = new Migration($version['version']);

   // Parse inc directory
   foreach (glob(dirname(__FILE__).'/inc/*') as $filepath) {
      // Load *.class.php files and get the class name
      if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
         $classname = 'PluginTag' . ucfirst($matches[1]);

         // Don't load Datainjection mapping lass (no install + bug if datainjection is not installed and activated)
         if ($classname == 'PluginTagTaginjection') {
            continue;
         }

         include_once($filepath);
         // If the install method exists, load it
         if (method_exists($classname, 'install')) {
            $classname::install($migration);
         }
      }
   }
   return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_tag_uninstall() {
   // Parse inc directory
   foreach (glob(dirname(__FILE__).'/inc/*') as $filepath) {
      // Load *.class.php files and get the class name
      if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
         $classname = 'PluginTag' . ucfirst($matches[1]);

         // Don't load Datainjection mapping lass (no uninstall + bug if datainjection is not installed and activated)
         if ($classname == 'PluginTagTaginjection') {
            continue;
         }

         include_once($filepath);
         // If the install method exists, load it
         if (method_exists($classname, 'uninstall')) {
            $classname::uninstall();
         }
      }
   }
   return true;
}
