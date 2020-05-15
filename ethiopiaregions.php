<?php

require_once 'ethiopiaregions.civix.php';
use CRM_Ethiopiaregions_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/ 
 */
function ethiopiaregions_civicrm_config(&$config) {
  _ethiopiaregions_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function ethiopiaregions_civicrm_enable() {
  _ethiopiaregions_civix_civicrm_enable();

  ethiopiaregions_load_regions();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function ethiopiaregions_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _ethiopiaregions_civix_civicrm_upgrade($op, $queue);
}

/**
 * Load regions from the CSV file.
 */
function ethiopiaregions_load_regions() {
  // Fix existing state/provinces
  // @todo Submit a fix upstream
  $state_province_fixes = [
    // Mostly opting for the short form listed on Wikipedia-EN.
    'Afar' => 'Afar Region',
    'Amara' => 'Amhara Region',
    'Benshangul-Gumaz' => 'Benishangul-Gumuz Region',
    'Gambela Peoples' => 'Gambela Region',
    'Harari People' => 'Harari Region',
    'Oromia' => 'Oromia Region',
    'Somali' => 'Somali Region',
    'Southern Nations, Nationalities and Peoples' => 'S.N.N.P. Region',
    'Tigrai' => 'Tigray Region',
  ];

  $country_id = civicrm_api3('Country', 'getsingle', [
    'name' => 'Ethiopia',
  ])['id'];

  foreach ($state_province_fixes as $old => $new) {
    CRM_Core_DAO::executeQuery('UPDATE civicrm_state_province SET name = %1 WHERE name = %2 AND country_id = %3', [
      1 => [$new, 'String'],
      2 => [$old, 'String'],
      3 => [$country_id, 'Positive'],
    ]);
  }

  // Get all the state_provinces
  $stateProvince = CRM_Core_PseudoConstant::stateProvinceForCountry($country_id, FALSE);

  $cpt = 0;
  $path = Civi::resources()->getPath(E::LONG_NAME, '/ethiopiaregions.csv');
  $handle = fopen($path, 'r');

  if (!$handle) {
    throw new Exception('Failed to read ethiopiaregions.csv');
  }

  while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
    $cpt++;

    // First row contains headers
    if ($cpt == 1) {
      continue;
    }

    // Get the state_province_id associated to the name
    $state_province_id = array_search($row[0], $stateProvince);

    if ($state_province_id === FALSE) {
      throw new Exception("State/province not found: {$row[0]}. If you are enabling this extension from drush, make sure to use -u 1 to run as admin.");
    }

    // Check if the county already exists
    // We are concatenating the Zone/Sub-City with the Woreda, since the woreda is what
    // is most important.
    $county_name = $row[1] . ', ' . $row[2];

    $county_id = CRM_Core_DAO::singleValueQuery('SELECT id FROM civicrm_county WHERE name = %1 AND state_province_id = %2', [
      1 => [$row[1], 'String'],
      2 => [$state_province_id, 'Positive'],
    ]);

    if (!$county_id) {
      CRM_Core_DAO::executeQuery('INSERT INTO civicrm_county(name, state_province_id) VALUES (%1, %2)', [
        1 => [$row[1], 'String'],
        2 => [$state_province_id, 'Positive'],
      ]);
    }
  }

  fclose($handle);
}
