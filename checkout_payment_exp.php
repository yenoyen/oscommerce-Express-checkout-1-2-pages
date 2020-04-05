<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2020 osCommerce

  Released under the GNU General Public License
*/

  require 'includes/application_top.php';
  
  $OSCOM_Hooks->register_pipeline('progress');

// if the customer is not logged on, redirect them to the login page
  if (!isset($_SESSION['customer_id'])) {
    $navigation->set_snapshot();
    tep_redirect(tep_href_link('login.php', '', 'SSL'));
  }

// if there is nothing in the customers cart, redirect them to the shopping cart page
  if ($cart->count_contents() < 1) {
    tep_redirect(tep_href_link('shopping_cart.php'));
  }

// if no shipping method has been selected, redirect the customer to the shipping method selection page
  if (!isset($_SESSION['shipping'])) {
 //   tep_redirect(tep_href_link('checkout_shipping.php', '', 'SSL'));
  }

// avoid hack attempts during the checkout procedure by checking the internal cartID
  if (isset($cart->cartID) && isset($_SESSION['cartID'])) {
    if ($cart->cartID != $cartID) {
      tep_redirect(tep_href_link('checkout_shipping.php', '', 'SSL'));
    }
  }

// Stock Check
  if ( (STOCK_CHECK == 'true') && (STOCK_ALLOW_CHECKOUT != 'true') ) {
    foreach ($cart->get_products() as $product) {
      if (tep_check_stock($product['id'], $product['quantity'])) {
        tep_redirect(tep_href_link('shopping_cart.php'));
        break;
      }
    }
  }

  if (isset($_SESSION['billto'])) {
// verify the selected billing address
    if ( is_numeric($_SESSION['billto']) || ([] === $_SESSION['billto']) ) {
      $check_address_query = tep_db_query("SELECT COUNT(*) AS total FROM address_book WHERE customers_id = " . (int)$_SESSION['customer_id'] . " and address_book_id = " . (int)$_SESSION['billto']);
      $check_address = tep_db_fetch_array($check_address_query);

      if ($check_address['total'] != '1') {
        $_SESSION['billto'] = $customer->get_default_address_id();
        unset($_SESSION['payment']);
      }
    }
  } else {
    // if no billing destination address was selected, use the customers own address as default
    $_SESSION['billto'] = $customer->get_default_address_id();
  }
 if (isset($_SESSION['sendto'])) {
    if ( (is_numeric($_SESSION['sendto']) && empty($customer->fetch_to_address($_SESSION['sendto']))) || ([] === $_SESSION['sendto']) ) {
      $_SESSION['sendto'] = $customer->get('default_address_id');
      unset($_SESSION['shipping']);
    }
  } else {
    // if no shipping destination address was selected, use the customer's own address as default
    $_SESSION['sendto'] = $customer->get('default_address_id');
  }
  
  $order = new order();
  $order_total_modules = new order_total();
  $order_total_modules->process();
  if (!tep_session_is_registered('comments')) tep_session_register('comments');
  if (isset($_POST['comments']) && tep_not_null($_POST['comments'])) {
    $comments = tep_db_prepare_input($_POST['comments']);
  }
  if ($order->content_type == 'virtual') {
    $_SESSION['shipping'] = false;
    $_SESSION['sendto'] = false;
    tep_redirect(tep_href_link('checkout_payment.php', '', 'SSL'));
  }
  $total_weight = $cart->show_weight();
  $total_count = $cart->count_contents();
// load all enabled shipping modules
  $shipping_modules = new shipping();

  $free_shipping = false;
  if ( ot_shipping::is_eligible_free_shipping($order->delivery['country_id'], $order->info['total']) ) {
      $free_shipping = true;

      include "includes/languages/$language/modules/order_total/ot_shipping.php";
  }

  $module_count = tep_count_shipping_modules();
