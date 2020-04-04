<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2018 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

 
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
//  require('includes/classes/payment.php');
  $payment_modules = new payment($payment);

//  require('includes/classes/order.php');
  $order = new order;

  $payment_modules->update_status();

  if ( ($payment_modules->selected_module != $payment) || ( is_array($payment_modules->modules) && (sizeof($payment_modules->modules) > 1) && !is_object($$payment) ) || (is_object($$payment) && ($$payment->enabled == false)) ) {
    tep_redirect(tep_href_link('checkout_payment.php', 'error_message=' . urlencode(ERROR_NO_PAYMENT_MODULE_SELECTED), 'SSL'));
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

  require('includes/languages/' . $language . '/invoice.php');

  $breadcrumb->add(NAVBAR_TITLE_1, tep_href_link('checkout_shipping.php', '', 'SSL'));
  $breadcrumb->add(NAVBAR_TITLE_2);

  //require('includes/template_top.php');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html <?php echo HTML_PARAMS; ?>>
<head>
<style>
body { 
    padding-top: 65px; 
}
</style>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<!--<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">-->
</head>
<body>

<nav class="navbar navbar-expand-sm bg-secondary fixed-top hidden-print noprint">
  <ul class="navbar-nav">
    <li class="nav-item">
     
          <h3 class="nav-link"><?php echo HEADING_TITLE; ?></h3>
           </li>
           </ul>
             <ul class="navbar-nav ml-auto ">
        <li class="nav-item pl-2 pb-2">
     <a  class="btn btn-info nav-item" href="<?php echo tep_href_link('checkout_payment.php', '', 'SSL'); ?>"><i class="far fa-arrow-alt-circle-left" aria-hidden="true"> </i>&nbsp;&nbsp;<?php echo TEXT_BACK; ?></a>  </div>
   </li>
        <li class="nav-item pl-2 pb-2">
    <button id="printInvoice" class="btn btn-info nav-item" onclick="window.print();return false;"><i class="fa fa-print"></i>&nbsp;&nbsp;<?php echo TEXT_PRINT; ?></button>
    </li>
    </ul>
  </nav>
</div>
<?php 
  if ($messageStack->size('checkout_confirmation') > 0) {
    echo $messageStack->output('checkout_confirmation');
  }
?>
<div class="content  px-5">
<div class="row align-items-center align-top">
    <div class="col align-top"><?php echo tep_image( 'images/' . STORE_LOGO, STORE_NAME); ?></div>
    <div class="col text-right">
      <?php
      echo '<h1 class="display-4">' . STORE_NAME . '</h1>';
      echo '<p>' . nl2br(STORE_ADDRESS) . '</p>';
      echo '<p>' . STORE_PHONE . '</p>';
      ?>
    </div>
  </div>
<hr>
 <div class="row border">
    <div class="col-sm-6">
    <div class="">        
        <ul class="list-group list-group-flush">
          <?php
            $address = $customer_data->get_module('address');
          if ($sendto != false) {
            echo '<li class="list-group-item">';
              echo '<i class="fas fa-shipping-fast fa-fw fa-3x float-right text-black-50"></i>';
              echo '<h5 class="mb-0">' . HEADING_DELIVERY_ADDRESS . '<small></small></h5>';
              echo '<p class="w-100 mb-1">' . $address->format($order->delivery, 1, ' ', '<br>') . '</p>';
            echo '</li>';
            }
            ?>
        
   </ul>
   </div>
    </div>
   <div class="col-sm-6">
    <div class="">        
        <ul class="list-group list-group-flush">
          <?php
           echo '<li class="list-group-item">';
            echo '<i class="fas fa-file-invoice-dollar fa-fw fa-3x float-right text-black-50"></i>';
            echo '<h5 class="mb-0">' . HEADING_BILLING_ADDRESS . '<small></small></h5>';
          echo '<p class="w-100 mb-1">' . $address->format($order->billing, 1, ' ', '<br>') . '</p>';
          echo '</li>';
          ?>
          </ul>
   </div>
  </div>
  </div>
   <br/>
  <div class="row border">
    <div class="col-sm-12">
        <div class="header">
        <h3 class="mb-1"><?php echo LIST_PRODUCTS; ?><small></small>
      </h3>
        <ul class="list-group list-group-flush">
          <?php
          for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
            echo '<li class="list-group-item">';
              echo '<span class="float-right">' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . '</span>';
              echo '<h5 class="mb-1">' . $order->products[$i]['name'] . '<small> x ' . $order->products[$i]['qty'] . '</small></h5>';
              if ( (isset($order->products[$i]['attributes'])) && (sizeof($order->products[$i]['attributes']) > 0) ) {
                echo '<p class="w-100 mb-1">';
                for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
                  echo '- ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'] . '<br>';
                }
                echo '</p>';
              }              
            echo '</li>';
          }
          ?>
        </ul>
        </div>
        </div>
        </div>
        <br/>
  <div class="row border ">
  <div class="col-sm-4 ">
  <?php
  echo '  <ul class="list-group list-group-flush">';
          if ($order->info['shipping_method']) {
            echo '<li class="list-group-item">';
              echo '<h5 class="mb-1">' . HEADING_SHIPPING_METHOD . '</h5>';
              echo '<p class="w-100 mb-1">' . $order->info['shipping_method'] . '</p>';
            echo '</li>';
          }
          echo '<li class="list-group-item">';
            echo '<h5 class="mb-1">' . HEADING_PAYMENT_METHOD . '</h5>';
            echo '<p class="w-100 mb-1">' . $order->info['payment_method'] . '</p>';
          echo '</li>';
          ?>
        </ul>
        </div>
  <div class="col-sm-5 ">
            <?php
             if (is_array($payment_modules->modules)) {
    if ($confirmation = $payment_modules->confirmation()) {

    if (tep_not_null($confirmation['title'])) {
      echo '<div class="col">';
        echo '<div class="bg-light border p-3">';
          echo $confirmation['title'];
        echo '</div>';
      echo '</div>';
    }
    if (isset($confirmation['fields'])) {
      echo '<div class="col">';
        echo '<div class="alert alert-info" role="alert">';
        $fields = '';
        for ($i=0, $n=sizeof($confirmation['fields']); $i<$n; $i++) {
          $fields .= $confirmation['fields'][$i]['title'] . ' ' . $confirmation['fields'][$i]['field'] . '<br>';
        }
        if (strlen($fields) > 4) echo substr($fields,0,-4);
        echo '</div>';
      echo '</div>';
    }
    }
    }
