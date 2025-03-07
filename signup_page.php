<?php
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Sign Up Page
 * @package MantisBT
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses core.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses crypto_api.php
 * @uses form_api.php
 * @uses helper_api.php
 * @uses html_api.php
 * @uses lang_api.php
 * @uses print_api.php
 * @uses utility_api.php
 */

require_once( 'core.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'crypto_api.php' );
require_api( 'form_api.php' );
require_api( 'helper_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'utility_api.php' );

require_css( 'login.css' );

require_js( 'login.js' );

$t_form_title = lang_get( 'signup_title' );

# Check for invalid access to signup page
if( !auth_signup_enabled() || LDAP == config_get_global( 'login_method' ) ) {
	print_header_redirect( auth_login_page() );
}

layout_login_page_begin( $t_form_title );

$t_public_key = crypto_generate_uri_safe_nonce( 64 );
?>

<div class="col-md-offset-3 col-md-6 col-sm-10 col-sm-offset-1">
<div class="login-container">
	<div class="space-12 hidden-480"></div>
	<?php layout_login_page_logo() ?>
	<div class="space-24 hidden-480"></div>

	<div class="position-relative">

		<div class="signup-box visible widget-box no-border" id="login-box">
			<div class="widget-body">
				<div class="widget-main">
					<h4 class="header lighter bigger">
						<?php print_icon( 'fa-pencil', 'ace-icon' ); ?>
						<?php echo $t_form_title ?>
					</h4>
					<div class="space-10"></div>

	<form id="signup-form" method="post" action="signup.php">
		<fieldset>
			<?php echo form_security_field( 'signup' ); ?>

			<label for="username" class="block clearfix">
				<span class="block input-icon input-icon-right">
					<input id="username" name="username" type="text" placeholder="<?php echo lang_get( 'username' ) ?>"
						size="32" maxlength="<?php echo DB_FIELD_SIZE_USERNAME;?>" class="form-control autofocus">
					<?php print_icon( 'fa-user', 'ace-icon' ); ?>
				</span>
			</label>

			<label for="email-field" class="block clearfix">
				<span class="block input-icon input-icon-right">
					<input id="email-field" name="email" type="text" placeholder="<?php echo lang_get( 'email_label' ) ?>"
						size="32" maxlength="64" class="form-control">
					<?php print_icon( 'fa-envelope', 'ace-icon' ); ?>
				</span>
			</label>

<?php
	$t_allow_passwd_change = helper_call_custom_function( 'auth_can_change_password', array() );

	# captcha image requires GD library and related option to ON
	if( ON == config_get( 'signup_use_captcha' ) && get_gd_version() > 0 && $t_allow_passwd_change ) {
		$t_securimage_path = 'vendor/dapphp/securimage';
		$t_securimage_show = $t_securimage_path . '/securimage_show.php';
		# Unique id prevents caching issues with audio captcha
		$t_securimage_play = $t_securimage_path . '/securimage_play.php?id=' . uniqid();
		$t_label_captcha_refresh = lang_get( 'signup_captcha_refresh' );
		$t_label_captcha_play = lang_get( 'signup_captcha_play' );
?>
				<script src="<?php echo $t_securimage_path . '/securimage.js'; ?>"></script>

				<label for="captcha-field" class="block clearfix">
					<strong><?php echo lang_get( 'signup_captcha_request_label' ); ?></strong>
				</label>

				<div id="captcha-input" class="input">
					<?php print_captcha_input( 'captcha' ); ?>

					<div id="captcha-image" class="captcha-image">
						<img src="<?php echo $t_securimage_show; ?>" alt="visual captcha"
							 title="<?php echo $t_label_captcha_refresh; ?>"
						/>
					</div>

					<div id="captcha_image_audio_div">
						<audio id="captcha_image_audio" preload="none">
							<source id="captcha_image_source_mp3" src="<?php echo $t_securimage_play . '&format=mpeg'; ?>" type="audio/mpeg">
							<source id="captcha_image_source_wav" src="<?php echo $t_securimage_play; ?>" type="audio/wav">
						</audio>
					</div>
					<div id="captcha_image_audio_controls">
						<a class="captcha_play_button" href="<?php echo $t_securimage_play; ?>"
						   title="<?php echo $t_label_captcha_play; ?>"
						   aria-label="<?php echo $t_label_captcha_play; ?>"
						>
							<?php print_icon( 'volume-up', 'ace-icon bigger-250 captcha_play_image'); ?>
							<?php print_icon( 'spinner', 'ace-icon bigger-250 captcha_loading_image" style="display: none'); ?>
						</a>
						<noscript>Enable Javascript for audio controls</noscript>
					</div>
					<a id="captcha-refresh" href="#"
						title="<?php echo $t_label_captcha_refresh; ?>"
						aria-label="<?php echo $t_label_captcha_refresh; ?>"
					>
						<?php print_icon( 'refresh', 'ace-icon bigger-250' ); ?>
					</a>
				</div>
<?php
			} // endif use captcha

			if( !$t_allow_passwd_change ) {
				echo '<div class="space-10"></div>';
				echo '<div class="alert alert-danger">';
				echo lang_get( 'no_password_change' );
				echo '</div>';
			}
?>

			<div class="clearfix"></div>
			<div class="space-10"></div>
			<?php echo lang_get( 'signup_info' ); ?>
			<div class="space-10"></div>

			<input type="submit" class="width-40 pull-right btn btn-success btn-inverse bigger-110" value="<?php echo lang_get( 'signup_button' ) ?>" />
		</fieldset>
	</form>
				</div>

				<div class="toolbar center">
					<a class="back-to-login-link pull-left" href="<?php echo AUTH_PAGE_USERNAME; ?>">
						<?php echo lang_get( 'login' ); ?>
					</a>
<?php
		# lost password feature disabled or reset password via email disabled
		if( ( LDAP != config_get_global( 'login_method' ) ) &&
			( ON == config_get( 'lost_password_feature' ) ) &&
			( ON == config_get( 'send_reset_password' ) ) &&
			( ON == config_get( 'enable_email_notification' ) ) ) {
?>
					<a class="back-to-login-link pull-right" href="lost_pwd_page.php">
						<?php echo lang_get( 'lost_password_link' ); ?>
					</a>
<?php } ?>
					<div class="clearfix"></div>
				</div>
			</div>
		</div>
	</div>
</div>

</div>

<?php
layout_login_page_end();