// process the selected shipping method
 // if (tep_validate_form_action_is('process')) {
    function tep_process_selected_shipping_method() {
      if (tep_not_null($_POST['comments'])) {
        $_SESSION['comments'] = tep_db_prepare_input($_POST['comments']);
      }

      if ( ($GLOBALS['module_count'] <= 0) && !$GLOBALS['free_shipping'] ) {
        if ( defined('SHIPPING_ALLOW_UNDEFINED_ZONES') && (SHIPPING_ALLOW_UNDEFINED_ZONES == 'False') ) {
          unset($_SESSION['shipping']);
          return;
        }

        $_SESSION['shipping'] = false;
        tep_redirect(tep_href_link('checkout_payment.php', '', 'SSL'));
      }

     if ( (isset($_POST['shipping'])) && (strpos($_POST['shipping'], '_')) ) {
        $_SESSION['shipping'] = $_POST['shipping'];

        list($module, $shipping_method) = explode('_', $_SESSION['shipping']);
        if ('free_free' === $_SESSION['shipping']) {
          $quote[0]['methods'][0]['title'] = FREE_SHIPPING_TITLE;
          $quote[0]['methods'][0]['cost'] = '0';
        } elseif (is_object($GLOBALS[$module])) {
          $quote = $GLOBALS['shipping_modules']->quote($shipping_method, $module);
        } else {
          unset($_SESSION['shipping']);
          return;
        }

        if (isset($quote['error'])) {
          unset($_SESSION['shipping']);
          return;
        }

        if ( isset($quote[0]['methods'][0]['title'], $quote[0]['methods'][0]['cost']) ) {
          $way = '';
          if (!empty($quote[0]['methods'][0]['title'])) {
            $way = ' (' . $quote[0]['methods'][0]['title'] . ')';
          }

          $_SESSION['shipping'] = [
            'id' => $_SESSION['shipping'],
            'title' => ($GLOBALS['free_shipping'] ?  $quote[0]['methods'][0]['title'] : $quote[0]['module'] . $way),
            'cost' => $quote[0]['methods'][0]['cost'],
          ];

          tep_redirect(tep_href_link('checkout_payment.php', '', 'SSL'));
        }
     }//
    }

    tep_process_selected_shipping_method();
 // }/////

// get all available shipping quotes
  $quotes = $shipping_modules->quote();

  if (!($_SESSION['shipping']->enabled ?? false)) {
    unset($_SESSION['shipping']);
  }

// if no shipping method has been selected, automatically select the cheapest method.
// if the module's status was changed when none were available, to save on implementing
// a javascript force-selection method, also automatically select the cheapest shipping
// method if more than one module is now enabled
  if ( !isset($_SESSION['shipping']) || (!$_SESSION['shipping'] && (tep_count_shipping_modules() > 1)) ) {
    $_SESSION['shipping'] = $shipping_modules->cheapest();
  }

// load all enabled payment modules
  $payment_modules = new payment();

  require "includes/languages/$language/checkout_payment_exp.php";

  $breadcrumb->add(NAVBAR_TITLE_1, tep_href_link('checkout_shipping.php', '', 'SSL'));
  $breadcrumb->add(NAVBAR_TITLE_2, tep_href_link('checkout_payment.php', '', 'SSL'));

  require 'includes/template_top.php';

  echo $payment_modules->javascript_validation();
?>

<h1 class="display-4"><?php echo HEADING_TITLE; ?></h1>

<?php
 if(GO_TO_CONFIRMATION!='True')  
 echo tep_draw_form('checkout_payment', tep_href_link('checkout_process.php', '', 'SSL'), 'post', 'onsubmit="return check_form();"', true); 
  if(GO_TO_CONFIRMATION!='False')  echo tep_draw_form('checkout_payment', tep_href_link('checkout_confirmation.php', '', 'SSL'), 'post', 'onsubmit="return check_form();"', true); 
 
 ?>

