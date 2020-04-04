<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2018 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');
  
 // $OSCOM_Hooks->register_pipeline('progress');
 
// if the customer is not logged on, redirect them to the login page
  if (!tep_session_is_registered('customer_id')) {
    $navigation->set_snapshot(array('mode' => 'SSL', 'page' => 'checkout_payment.php'));
    tep_redirect(tep_href_link('login.php', '', 'SSL'));
  }

// if there is nothing in the customers cart, redirect them to the shopping cart page
  if ($cart->count_contents() < 1) {
    tep_redirect(tep_href_link('shopping_cart.php'));
  }

// avoid hack attempts during the checkout procedure by checking the internal cartID
  if (isset($cart->cartID) && tep_session_is_registered('cartID')) {
    if ($cart->cartID != $cartID) {
      tep_redirect(tep_href_link('checkout_shipping.php', '', 'SSL'));
    }
  }

// if no shipping method has been selected, redirect the customer to the shipping method selection page
  if (!tep_session_is_registered('shipping')) {
    tep_redirect(tep_href_link('checkout_shipping.php', '', 'SSL'));
  }

  if (!tep_session_is_registered('payment')) tep_session_register('payment');
  if (isset($_POST['payment'])) $payment = $_POST['payment'];

  if (!tep_session_is_registered('comments')) tep_session_register('comments');
  if (isset($_POST['comments']) && tep_not_null($_POST['comments'])) {
    $comments = tep_db_prepare_input($_POST['comments']);
  }

// load the selected payment module
  //require('includes/classes/payment.php');
  $payment_modules = new payment($payment);

 // require('includes/classes/order.php');
  $order = new order;

  $payment_modules->update_status();

  if ( ($payment_modules->selected_module != $payment) || ( is_array($payment_modules->modules) && (sizeof($payment_modules->modules) > 1) && !is_object($$payment) ) || (is_object($$payment) && ($$payment->enabled == false)) ) {
  //  tep_redirect(tep_href_link('payment_ajax.php', 'error_message=' . urlencode(ERROR_NO_PAYMENT_MODULE_SELECTED), 'SSL'));
  }

  if (is_array($payment_modules->modules)) {
    $payment_modules->pre_confirmation_check();
  }

// load the selected shipping module
  //require('includes/classes/shipping.php');
  $shipping_modules = new shipping($shipping);
  // require('includes/classes/order_total.php');
  $order_total_modules = new order_total;
  $order_total_modules->process();

// Stock Check
  $any_out_of_stock = false;
  if (STOCK_CHECK == 'true') {
    for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
      if (tep_check_stock($order->products[$i]['id'], $order->products[$i]['qty'])) {
        $any_out_of_stock = true;
      }
    }
    // Out of Stock
    if ( (STOCK_ALLOW_CHECKOUT != 'true') && ($any_out_of_stock == true) ) {
      tep_redirect(tep_href_link('shopping_cart.php'));
    }
  }

  require('includes/languages/' . $language . '/checkout_confirmation.php');

  $breadcrumb->add(NAVBAR_TITLE_1, tep_href_link('checkout_shipping.php', '', 'SSL'));
  $breadcrumb->add(NAVBAR_TITLE_2);

echo '<div class="contentContainer">';
  if (isset($_GET['payment_error']) && is_object(${$_GET['payment_error']}) && ($error = ${$_GET['payment_error']}->get_error())) {
    echo '<div class="alert alert-danger">';
      echo '<p class="lead"><b>' . tep_output_string_protected($error['title']) . '</b></p>';
      echo '<p>' . tep_output_string_protected($error['error']) . '</p>';
    echo '</div>';
  }
  
  $selection = $payment_modules->selection();
       echo '<div class="col-12">';
    echo '<ul class="list-group">';
    
            echo '<li class="list-group-item">';
            echo '<h5 class="mb-1">' . HEADING_PAYMENT_METHOD . '<small></small></h5>';
            echo '<p class="w-100 mb-1">' . $order->info['payment_method'] . '</p>';
          echo '</li>';
    echo '</ul></div>';

 
  require('includes/application_bottom.php');
?>
