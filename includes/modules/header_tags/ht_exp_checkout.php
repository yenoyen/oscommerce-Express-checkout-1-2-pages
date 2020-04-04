<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

  class ht_exp_checkout {
    var $code = 'ht_exp_checkout';
    var $group = 'header_tags';
    var $title;
    var $description;
    var $sort_order;
    var $enabled = false;
    
    function __construct() {
      $this->title = MODULE_HEADER_TAGS_EXP_CHECKOUT_TITLE;
      $this->description = MODULE_HEADER_TAGS_EXP_CHECKOUT_DESCRIPTION;

      if ( defined('MODULE_HEADER_TAGS_EXP_CHECKOUT_STATUS') ) {
        $this->sort_order = MODULE_HEADER_TAGS_EXP_CHECKOUT_SORT_ORDER;
        $this->enabled = (MODULE_HEADER_TAGS_EXP_CHECKOUT_STATUS == 'True');
      }
    }

    function execute() {
      global $oscTemplate,$PHP_SELF,$origin_href;
        if (MODULE_HEADER_TAGS_EXP_CHECKOUT_STATUS=='True'){
            if ($PHP_SELF=='checkout_shipping.php')    tep_redirect(tep_href_link('checkout_payment_exp.php', '', 'SSL')); 
            if ($PHP_SELF=='checkout_payment.php')    tep_redirect(tep_href_link('checkout_payment_exp.php', '', 'SSL')); 
   //  if ($PHP_SELF=='login.php')   tep_redirect(tep_href_link('create_account.php', '', 'SSL'));
  
        }
    
     }

    function isEnabled() {
      return $this->enabled;
    }

    function check() {
      return defined('MODULE_HEADER_TAGS_EXP_CHECKOUT_STATUS');
    }

    function install() {
      tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Express Checkout', 'MODULE_HEADER_TAGS_EXP_CHECKOUT_STATUS', 'True', 'Use Express checkout?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
    //  tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Use PWA Addon', 'USE_PWA', 'False', 'Do you use PWA?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Use Confirmation Page', 'GO_TO_CONFIRMATION', 'False', 'Do you want to have a confirmation page?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Print Invoice', 'PRINT_PRE_INVOICE', 'True', 'Do you want to have a print button on checkout paymennt?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
    //  tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Use Accounnt Sucsess Page', 'USE_ACCOUNT_SUCCESS', 'True', 'Do you want to have a accounnt sucess page?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
    //  tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Use Login Box', 'SHOW_LOGIN_BOX', 'True', 'Do you want to show login box in create account page?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_HEADER_TAGS_EXP_CHECKOUT_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");

     }

    function remove() {
      tep_db_query("delete from configuration where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_HEADER_TAGS_EXP_CHECKOUT_STATUS', 'GO_TO_CONFIRMATION', 'PRINT_PRE_INVOICE','MODULE_HEADER_TAGS_EXP_CHECKOUT_SORT_ORDER');
    }
  }
?>