<div class="contentContainer">
  <div class="row">
    <div class="col-sm-7">
      <h5 class="mb-1"><?php echo LIST_PRODUCTS; ?><small><a class="font-weight-lighter ml-2" href="<?php echo tep_href_link('shopping_cart.php', '', 'SSL'); ?>"><?php echo TEXT_EDIT; ?></a></small>
      </h5>
  
        <ul class="list-group list-group-flush">
          <?php
          
          for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
            echo '<li class="list-group-item list-group-flush  list-group-item-info">';
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
  <div class="row">
    <div class="col-sm-7">
      <h5 class="mb-1"><?php echo TABLE_HEADING_SHIPPING_METHOD; ?></h5>
      <div>
        <?php
        if ($module_count > 0) {
          if ($free_shipping == true) {
            ?>
        <div class="alert alert-info mb-0" role="alert">
          <p class="lead"><b><?php echo FREE_SHIPPING_TITLE; ?></b></p>
          <p class="lead"><?php echo sprintf(FREE_SHIPPING_DESCRIPTION, $currencies->format(MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)) . tep_draw_hidden_field('shipping', 'free_free'); ?></p>
        </div>
            <?php
          } else {
            ?>
        <table class="table border-right border-left border-bottom table-sm table-hover m-0">
          <?php
          // GDPR - can't be checked by default?
          $checked = null;

          $n = count($quotes);
          foreach ($quotes as $quote) {
            $n2 = count($quote['methods']);
            foreach (($quote['methods'] ?? []) as $method) {
              // set the radio button to be checked if it is the method chosen
               $checked = (($quote['id'] . '_' . $method['id'] == $shipping['id']) ? true : false);
              ?>
              <tr class="table-selection table-selection3">
                <td>
                  <?php
                  echo $quote['module'];

                  if (tep_not_null($quote['icon'] ?? '')) {
                    echo '&nbsp;' . $quote['icon'];
                  }

                  if (isset($quote['error'])) {
                    echo '<div class="form-text">' . $quote['error'] . '</div>';
                  }

                  if (tep_not_null($method['title'])) {
                    echo '<div class="form-text">' . $method['title'] . '</div>';
                  }
                  ?>
                </td>
                <?php
                if ( ($n > 0) || ($n2 > 0) ) {
                  ?>
                  <td class="text-right">
                    <?php
                    if (isset($quote['error'])) {
                      echo '<div class="alert alert-error">' . $quote['error'] . '</div>';
                    } else {
                      echo '<div class="custom-control custom-radio custom-control-inline">';
                      echo tep_draw_radio_field('shipping',  $quote['id'] . '_' . $method['id'], $checked, 'id="d_' . $method['id'] . '" required aria-required="true" aria-describedby="d_' . $method['id'] . '" class="custom-control-input"');
                      echo '<label class="custom-control-label" for="d_' . $method['id'] . '">' . $currencies->format(tep_add_tax($method['cost'], (isset($quote['tax']) ? $quote['tax'] : 0))) . '</label>';
                      echo '</div>';
                    }
                    ?>
                  </td>
                  <?php
                } else {
                  ?>
                  <td class="text-right"><?php echo $currencies->format(tep_add_tax($method['cost'], (isset($quote['tax']) ? $quote['tax'] : 0))) . tep_draw_hidden_field('shipping', $quote['id'] . '_' . $method['id']); ?></td>
                  <?php
                }
                ?>
              </tr>
              <?php
              }
            }
          }
          ?>
        </table>
        <?php
        if ( !$free_shipping && (1 === $module_count) ) {
          ?>
          <p class="m-2 font-weight-lighter"><?php echo TEXT_ENTER_SHIPPING_INFORMATION; ?></p>
          <?php
        }
      }
      ?>
      </div>
    </div>
    
    <div class="col-sm-5">
      <h5 class="mb-1">
        <?php
        echo TABLE_HEADING_SHIPPING_ADDRESS;
        echo sprintf(LINK_TEXT_EDIT, 'font-weight-lighter ml-3', tep_href_link('checkout_shipping_address.php', '', 'SSL'));
        ?>
      </h5>
      <div class="border">
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><?php echo SHIPPING_FA_ICON . $customer->make_address_label($_SESSION['sendto'], true, ' ', '<br>'); ?></li>
        </ul>
      </div>
    </div>
  </div>
<?php
  if (isset($_GET['payment_error']) && is_object(${$_GET['payment_error']}) && ($error = ${$_GET['payment_error']}->get_error())) {
    echo '<div class="alert alert-danger">' . "\n";
      echo '<p class="lead"><b>' . tep_output_string_protected($error['title']) . "</b></p>\n";
      echo '<p>' . tep_output_string_protected($error['error']) . "</p>\n";
    echo '</div>';
  }

  $selection = $payment_modules->selection();
