<?php
/*
  $Id$

    osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2018 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');
  
  $OSCOM_Hooks->register_pipeline('progress');

// if the customer is not logged on, redirect them to the login page
  if (!tep_session_is_registered('customer_id')) {
    $navigation->set_snapshot();
    tep_redirect(tep_href_link('login.php', '', 'SSL'));
  }

// if there is nothing in the customers cart, redirect them to the shopping cart page
  if ($cart->count_contents() < 1) {
    tep_redirect(tep_href_link('shopping_cart.php'));
  }

// if no shipping destination address was selected, use the customers own address as default
  if (!tep_session_is_registered('sendto')) {
    tep_session_register('sendto');
    $sendto = $customer_default_address_id;
  } 
  else {
// verify the selected shipping address
    if ( (is_array($sendto) && empty($sendto)) || is_numeric($sendto) ) {
      $check_address_query = tep_db_query("select count(*) as total from address_book where customers_id = '" . (int)$customer_id . "' and address_book_id = '" . (int)$sendto . "'");
      $check_address = tep_db_fetch_array($check_address_query);

      if ($check_address['total'] != '1') {
        $sendto = $customer_default_address_id;
        if (tep_session_is_registered('shipping')) tep_session_unregister('shipping');
      }
    }
  }

 // require('includes/classes/order.php');
  $order = new order;

// register a random ID in the session to check throughout the checkout procedure
// against alterations in the shopping cart contents
  if (!tep_session_is_registered('cartID')) {
    tep_session_register('cartID');
  } elseif (($cartID != $cart->cartID) && tep_session_is_registered('shipping')) {
    tep_session_unregister('shipping');
  }

  $cartID = $cart->cartID = $cart->generate_cart_id();

// if the order contains only virtual products, forward the customer to the billing page as
// a shipping address is not needed
  if ($order->content_type == 'virtual') {
    if (!tep_session_is_registered('shipping')) tep_session_register('shipping');
    $shipping = false;
    $sendto = false;
    tep_redirect(tep_href_link('checkout_payment.php', '', 'SSL'));
  }

  $total_weight = $cart->show_weight();
  $total_count = $cart->count_contents();

// load all enabled shipping modules
//require('includes/classes/shipping.php');
  $shipping_modules = new shipping;

  if ( defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && (MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true') ) {
    $pass = false;

    switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
      case 'national':
        if ($order->delivery['country_id'] == STORE_COUNTRY) {
          $pass = true;
        }
        break;
      case 'international':
        if ($order->delivery['country_id'] != STORE_COUNTRY) {
          $pass = true;
        }
        break;
      case 'both':
        $pass = true;
        break;
    }

    $free_shipping = false;

    if ( ($pass == true) && ($order->info['total'] >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER) ) {
      $free_shipping = true;

      include('includes/languages/' . $language . '/modules/order_total/ot_shipping.php');
    }
  } else {
    $free_shipping = false;
  }

// process the selected shipping method
//  if ( isset($_GET['action']) && ($_GET['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] == $sessiontoken) ) {
  $action=1;
  if( $action=1){
    if (!tep_session_is_registered('comments')) tep_session_register('comments');
    if (tep_not_null($_POST['comments'])) {
      $comments = tep_db_prepare_input($_POST['comments']);
    }

   if (!tep_session_is_registered('shipping')) tep_session_register('shipping');

    if ( (tep_count_shipping_modules() > 0) || ($free_shipping == true) ) {
      if ( (isset($_POST['shipping'])) && (strpos($_POST['shipping'], '_')) ) {
        $shipping = $_POST['shipping'];

        list($module, $method) = explode('_', $shipping);
        if ( is_object($$module) || ($shipping == 'free_free') ) {
          if ($shipping == 'free_free') {
            $quote[0]['methods'][0]['title'] = FREE_SHIPPING_TITLE;
            $quote[0]['methods'][0]['cost'] = '0';
          } else {
            $quote = $shipping_modules->quote($method, $module);
          }
          if (isset($quote['error'])) {
            tep_session_unregister('shipping');
          } else {
            if ( (isset($quote[0]['methods'][0]['title'])) && (isset($quote[0]['methods'][0]['cost'])) ) {
              $way = ''; 
              if (!empty($quote[0]['methods'][0]['title'])) {
                  $way = ' (' . $quote[0]['methods'][0]['title'] . ')'; 
              }
              $shipping = array('id' => $shipping,
                                'title' => (($free_shipping == true) ?  $quote[0]['methods'][0]['title'] : $quote[0]['module'] . $way), 
                                'cost' => $quote[0]['methods'][0]['cost']);

              tep_redirect(tep_href_link('total_ajax.php', '', 'SSL'));
            }
          }
        } else {
          tep_session_unregister('shipping');
        }
      }
    } else {
    if ( defined('SHIPPING_ALLOW_UNDEFINED_ZONES') && (SHIPPING_ALLOW_UNDEFINED_ZONES == 'False') ) {
        tep_session_unregister('shipping');
      } 
      else {
        $shipping = false;
      //  tep_redirect(tep_href_link('checkout_payment.php', '', 'SSL'));
      }
    }
  }

// get all available shipping quotes
  $quotes = $shipping_modules->quote();

// if no shipping method has been selected, automatically select the cheapest method.
// if the modules status was changed when none were available, to save on implementing
// a javascript force-selection method, also automatically select the cheapest shipping
// method if more than one module is now enabled
  if ( !tep_session_is_registered('shipping') || ( tep_session_is_registered('shipping') && ($shipping == false) && (tep_count_shipping_modules() > 1) ) ) $shipping = $shipping_modules->cheapest();

  require('includes/languages/' . $language . '/checkout_shipping.php');

  if ( defined('SHIPPING_ALLOW_UNDEFINED_ZONES') && (SHIPPING_ALLOW_UNDEFINED_ZONES == 'False') && !    tep_session_is_registered('shipping') && ($shipping == false) ) {
  $messageStack->add_session('checkout_address', ERROR_NO_SHIPPING_AVAILABLE_TO_SHIPPING_ADDRESS);
  tep_redirect(tep_href_link('checkout_shipping_address.php', '', 'SSL'));
}
 tep_session_register('shipping');
  //  $payment_modules->update_status();
 //  require('includes/classes/order_total.php'); 
   $order_total_modules = new order_total;
   $order_total_modules->process();
?>
<div class="contentContainer">
 
 <table class="table table-info table-hover mb-0">
          <?php
          if (MODULE_ORDER_TOTAL_INSTALLED) {
            echo $order_total_modules->output();
          }
      
          ?>
        </table>

</div>

<?php

  require('includes/application_bottom.php');
?>
