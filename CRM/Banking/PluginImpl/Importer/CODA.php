<?php
/*-------------------------------------------------------+
| CiviBanking CODA Importer                              |
| Copyright (C) 2016 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| License: AGPLv3, see LICENSE file                      |
+--------------------------------------------------------*/

include_once 'vendor/autoload.php';
use Codelicious\Coda\Parser;

/**
 * CODA Importer
 * @package org.project60.banking
 */
class CRM_Banking_PluginImpl_Importer_CODA extends CRM_Banking_PluginImpl_Importer_CSV {

  /**
   * class constructor
   */ function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->import_mode))       $config->import_mode       = 'simple'; // or 'raw'
    if (!isset($config->defaults))          $config->defaults          = array();
    if (!isset($config->statement_rules))   $config->statement_rules   = array();
    if (!isset($config->transaction_rules)) $config->transaction_rules = array();
  }

  /**
   * will be used to avoid multiple account lookups
   */
  protected $account_cache = array();

  /** 
   * the plugin's user readable name
   * 
   * @return string
   */
  static function displayName()
  {
    return 'CODA Importer';
  }

  /** 
   * Report if the plugin is capable of importing files
   * 
   * @return bool
   */
  static function does_import_files()
  {
    return true;
  }

  /** 
   * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
   * 
   * @return bool
   */
  static function does_import_stream()
  {
    return false;
  }

  /** 
   * Test if the given file can be imported
   */
  function probe_file( $file_path, $params )
  {
    $config = $this->_plugin_config;
    $parser = new Parser();
    $statements = $parser->parseFile($file_path, $config->import_mode);
    return !empty($statements);
  }


  /** 
   * Import the given file
   * 
   * @return TODO: data format? 
   */
  function import_file( $file_path, $params )
  {
    // begin
    $config = $this->_plugin_config;
    $this->reportProgress(0.0, sprintf("Starting to read file '%s'...", $params['source']));

    $parser = new Parser();
    $statements = $parser->parseFile($file_path, $config->import_mode);

    // get some stats
    $total_processed = $total_count = 0;
    foreach ($statements as $statement) {
      $total_count += count($statement->transactions);
    }

    foreach ($statements as $statement) {
      $statement_data = $config->defaults;
      foreach ($config->statement_rules as $rule) {
        $this->apply_rule($rule, $statement, $statement_data, NULL);
      }

      // create an empty batch
      $batch = $this->openTransactionBatch();
      $trxn_nr = 0;

      // and process the transactions
      foreach ($statement->transactions as $transaction) {
        $trxn_nr += 1;
        $total_processed += 1;
        if ($config->import_mode == 'raw') {
          $raw_data = 'n/a (import mode: raw)';
        } else {
          $raw_data = json_encode($transaction);
        }

        $transaction_data =  array(
          'version'   => 3,
          'currency'  => 'EUR',
          'data_raw'  => $raw_data,
          'sequence'  => $trxn_nr,
        );

        $transaction_data = $transaction_data + $statement_data;

        foreach ($config->transaction_rules as $rule) {
          $this->apply_rule($rule, $transaction, $transaction_data, NULL);
        }

        // look up the bank accounts
        // TODO: move to abstract class
        foreach ($transaction_data as $key => $value) {
          // Convert NBAN to IBAN
          if (preg_match('/^_party_NBAN_(..)$/', $key, $matches)) {
            $country = $matches[1];
            $result = civicrm_api('BankingAccountReference', 'convertnban', array('version' => 3, 'nban' => $value), $country);
            $transaction_data['_party_IBAN'] = $result['values'][0]; 
          }
          // check for NBAN_?? or IBAN endings
          if (preg_match('/^_.*NBAN_..$/', $key) || preg_match('/^_.*IBAN$/', $key)) {
            // this is a *BAN entry -> look it up
            if (!isset($this->account_cache[$value])) {
              $result = civicrm_api('BankingAccountReference', 'getsingle', array('version' => 3, 'reference' => $value));
              if (!empty($result['is_error'])) {
                $this->account_cache[$value] = NULL;
              } else {
                $this->account_cache[$value] = $result['ba_id'];
              }
            }

            if ($this->account_cache[$value] != NULL) {
              if (substr($key, 0, 7)=="_party_") {
                $transaction_data['party_ba_id'] = $this->account_cache[$value];  
              } elseif (substr($key, 0, 1)=="_") {
                $transaction_data['ba_id'] = $this->account_cache[$value];  
              }
            }
          }
        }

        // prepare for BTX creation
        $transaction_data_extra = array();
        foreach ($transaction_data as $key => $value) {
          if (!in_array($key, $this->_primary_btx_fields)) {
            // this entry has to be moved to the $transaction_data_extra records
            $transaction_data_extra[$key] = $value;
            unset($transaction_data[$key]);
          }
        }
        $transaction_data['data_parsed'] = json_encode($transaction_data_extra);
        $transaction_data['bank_reference'] = sha1(print_r($transaction_data, 1));
      
        // and finally write it into the DB
        $progress = (float) $total_processed / (float) $total_count;
        $duplicate = $this->checkAndStoreBTX($transaction_data, $progress, $params);

        $this->reportProgress($progress, sprintf("Imported line %d", $trxn_nr));
      } // NEXT TRANSACTION

      // wrap up the transaction
      if ($this->getCurrentTransactionBatch()->tx_count) {
        // we have transactions in the batch -> save
        if ($config->title) {
          // the config defines a title, replace tokens
          $this->getCurrentTransactionBatch()->reference = $config->title;
        } else {
          $this->getCurrentTransactionBatch()->reference = "CODA {md5}";
        }

        if (!empty($statement_data['tx_batch.sequence']))
          $this->getCurrentTransactionBatch()->sequence = $statement_data['tx_batch.sequence'];
        if (!empty($statement_data['tx_batch.starting_date']))
          $this->getCurrentTransactionBatch()->starting_date = $statement_data['tx_batch.starting_date'];
        if (!empty($statement_data['tx_batch.ending_date']))
          $this->getCurrentTransactionBatch()->ending_date = $statement_data['tx_batch.ending_date'];

        $this->closeTransactionBatch(TRUE);
      } else {
        $this->closeTransactionBatch(FALSE);
      }
    
    } // NEXT STATEMENT

    $this->reportDone();
  }

  /**
   * Extract the value for the given key from the CODA resource
   */
  protected function getValue($key, $btx, $line=NULL, $header=array()) {
    // get value
    if (substr($key, 0, 10) == '_constant:') {
      return substr($key, 10);

    } elseif (strpos($key, ':') !== FALSE) {
      // a from field with ':' means a descend into the tree'
      $path = explode(':', $key);
      $value = $line;
      foreach ($path as $skey) {
        if (isset($value->$skey)) {
          $value = $value->$skey;
        } else {
          return '';
        }
      }
      return $value;

    } elseif (isset($line->$key)) {
      return $line->$key;

    } elseif (isset($btx[$key])) {
      return $btx[$key];

    } else {
      return '';
    }
  }




  /** 
   * Test if the configured source is available and ready
   * 
   * @var 
   * @return TODO: data format?
   */
  function probe_stream( $params )
  {
    return false;
  }

  /** 
   * Import the given file
   * 
   * @return TODO: data format? 
   */
  function import_stream( $params )
  {
    $this->reportDone(ts("Importing streams not supported by this plugin."));
  }
}

