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
 * User Edit Page
 *
 * @package MantisBT
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses core.php
 * @uses access_api.php
 * @uses authentication_api.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses error_api.php
 * @uses form_api.php
 * @uses gpc_api.php
 * @uses helper_api.php
 * @uses html_api.php
 * @uses lang_api.php
 * @uses print_api.php
 * @uses string_api.php
 * @uses user_api.php
 * @uses utility_api.php
 *
 * @noinspection PhpUnhandledExceptionInspection
 */

require_once( 'core.php' );
require_api( 'access_api.php' );
require_api( 'authentication_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'error_api.php' );
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'helper_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'string_api.php' );
require_api( 'user_api.php' );
require_api( 'utility_api.php' );

auth_reauthenticate();

access_ensure_global_level( config_get( 'manage_user_threshold' ) );

$f_username = gpc_get_string( 'username', '' );

if( is_blank( $f_username ) ) {
	$t_user_id = gpc_get_int( 'user_id' );
} else {
	$t_user_id = user_get_id_by_name( $f_username );
	if( $t_user_id === false ) {
		# If we can't find the user by name, attempt to find by email.
		$t_user_id = user_get_id_by_email( $f_username );
		if( $t_user_id === false ) {
			# If we can't find the user by email, attempt to find by realname.
			$t_user_id = user_get_id_by_realname( $f_username );
			if( $t_user_id === false ) {
				error_parameters( $f_username );
				trigger_error( ERROR_USER_BY_NAME_NOT_FOUND, ERROR );
			}
		}
	}
}

$t_user = user_get_row( $t_user_id );
if( !$t_user ) {
	error_parameters( $t_user_id );
	trigger_error( ERROR_USER_BY_ID_NOT_FOUND, ERROR);
}

# Ensure that the account to be updated is of equal or lower access to the
# current user.
access_ensure_global_level( $t_user['access_level'] );

$t_ldap = ( LDAP == config_get_global( 'login_method' ) );

# User action buttons: RESET/UNLOCK, IMPERSONATE and DELETE
$t_reset = $t_user['id'] != auth_get_current_user_id()
	&& auth_can_set_password( $t_user['id'] )
	&& user_is_enabled( $t_user['id'] )
	&& !user_is_protected( $t_user['id'] );
$t_unlock = !user_is_login_request_allowed( $t_user['id'] );
$t_delete = !( user_is_administrator( $t_user_id )
	&& user_count_level( config_get_global( 'admin_site_threshold' ) ) <= 1
);
$t_impersonate = auth_can_impersonate( $t_user['id'] );
$t_email_notification_enabled = ON == config_get( 'enable_email_notification' );
$t_reset_password_msg = lang_get(
	( ON == config_get( 'send_reset_password' ) && $t_email_notification_enabled )
	? 'reset_password_msg'
	: 'reset_password_msg2'
);

layout_page_header();
layout_page_begin( 'manage_overview_page.php' );

print_manage_menu( 'manage_user_page.php' );
?>

<div class="col-md-12 col-xs-12">
	<div class="space-10"></div>

<!-- EDIT USER INFO SECTION -->
<div id="edit-user-div" class="form-container">
<form id="edit-user-form" method="post" action="manage_user_update.php">
	<?php echo form_security_field( 'manage_user_update' ) ?>
	<input type="hidden" name="user_id" value="<?php echo $t_user['id'] ?>" />

	<div class="widget-box widget-color-blue2">
		<div class="widget-header widget-header-small">
			<h4 class="widget-title lighter">
				<?php print_icon( 'fa-user', 'ace-icon' ); ?>
				<?php echo lang_get('edit_user_title') ?>
			</h4>
		</div>

		<div class="widget-body">
			<div class="widget-main no-padding table-responsive">
				<table class="table table-bordered table-condensed table-striped">

					<!-- Username -->
					<tr>
						<td class="category width-30">
							<label for="edit-username">
								<?php echo lang_get( 'username_label' ) ?>
							</label>
						</td>
						<td>
							<input id="edit-username" name="username"
								   type="text" class="input-sm"
								   size="32" maxlength="<?php echo DB_FIELD_SIZE_USERNAME;?>"
								   value="<?php echo string_attribute( $t_user['username'] ) ?>"
							/>
						</td>
					</tr>

					<!-- Realname -->
					<tr>
<?php
	if( $t_ldap && ON == config_get_global( 'use_ldap_realname' ) ) {
		# With LDAP
?>
						<td class="category">
							<?php echo lang_get( 'realname_label' ) ?>
						</td>
						<td>
							<?php echo string_display_line( user_get_realname( $t_user_id ) ) ?>
						</td>
<?php
	} else {
		# Without LDAP
?>
						<td class="category">
							<label for="edit-realname">
								<?php echo lang_get( 'realname_label' ) ?>
							</label>
						</td>
						<td>
							<input id="edit-realname" name="realname"
								   type="text" class="input-sm"
								   size="32" maxlength="<?php echo DB_FIELD_SIZE_REALNAME;?>"
								   value="<?php echo string_attribute( $t_user['realname'] ) ?>"
							/>
						</td>
<?php
	}
?>
					</tr>

					<!-- Email -->
					<tr>
<?php
	if( $t_ldap && ON == config_get_global( 'use_ldap_email' ) ) {
		# With LDAP
?>
						<td class="category">
							<?php echo lang_get( 'email_label' ) ?>
						</td>
						<td>
							<?php echo string_display_line( user_get_email( $t_user_id ) ) ?>
						</td>
<?php
	} else {
		# Without LDAP
?>
						<td class="category">
							<label for="edit-realname">
								<?php echo lang_get( 'email_label' ) ?>
							</label>
						</td>
						<td>
<?php
							print_email_input( 'email', $t_user['email'] );
							if( config_get_global( 'email_ensure_unique' )
								&& !user_is_email_unique( $t_user['email'], $t_user_id )
							) {
								echo '<span class="padding-8">';
								print_icon('fa-exclamation-triangle',
									'ace-icon bigger-125 red  padding-right-4'
								);
								echo lang_get( 'email_not_unique' );
								echo '</span>';
							}
?>
						</td>
<?php
	}
?>
					</tr>

					<!-- Access Level -->
					<tr>
						<td class="category">
							<label for="edit-access-level">
								<?php echo lang_get( 'access_level_label' ) ?>
							</label>
						</td>
						<td>
							<select id="edit-access-level" name="access_level" class="input-sm">
<?php
	$t_access_level = $t_user['access_level'];
	if( !MantisEnum::hasValue( config_get( 'access_levels_enum_string' ), $t_access_level ) ) {
		$t_access_level = config_get( 'default_new_account_access_level' );
	}
	print_project_access_levels_option_list( (int)$t_access_level );
?>
							</select>
						</td>
					</tr>

					<!-- Enabled Checkbox -->
					<tr>
						<td class="category">
							<?php echo lang_get( 'enabled_label' ) ?>
						</td>
						<td>
							<label>
								<input id="edit-enabled" name="enabled"
									   type="checkbox" class="ace"
									   <?php check_checked( (int)$t_user['enabled'], ON ); ?>
								/>
								<span class="lbl"></span>
							</label>
						</td>
					</tr>

					<!-- Protected Checkbox -->
					<tr>
						<td class="category">
							<?php echo lang_get( 'protected_label' ) ?>
						</td>
						<td>
							<label>
								<input id="edit-protected" name="protected"
									   type="checkbox" class="ace"
									   <?php check_checked( (int)$t_user['protected'], ON ); ?>
								/>
								<span class="lbl"></span>
							</label>
						</td>
					</tr>

					<?php event_signal( 'EVENT_MANAGE_USER_UPDATE_FORM', array( $t_user['id'] ) ); ?>
				</table>
			</div>
		</div>

		<div class="widget-toolbox padding-8 clearfix">
			<button class="btn btn-primary btn-white btn-round">
				<?php echo lang_get( 'update_user_button' ) ?>
			</button>
<?php
	if( $t_email_notification_enabled ) {
?>
			&nbsp;
			<label class="inline">
				<input id="send-email" name="send_email_notification"
					   type="checkbox" class="ace" checked="checked"
				/>
				<span class="lbl">
					<?php echo lang_get( 'notify_user' ) ?>
				</span>
			</label>
<?php } ?>
			<div class="btn-group pull-right">
<?php
	# Information button
	print_link_button( 'view_user_page.php?id=' . $t_user['id'],
		lang_get( 'view_account_title' ),
		"btn btn-primary btn-white btn-round pull-left"
	);

	# Impersonate Button
	if( $t_impersonate ) {
		echo form_security_field( 'manage_user_impersonate' )
?>
				<button formaction="manage_user_impersonate.php"
						class="btn btn-primary btn-white btn-round">
					<?php echo lang_get( 'impersonate_user_button' ) ?>
				</button>
<?php
	}

	# Reset/Unlock Button
	if( $t_reset || $t_unlock ) {
?>
				<button formaction="manage_user_reset.php"
						title="<?php echo $t_reset_password_msg ?>"
						class="btn btn-primary btn-white btn-round">
					<?php echo lang_get( $t_reset ? 'reset_password_button' : 'account_unlock_button' ) ?>
				</button>
<?php
	}

	# Delete Button
	if( $t_delete ) {
?>

				<button formaction="manage_user_delete.php"
						class="btn btn-primary btn-white btn-round">
					<?php echo lang_get( 'delete_user_button' ) ?>
				</button>
<?php } ?>

			</div>
		</div>
	</div>
</form>
</div>

<?php event_signal( 'EVENT_MANAGE_USER_PAGE', array( $t_user_id ) ); ?>

<?php
# Project access sections are only shown if the current user's permissions
# allow and the user being edited is not an Administrator.
if( access_has_global_level( config_get( 'manage_user_threshold' ) )
	&& !user_is_administrator( $t_user_id )
) {

	$t_projects = user_get_assigned_projects( $t_user['id'] );
	if( !empty( $t_projects ) ) {
?>
<!-- ASSIGNED PROJECTS SECTION -->
<div class="space-10"></div>
<div id="project-access-div" class="form-container">
<form id="project-access-form" method="post" action="manage_user_proj_delete.php">

	<?php echo form_security_field( 'manage_user_proj_delete' ) ?>
	<input name="user_id" type="hidden" value="<?php echo (int)$t_user['id'] ?>" />

	<div class="widget-box widget-color-blue2">
		<div class="widget-header widget-header-small">
			<h4 class="widget-title lighter">
				<?php print_icon( 'fa-puzzle-piece', 'ace-icon' ); ?>
				<?php echo lang_get( 'assigned_projects_label' ) ?>
			</h4>
		</div>
		<div class="widget-body">
			<div class="widget-main no-padding table-responsive">
				<table class="table table-bordered table-condensed table-striped">
					<thead>
						<tr>
							<th><?php echo lang_get( 'remove_link' ) ?></th>
							<th><?php echo lang_get( 'project_name' ) ?></th>
							<th><?php echo lang_get( 'access_level' ) ?></th>
							<th><?php echo lang_get( 'view_status' ) ?></th>
						</tr>
					</thead>
					<tbody>
<?php
		$t_projects = user_get_assigned_projects( $t_user['id'] );
		foreach( $t_projects as $t_project_id => $t_project ) {
			$t_can_remove = access_has_project_level( config_get( 'project_user_threshold' ), $t_project_id );
			$t_project_name = string_attribute( $t_project['name'] );
			$t_access_level = get_enum_element( 'access_levels', $t_project['access_level'] );
			$t_view_state = get_enum_element( 'project_view_state', $t_project['view_state'] );
?>
						<tr>
							<td class="center">
<?php
			if( $t_can_remove ) {
?>
								<!--suppress HtmlFormInputWithoutLabel -->
								<input name="project_id[]" type="checkbox" class="ace"
									   value="<?php echo $t_project_id ?>"
								/>
								<span class="lbl"></span>
<?php
			}
?>
							</td>
							<td><?php echo $t_project_name ?></td>
							<td><?php echo $t_access_level ?></td>
							<td><?php echo $t_view_state ?></td>
						</tr>
<?php
		} # foreach project
?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="widget-toolbox padding-8 clearfix">
			<label>
				<input id="project_id_all" name="project_id_all"
					   type="checkbox" class="ace check_all"
				/>
				<span class="lbl">
					<?php echo lang_get( 'select_all' ) ?>
				</span>
			</label>
			&nbsp;
			<button class="btn btn-primary btn-white btn-round">
				<?php echo lang_get( 'remove_link' ) ?>
			</button>
		</div>
	</div>
</form>
</div>
<?php
	} # end if user has assigned projects
?>

<!-- ADD USER TO PROJECT SECTION -->
<div class="space-10"></div>
<div id="manage-user-project-add-div" class="form-container">
<form id="manage-user-project-add-form" method="post" action="manage_user_proj_add.php">
	<?php echo form_security_field( 'manage_user_proj_add' ) ?>
	<input type="hidden" name="user_id" value="<?php echo $t_user['id'] ?>" />

	<div class="widget-box widget-color-blue2">

		<div class="widget-header widget-header-small">
			<h4 class="widget-title lighter">
				<?php print_icon( 'fa-puzzle-piece', 'ace-icon' ); ?>
				<?php echo lang_get( 'add_user_title' ) ?>
			</h4>
		</div>

		<div class="widget-body">
			<div class="widget-main no-padding table-responsive">
				<table class="table table-bordered table-condensed table-striped">
					<tr>
						<td class="category width-30">
							<label for="add-user-project-id">
								<?php echo lang_get( 'unassigned_projects_label' ) ?>
							</label>
						</td>
						<td>
							<select id="add-user-project-id" name="project_id[]"
									class="input-sm" multiple="multiple" size="5">
								<?php print_project_user_list_option_list2( $t_user['id'] ) ?>
							</select>
						</td>
					</tr>
					<tr>
						<td class="category">
							<label for="add-user-project-access">
								<?php echo lang_get( 'access_level_label' ) ?>
							</label>
						</td>
						<td>
							<select id="add-user-project-access" name="access_level"
									class="input-sm">
								<?php print_project_access_levels_option_list(
										(int)config_get( 'default_new_account_access_level' ) )
								?>
							</select>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<div class="widget-toolbox padding-8 clearfix">
			<button class="btn btn-primary btn-white btn-round">
				<?php echo lang_get( 'add_user_button' ) ?>
			</button>
		</div>
	</div>
</form>
</div>

<?php
} # End of PROJECT ACCESS conditional section
?>

<!-- ACCOUNT PREFERENCES -->
<?php
define( 'ACCOUNT_PREFS_INC_ALLOW', true );
include( __DIR__ . '/account_prefs_inc.php' );
edit_account_prefs(
	$t_user['id'],
	false,
	false,
	'manage_user_edit_page.php?user_id=' . $t_user_id
);
?>

</div>

<?php
layout_page_end();