?>
 </div>
  <div class="col-sm-3 ">     
        <table class="table mb-0">
          <?php
          if (MODULE_ORDER_TOTAL_INSTALLED) {
            echo $order_total_modules->output();
          }
          ?>
        </table>
      </div>      
</div>
<br/>

  <?php
  if (tep_not_null($order->info['comments'])) {
    ?>
<div class="row border ">
<div class="col-sm-12">
    <h5 class="mb-1"><?php echo HEADING_ORDER_COMMENTS . '<small><a class="font-weight-lighter ml-2" href="' . tep_href_link('checkout_payment.php', '', 'SSL') . '">' .TEXT_EDIT . '</a></small>'; ?></h5>
    
    <div class="border mb-3">
      <ul class="list-group list-group-flush">
        <li class="list-group-item">
          <?php 
          echo '<i class="fas fa-comments fa-fw fa-3x float-right text-black-50"></i>';
          echo nl2br(tep_output_string_protected($order->info['comments'])) . tep_draw_hidden_field('comments', $order->info['comments']);
          ?>
        </li>
      </ul>
    </div>
   </div>
 </div>
    <?php
  }
?>

  <div class="w-100"></div>
<?php 
  echo $OSCOM_Hooks->call('siteWide', 'injectFormDisplay');
  ?>
</div>
</div>
</br>


<?php
//echo '<div class=" container hidden-print noprint">';
echo '<div class="w-100" row hidden-print noprint">';
//echo '<div class=" hidden-print noprint">';
echo '<nav class=" bg-secondary hidden-print noprint col-12 ">&nbsp;';
//  require('includes/footer.php');
 echo '</nav>';
  echo '</div>';

  require('includes/application_bottom.php');
?>
<style>
@media print
{
.noprint {display:none;}
}
#invoice{
    padding: 10px;
}
#store {
    width:50%;
    font-size:2rem;
    text-align:left;
} 

#logo {
    
    text-align:right !important;
} 
</style>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css" integrity="sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ" crossorigin="anonymous">