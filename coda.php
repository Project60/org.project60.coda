<?php
/*-------------------------------------------------------+
| CiviBanking CODA Importer                              |
| Copyright (C) 2016 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| License: AGPLv3, see LICENSE file                      |
+--------------------------------------------------------*/

require_once 'coda.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function coda_civicrm_config(&$config) {
  _coda_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param array $files
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function coda_civicrm_xmlMenu(&$files) {
  _coda_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function coda_civicrm_install() {
  _coda_civix_civicrm_install();

  // add our plugin to the list
  $plugin_types = civicrm_api3('OptionGroup', 'get', array('name' => 'civicrm_banking.plugin_types'));
  if (empty($plugin_types['id'])) {
    // create option group (CiviBanking not installed yet)
    $plugin_types = civicrm_api3('OptionGroup', 'create', array(
          'title'       => 'CiviBanking plugin types',
          'description' => 'The set of possible CiviBanking plugin types',
      ));
  }

  if (!empty($plugin_types['id'])) {
    $importer = CRM_Coda_OptionGroup::getValue('civicrm_banking.plugin_types', 'importer_coda', 'name', 'String', 'label');
    if (!$importer) {
      // doesn't exist yet
      civicrm_api3('OptionValue', 'create', array(
        'option_group_id'  => $plugin_types['id'],
        'name'             => 'importer_coda',
        'label'            => 'CODA Importer',
        'value'            => 'CRM_Banking_PluginImpl_Importer_CODA',
        'is_default'       => 0
        ));
    }
  }
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function coda_civicrm_uninstall() {
  // remove plugin type
  $importer_id = CRM_Coda_OptionGroup::getValue('civicrm_banking.plugin_types', 'importer_coda', 'name', 'String', 'id');

  _coda_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function coda_civicrm_enable() {
  _coda_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function coda_civicrm_disable() {
  _coda_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function coda_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _coda_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function coda_civicrm_managed(&$entities) {
  _coda_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * @param array $caseTypes
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function coda_civicrm_caseTypes(&$caseTypes) {
  _coda_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function coda_civicrm_angularModules(&$angularModules) {
_coda_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function coda_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _coda_civix_civicrm_alterSettingsFolders($metaDataFolders);
}
