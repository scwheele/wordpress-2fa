<?php
/*
 * Plugin Name: 2fa
 * Plugin URI: https://swheeler.co
 * Description: Adds basic 2 factor authentication to your wordpress site
 * Version: 0.1
 * Author: Scott Wheeler
 * Author URI: https://swheeler.co
*/

function destory_2fa_sessions() {
  if(isset($_COOKIE['verified'])) {
    unset($_COOKIE['verified']);
  }
}

add_action('wp_logout', destory_2fa_sessions);

function generate_verification_page() {
  if(get_page_by_title('Verify') == NULL) {
    $verify_page = array(
      'post_type' => 'page',
      'post_title' => 'Verify',
      'post_content' => "",
      'post_status' => 'publish',
      'post_author' => 1,
      'post_slug' => 'verify'
    );
    wp_insert_post($verify_page);
  }
}

add_action('init', 'generate_verification_page');

function verify_user_2fa() {
  if(is_page('verify')) {
    if(!is_user_logged_in()) {
      wp_redirect(get_home_url());
      return false;
    }

    if(!ISSET($_GET['USER_ID']) && !isset($_GET['VCode'])) {
      wp_redirect(get_home_url());
      return false;
    }

    $User_ID = $_GET['User_ID'];
    $VCode = $_GET['VCode'];

    $User = wp_get_current_user();
    if(!(esc_attr(get_the_author_meta('2fa_enabled', $User->ID)) === "on")) {
      wp_redirect(get_home_url());
      return false;
    }

    if($User->ID != $User_ID) {
      wp_redirect(get_home_url());
      return false;
    }

    if((esc_attr(get_the_author_meta('2fa_code', $User->ID))) != $VCode) {
      wp_redirect(get_home_url());
      return false;
    }

    setcookie('verified', '1', time() + (86400 * 30), '/');
    wp_redirect(get_admin_url());

  }
}

add_action('get_header', 'verify_user_2fa');

function is_user_on_admin_page() {
  $user = wp_get_current_user();
  if(is_admin() && (esc_attr(get_the_author_meta('2fa_enabled', $user->ID)) === "on")) {
    if(!isset($_COOKIE['verified'])) {
      wp_logout();
      wp_redirect(wp_login_url());
    }
    if($_COOKIE['verified'] == 0) {
      wp_redirect(get_home_url());
    }
  }
}

add_action('init', 'is_user_on_admin_page');

function display_2fa_page($user_login, $user) {
    if(esc_attr(get_the_author_meta('2fa_enabled', $user->ID)) === "on") {
      $randomCode = rand(111111,999999);
      update_usermeta($user->ID, '2fa_code', $randomCode);
      $user_email = $user->user_email;
      $user_name = $user->display_name;

      mail($user_email, "2FA Request at " . get_bloginfo('name'), "Please follow the link to finish login: " . get_home_url() . "/verify?User_ID=" . $user->ID . "&VCode=" . $randomCode);
      setcookie('verified', '0', time() + (86400 * 30));
    }
}

add_action('wp_login', 'display_2fa_page', 10, 2);

function save_enroll_in_2fa($user_id) {
  if(!current_user_can('edit_user', $user_id)) {
    return false;
  }

  update_usermeta($user_id, '2fa_enabled', $_POST["2fa"]);
}

add_action('personal_options_update', 'save_enroll_in_2fa');
add_action('edit_user_profile_update', 'save_enroll_in_2fa');

function enroll_in_2fa($user) {
  ?> <h3>Enable 2 Factor Authentication</h3>
  <?php echo esc_attr(get_the_author_meta('2fa_enabled', $user->ID)); ?>
  <table class="form-table">
    <tr>
      <th><label for="2fa">Enable?</label></th>
      <td>
        <input type="checkbox" name="2fa" <?php if(esc_attr(get_the_author_meta('2fa_enabled', $user->ID)) == "on") echo "checked"; ?>>
        <span class="description">Enable 2FA?</span>
      </td>
    </tr>
  </table>
<?php }

add_action('show_user_profile', 'enroll_in_2fa');
add_action('edit_user_profile', 'enroll_in_2fa');
