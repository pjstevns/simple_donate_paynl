<?php
/*
Plugin Name: Simple Donate - pay.nl
Plugin URI: http://github.com/pjstevns/simple_donate_paynl
Description: A real simple donation widget for iDeal
Version: 0.1
Author: Paul J Stevens
Author URI: http://github.com/pjstevns/
License: GPLv2
Text Domain: simple_donate
*/

require_once 'Transaction.php';

class Simple_Donate_Paynl extends WP_Widget {
	function __construct() 
	{
		parent::__construct(
			'simple_donate_paynl',
			'Simple Donate Paynl',
			array('description' => __('A Simple Donation Widget', 'simple_donate'), )
		);
	}

	private function connect($instance)
	{
		$email_address = explode(',', trim($instance['email_address']));
		$this->conn = new Transaction(
			(int)$instance['program_id'],
			(int)$instance['website_id'],
			(int)$instance['location_id'],
			$email_address,
			(int)$instance['account_id'],
			$instance['token']
		);

		$this->conn->setTestMode($instance['debug']);
	}

	private function handle_return($instance)
	{
		$this->connect($instance);
		$result = $this->conn->getPaymentStatus($_GET['paymentSessionId']);
		if ($result['status'] == 'PAID')
			$msg = $instance['thanks'];
		else
			$msg = $instance['sorry'] . "<br/><em>" . $result['status'] . "</em>";
		printf("<p>%s</p>", $msg);
	}


	private function handle_payment($instance)
	{
		$amount = (int)((float)($_POST['amount']) * 100);
		$this->connect($instance);
		$this->conn->setExchangeUrl($instance['exchange_url']);
		$this->conn->setReturnUrl($_SERVER['HTTP_REFERER']);
		if (intval($_POST['payment_profile']) == 10)
			$this->conn->setBankId($_POST['bank_id']);

		try {
			$transaction = $this->conn->createTransaction(
				$amount,
				intval($_POST['payment_profile']),
				array('extra1' => $instance['description']));
		} catch(Exception $e) {
			echo "<p>Unable to create payment.</p>";
			echo sprintf("<p>%s</p>" . $e->getMessage());
			exit;
		}

		wp_redirect($transaction['issuerUrl']);
	}

	private function select_bank($instance)
	{
		$this->connect($instance);
		$profiles = $this->conn->getActivePaymentProfiles();
?>
		<form id="select_method_form" method="post">
		<input type="hidden" name="amount" value="<?php echo $_POST['amount']; ?>"/>
		<input type="hidden" name="payment_profile" value="10">
<?php
		if ($this->conn->isBankList($profiles)) {
			$banks = $this->conn->getIdealBanks();
			foreach($banks as $bank) {
				printf("<div><input type=\"radio\" name=\"bank_id\" value=\"%s\">" .
					"<img src=\"%s\" alt=\"%s\"></div>",
					$bank['id'], $bank['icon'], $bank['name']);
			}
		}
?>
		<input type="submit" value="<? _e('Pay', 'simple_donate')?>"?>
		</form>
<?php
	}
	public function widget($args, $instance)
	{
		extract($args);

		$title = apply_filters('widget_title', $instance['title']);
		echo $before_widget;
		if (! empty($title))
			echo $before_title . $title . $after_title;

		if ($_GET['paymentSessionId']) {
			$this->handle_return($instance);
			echo $after_widget;
			return;
		}

		if (isset($_POST['bank_id']) and ! empty($_POST['bank_id'])) {
			$this->handle_payment($instance);
		} else if (isset($_POST['amount'])) {
			$this->select_bank($instance);
		} else {
?>
		<form method="post">
		<label for="amount">&euro;</label>
		<input class="widefat" id="simple_donate_amount" name="amount"
			type="text" value="10.00" />
		<input type="submit" value="<? _e('Donate!', 'simple_donate'); ?>"/>
		</form>
<?
		}
		
		echo $after_widget;
	}