?>

  <div class="row">
    <div class="col-sm-7">
      <h5 class="mb-1"><?php echo TABLE_HEADING_PAYMENT_METHOD; ?></h5>
      <div>
        <table class="table border-right border-left border-bottom table-sm table-hover m-0">
          <?php
          foreach ($selection as $choice) {
            ?>
            <tr class="table-selection table-selection2">
              <td><label for="p_<?php echo $choice['id']; ?>"><?php echo $choice['module']; ?></label></td>
              <td class="text-right">
                <?php
                if (count($selection) > 0) {
                  echo '<div class="custom-control custom-radio custom-control-inline">';
                    echo tep_draw_radio_field('payment', $choice['id'], ($choice['id'] == $payment), 'id="p_' . $choice['id'] . '" required="required" aria-required="true" class="custom-control-input"');
                    echo '<label class="custom-control-label" for="p_' . $choice['id'] . '">&nbsp;</label>';
                  echo '</div>';
                } else {
                  echo tep_draw_hidden_field('payment', $choice['id']);
                }
                ?>
              </td>
            </tr>
            <?php
            if (isset($choice['error'])) {
              ?>
              <tr>
                <td colspan="2"><?php echo $choice['error']; ?></td>
              </tr>
              <?php
            } elseif (isset($choice['fields']) && is_array($choice['fields'])) {
              foreach ($choice['fields'] as $field) {
                ?>
                <tr>
                  <td><?php echo $field['title']; ?></td>
                  <td><?php echo $field['field']; ?></td>
                </tr>
                <?php
              }
            }
          }
          ?>
        </table>
        
        <?php
        if (count($selection) == 1) {
          echo '<p class="m-2 font-weight-lighter">' . TEXT_ENTER_PAYMENT_INFORMATION . "</p>\n";
        }
        ?>
        
      </div>
    </div>
    <div class="col-sm-5">
      <h5 class="mb-1">
        <?php
        echo TABLE_HEADING_BILLING_ADDRESS;
        echo sprintf(LINK_TEXT_EDIT, 'font-weight-lighter ml-3', tep_href_link('checkout_payment_address.php', '', 'SSL'));
        ?>
      </h5>
      <div class="border">
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><?php echo PAYMENT_FA_ICON . $customer->make_address_label($_SESSION['billto'], true, ' ', '<br>'); ?>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <hr>

  <div class="form-group row">
    <label for="inputComments" class="col-form-label col-sm-4 text-sm-right"><?php echo ENTRY_COMMENTS; ?></label>
    <div class="col-sm-8"><?php echo tep_draw_textarea_field('comments', 'soft', 60, 5, $comments, 'id="inputComments" placeholder="' . ENTRY_COMMENTS_PLACEHOLDER . '"'); ?></div>
  </div>

  <?php
  $PHP_SELF='checkout_payment.php';
  echo $OSCOM_Hooks->call('siteWide', 'injectFormDisplay');
  ?>
     <div class="row">
      <div  class="col-md-8 col-sm-3">    
   <h4 class="mb-0 px-4">
        <?php 
        echo ORDER_DETAILS;
        ?>
      </h4>
      <div  id  ="result">
     <table  class="table table-hover mb-0">
            <?php
          if (MODULE_ORDER_TOTAL_INSTALLED) {
            echo $order_total_modules->output();
          }
          ?>
        </table>
    </div>
    </div>
      <div class="col-md-4 col-sm-3">
     <div class="buttonSet">
    <div class="text-right"><?php echo tep_draw_button(UPDATE_TOTAL, 'fas fa-calculator', null, 'primary', null, 'btn-success btn-lg btn-block AddItem'); ?></div>
  </div><br />
 <div class="col-12">
   <div id ="result_payment">
   <?php
     echo '<div class="col-12">';
    echo '<ul class="list-group">';
    
            echo '<li class="list-group-item">';
            echo '<h5 class="mb-1">' . HEADING_PAYMENT_METHOD . '<small></small></h5>';
            echo '<p class="w-100 mb-1">' . $order->info['payment_method'] . '</p>';
          echo '</li>';
    echo '</ul></div>';

   ?>
  </div></div><br />
