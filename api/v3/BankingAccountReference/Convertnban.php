<?php
include_once 'vendor/autoload.php';
use globalcitizen\php\iban;

use CRM_Coda_ExtensionUtil as E;

/**
 * BankingAccountReference.Convertnban API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_banking_account_reference_Convertnban_spec(&$spec) {
  $spec['nban']['api.required'] = 1;
  $spec['country']['api.required'] = 0;
}

/**
 * BankingAccountReference.Convertnban API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_banking_account_reference_Convertnban($params) {
  if (array_key_exists('nban', $params)) {

    if (array_key_exists('country', $params)) {
      $country = $params['country'];
    }
    else {
      $country = 'BE';
    }
    $iban = $country.'00'.$params['nban'];
    $checksum = iban_find_checksum($iban);

    $iban = $country.$checksum.$params['nban'];
   
    $returnValues = array(
      $iban
    );

    // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
    return civicrm_api3_create_success($returnValues, $params, 'NewEntity', 'NewAction');
  }
  else {
    throw new API_Exception(/*errorMessage*/ 'Everyone knows that the magicword is "sesame"', /*errorCode*/ 1234);
  }
}