	public function form($instance)
	{
		if (isset($instance['title'])) {
			$title = $instance['title'];
		} else {
			$title = __('Title', 'simple_donate');
		}

		if (isset($instance['account_id'])) {
			$account_id = $instance['account_id'];
		} else {
			$account_id = __('Account ID', 'simple_donate');
		}
		if (isset($instance['program_id'])) {
			$program_id = $instance['program_id'];
		} else {
			$program_id = __('Program ID', 'simple_donate');
		}
		if (isset($instance['website_id'])) {
			$website_id = $instance['website_id'];
		} else {
			$website_id = __('Website ID', 'simple_donate');
		}
		if (isset($instance['location_id'])) {
			$location_id = $instance['location_id'];
		} else {
			$location_id = __('Location ID', 'simple_donate');
		}
		if (isset($instance['token'])) {
			$token = $instance['token'];
		} else {
			$token = __('Token', 'simple_donate');
		}
		if (isset($instance['email_address'])) {
			$email_address = $instance['email_address'];
		} else {
			$email_address = __('Notification address', 'simple_donate');
		}

		if (isset($instance['description'])) {
			$description = $instance['description'];
		} else {
			$description = __('Description transaction', 'simple_donate');
		}
		if (isset($instance['exchange_url'])) {
			$exchange_url = $instance['exchange_url'];
		} else {
			$exchange_url = __('Exchange URL', 'simple_donate');
		}
	
		if (isset($instance['thanks'])) {
			$thanks = $instance['thanks'];
		} else {
			$thanks = __('"Thank you" message', 'simple_donate');
		}
		if (isset($instance['sorry'])) {
			$sorry = $instance['sorry'];
		} else {
			$sorry = __('"Sorry" message', 'simple_donate');
		}
		if (isset($instance['debug'])) {
			$debug = $instance['debug'];
		} else {
			$debug = true;
		}


?>
	<p> <label for="<?php echo $this->get_field_name('title'); ?>"><?php _e('Title', 'simple_donate'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id('title');?>" name="<?php echo $this->get_field_name('title'); ?>"
		type="text" value="<?php echo esc_attr($title); ?>" /></p>

	<p><label for="<?php echo $this->get_field_name('account_id'); ?>"><?php _e('Account ID', 'simple_donate'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id('account_id');?>" name="<?php echo $this->get_field_name('account_id'); ?>"
		type="text" value="<?php echo esc_attr($account_id); ?>" /></p>

	<p><label for="<?php echo $this->get_field_name('program_id'); ?>"><?php _e('Program ID', 'simple_donate'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id('program_id');?>" name="<?php echo $this->get_field_name('program_id'); ?>"
		type="text" value="<?php echo esc_attr($program_id); ?>" /></p>

	<p><label for="<?php echo $this->get_field_name('website_id'); ?>"><?php _e('Website ID', 'simple_donate'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id('website_id');?>" name="<?php echo $this->get_field_name('website_id'); ?>"
		type="text" value="<?php echo esc_attr($website_id); ?>" /></p>

	<p><label for="<?php echo $this->get_field_name('location_id'); ?>"><?php _e('Location ID', 'simple_donate'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id('location_id');?>" name="<?php echo $this->get_field_name('location_id'); ?>"
		type="text" value="<?php echo esc_attr($location_id); ?>" /></p>

	<p><label for="<?php echo $this->get_field_name('token'); ?>"><?php _e('Token', 'simple_donate'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id('token');?>" name="<?php echo $this->get_field_name('token'); ?>"
		type="text" value="<?php echo esc_attr($token); ?>" /></p>

	<p><label for="<?php echo $this->get_field_name('email_address'); ?>"><?php _e('E-mail address', 'simple_donate'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id('email_address');?>" name="<?php echo $this->get_field_name('email_address'); ?>"
		type="text" value="<?php echo esc_attr($email_address); ?>" /></p>

	<p> <label for="<?php echo $this->get_field_name('description'); ?>"><?php _e('Description transaction', 'simple_donate'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id('description');?>" name="<?php echo $this->get_field_name('description'); ?>"
		type="text" value="<?php echo esc_attr($description); ?>" /></p>

	<p><label for="<?php echo $this->get_field_name('exchange_url'); ?>"><?php _e('Exchange URL', 'simple_donate'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id('exchange_url');?>" name="<?php echo $this->get_field_name('exchange_url'); ?>"
		type="text" value="<?php echo esc_attr($exchange_url); ?>" /></p>

	<p> <label for="<?php echo $this->get_field_name('thanks'); ?>"><?php _e('"Thank you" message', 'simple_donate'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id('thanks');?>" name="<?php echo $this->get_field_name('thanks'); ?>"
		type="text" value="<?php echo esc_attr($thanks); ?>" /></p>

	<p><label for="<?php echo $this->get_field_name('sorry'); ?>"><?php _e('"Sorry" message', 'simple_donate'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id('sorry');?>" name="<?php echo $this->get_field_name('sorry'); ?>"
		type="text" value="<?php echo esc_attr($sorry); ?>" /></p>

	<p><label for="<?php echo $this->get_field_name('debug'); ?>"><?php _e('Test mode', 'simple_donate'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id('debug');?>" name="<?php echo $this->get_field_name('debug'); ?>"
		type="checkbox" <?php echo $debug?"checked":"" ?> /></p>
<?
	}

	public function update($new_instance, $old_instance)
	{
		$instance = array();
		$instance['title'] = (! empty($new_instance['title'])) ? strip_tags($new_instance['title']): '';
		$instance['account_id'] = (! empty($new_instance['account_id'])) ? strip_tags($new_instance['account_id']): '';
		$instance['program_id'] = (! empty($new_instance['program_id'])) ? strip_tags($new_instance['program_id']): '';
		$instance['website_id'] = (! empty($new_instance['website_id'])) ? strip_tags($new_instance['website_id']): '';
		$instance['location_id'] = (! empty($new_instance['location_id'])) ? strip_tags($new_instance['location_id']): '';
		$instance['token'] = (! empty($new_instance['token'])) ? strip_tags($new_instance['token']): '';
		$instance['email_address'] = (! empty($new_instance['email_address'])) ? strip_tags($new_instance['email_address']): '';
		$instance['exchange_url'] = (! empty($new_instance['exchange_url'])) ? strip_tags($new_instance['exchange_url']): '';
		$instance['description'] = (! empty($new_instance['description'])) ? strip_tags($new_instance['description']): '';
		$instance['thanks'] = (! empty($new_instance['thanks'])) ? strip_tags($new_instance['thanks']): '';
		$instance['sorry'] = (! empty($new_instance['sorry'])) ? strip_tags($new_instance['sorry']): '';
		$instance['debug'] = (! empty($new_instance['debug'])) ? true : false;
		return $instance;
	}
}

function paynl_ob_start() {
	ob_start();
}

function paynl_register_widget()
{
	register_widget('Simple_Donate_Paynl');
}

// no lambda/closures here to allow php < 5.3
add_action('init', 'paynl_ob_start');
add_action('widgets_init', 'paynl_register_widget');

$plugin_dir = basename(dirname(__FILE__));
load_plugin_textdomain('simple_donate', null, $plugin_dir . '/i18n');

?>