<div>
 <?php 
     if (GO_TO_CONFIRMATION=="False"){
 ?>
  <label for="myCheck" class="p-5 mb-2 bg-warning text-dark col-12">
  <div>
    <input id="myCheck" class="form-check-input " type="checkbox" name="remember" required><?php echo TEXT_AGREE;?>
   </div> 
  </label>

   <?php
    }
    ?>
  </div>
 
    <div class="buttonSet ">
      <?php 
     if (PRINT_PRE_INVOICE=="True"){
 ?>
  <a  class="btn btn-outline-success  col-12" href="<?php echo tep_href_link('invoice.php', '', 'SSL'); ?>"><i class="fa fa-print" aria-hidden="true"> </i><?php echo TEXT_PRINT; ?></a> 
    <?php
    }
    ?>
    </div>
   </div>
  </div>
  <br />
  <div class="buttonSet">
   <?php 
     if (GO_TO_CONFIRMATION=="False"){
 ?>
    <div class="text-right"><?php echo tep_draw_button(TITLE_CONTINUE_CHECKOUT_PROCEDURE, 'fas fa-angle-right', null, 'primary', null, 'btn-success btn-lg btn-block '); ?></div>
   <?php
    } else {
    ?>
   <div class="text-right"><?php echo tep_draw_button(BUTTON_CONTINUE_CHECKOUT_PROCEDURE, 'fas fa-angle-right', null, 'primary', null, 'btn-success btn-lg btn-block'); ?></div>

  <?php
    }
    ?>
  </div>

  <div class="progressBarHook">
    <?php
    echo $OSCOM_Hooks->call('progress', 'progressBar', $arr = array('style' => 'progress-bar progress-bar-striped progress-bar-animated bg-info', 'markers' => array('position' => 2, 'min' => 0, 'max' => 100, 'now' => 10)));
    ?>  
  </div>

</div>

</form>

<script>
$(document).ready(function(){
 	
 	function load_data(shipping)
	{
		$.ajax({
			url:"total_ajax.php?action=process",
			method:"post",
			data:{
		    shipping:shipping
		//    action:process
			},
			success:function(data)
			{
				$('#result').html(data);
			}
		});
	}
	
	function process_bar()
	{
	  var valeurrp = 0;
	  var valeurcb = 0;
	  var valeurrs = 0;
    
	  valeurcb=$('input:checkbox[id="myCheck"]:checked').length;
	  valeurrs= $('input:radio[name="shipping"]:checked').length;
	  valeurrp= $('input:radio[name="payment"]:checked').length;
	  valeur= 33*( valeurrp+valeurcb+valeurrs);
$('.progress-bar').css('width', valeur+'%').attr('aria-valuenow', valeur).html('');
	}
	
	function load_data_payment()
	{
	  var payment =  $('input:radio[name="payment"]:checked').val();
		$.ajax({
			url:"<?php echo DIR_WS_CATALOG;?>payment_ajax.php?action=process",
			method:"post",
			data:{
		    payment:payment
		//    action:process
			},
			success:function(data)
			{
				$('#result_payment').html(data);
			}
		});
	}

	$(".AddItem").click(function(event)
    {
    event.preventDefault(); // cancel default behavior
    var shipping = $(".shipping:checked").val();
    load_data(shipping);

     });
     
    $('.table-selection3').click(function() {
    $('.table-selection3').removeClass('success').find('input').prop('checked', false);
    $(this).addClass('success').find('input').prop('checked', true); 
     var shipping =  $('input:radio[name="shipping"]:checked').val();
    load_data(shipping);
    process_bar()
	
 });

    $('.table-selection2').click(function() {
    $('.table-selection2').removeClass('success').find('input').prop('checked', false);
    $(this).addClass('success').find('input').prop('checked', true); 
    var payment =  $('input:radio[name="payment"]:checked').val();
    load_data_payment();
     process_bar()
	
 });
  $('#myCheck').change(function() {
  process_bar()
});
});
</script>
<script>$('.table-selection4').click(function() { $('.table-selection4').removeClass('success').find('input').prop('checked', false); $(this).addClass('success').find('input').prop('checked', true); });</script>

<?php
  require 'includes/template_bottom.php';
  require 'includes/application_bottom.php';
?>