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
 * User Page
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
 * @uses database_api.php
 * @uses gpc_api.php
 * @uses helper_api.php
 * @uses html_api.php
 * @uses icon_api.php
 * @uses lang_api.php
 * @uses print_api.php
 * @uses string_api.php
 * @uses utility_api.php
 */

require_once( 'core.php' );
require_api( 'access_api.php' );
require_api( 'authentication_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'database_api.php' );
require_api( 'gpc_api.php' );
require_api( 'helper_api.php' );
require_api( 'html_api.php' );
require_api( 'icon_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'string_api.php' );
require_api( 'utility_api.php' );

auth_reauthenticate();

access_ensure_global_level( config_get( 'manage_user_threshold' ) );

$t_cookie_name = config_get( 'manage_users_cookie' );
$t_lock_image = icon_get( 'fa-lock', 'fa-lg', lang_get( 'protected' ) );

$f_save = gpc_get_bool( 'save' );
$f_filter = gpc_get_string( 'filter', 'ALL' );
$f_search = gpc_get_string( 'search', '');
$f_page_number   = gpc_get_int( 'page_number', 1 );

if( !$f_save && !is_blank( gpc_get_cookie( $t_cookie_name, '' ) ) ) {
	$t_manage_arr = explode( ':', gpc_get_cookie( $t_cookie_name ) );

	# Hide Inactive
	$f_hide_inactive = (bool)$t_manage_arr[0];

	# Sort field
	$f_sort = $t_manage_arr[1] ?? 'username';

	# Sort order
	$f_dir = $t_manage_arr[2] ?? 'DESC';

	# Show Disabled
	$f_show_disabled = $t_manage_arr[3] ?? false;
} else {
	$f_sort          = gpc_get_string( 'sort', 'username' );
	$f_dir           = gpc_get_string( 'dir', 'ASC' );
	$f_hide_inactive = gpc_get_bool( 'hideinactive' );
	$f_show_disabled = gpc_get_bool( 'showdisabled' );
}

# Clean up the form variables
if( !db_field_exists( $f_sort, db_get_table( 'user' ) ) ) {
	$c_sort = 'username';
} else {
	$c_sort = addslashes( $f_sort );
}

$c_dir = ( $f_dir == 'ASC' ) ? 'ASC' : 'DESC';

# OFF = show inactive users, anything else = hide them
$c_hide_inactive = ( $f_hide_inactive == OFF ) ? OFF : ON;
$t_hide_inactive_filter = '&amp;hideinactive=' . $c_hide_inactive;

# OFF = hide disabled users, anything else = show them
$c_show_disabled = ( $f_show_disabled == OFF ) ? OFF : ON;
$t_show_disabled_filter = '&amp;showdisabled=' . $c_show_disabled;

# set cookie values for hide inactive, sort by, dir and show disabled
if( $f_save ) {
	$t_manage_string = $c_hide_inactive.':'.$c_sort.':'.$c_dir.':'.$c_show_disabled;
	gpc_set_cookie( $t_cookie_name, $t_manage_string, true );
}

layout_page_header( lang_get( 'manage_users_link' ) );

layout_page_begin( 'manage_overview_page.php' );

print_manage_menu( 'manage_user_page.php' );

# New Accounts Form BEGIN

$t_days_old = 7 * SECONDS_PER_DAY;
$t_query = 'SELECT COUNT(*) AS new_user_count FROM {user}
	WHERE ' . db_helper_compare_time( db_param(), '<=', 'date_created', $t_days_old );
$t_result = db_query( $t_query, array( db_now() ) );
$t_row = db_fetch_array( $t_result );
$t_new_user_count = $t_row['new_user_count'];

# Never Logged In Form BEGIN

$t_query = 'SELECT COUNT(*) AS unused_user_count FROM {user}
	WHERE ( login_count = 0 ) AND ( date_created = last_visit )';
$t_result = db_query( $t_query );
$t_row = db_fetch_array( $t_result );
$t_unused_user_count = $t_row['unused_user_count'];

# Manage Form BEGIN

$t_prefix_array = array();

$t_prefix_array['ALL'] = lang_get( 'filter_all' );

for( $i = 'A'; $i != 'AA'; $i++ ) {
	$t_prefix_array[$i] = $i;
}

for( $i = 0; $i <= 9; $i++ ) {
	$t_prefix_array[(string)$i] = (string)$i;
}
$t_prefix_array['UNUSED'] = lang_get( 'filter_unused' );
$t_prefix_array['NEW'] = lang_get( 'filter_new' );
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>
<div class="center">
	<div class="btn-toolbar inline">
		<div class="btn-group">

<?php
foreach ( $t_prefix_array as $t_prefix => $t_caption ) {
	if( $t_prefix === 'UNUSED' ) {
		$t_search = '';
		$t_title = ' title="[' . $t_unused_user_count . '] (' . lang_get( 'never_logged_in_title' ) . ')"';
	} else if( $t_prefix === 'NEW' ) {
		$t_search = '';
		$t_title = ' title="[' . $t_new_user_count . '] (' . lang_get( '1_week_title' ) . ')"';
	} else {
		$t_search = $f_search;
		$t_title = '';
	}
	$t_active = (string)$t_prefix === $f_filter ? 'active' : '';
		print_manage_user_sort_link( 'manage_user_page.php',
			$t_caption,
			$c_sort,
			$c_dir, null, $c_hide_inactive, $t_prefix, $t_search, $c_show_disabled,
			'btn btn-xs btn-white btn-primary ' . $t_active );
}
?>
		</div>
	</div>
</div>
<div class="space-10"></div>

<?php
$t_where_params = array();
if( $f_filter === 'ALL' ) {
	$t_where = '(1 = 1)';
} else if( $f_filter === 'UNUSED' ) {
	$t_where = '(login_count = 0) AND ( date_created = last_visit )';
} else if( $f_filter === 'NEW' ) {
	$t_where = db_helper_compare_time( db_param(), '<=', 'date_created', $t_days_old );
	$t_where_params[] = db_now();
} else {
	$t_where_params[] = $f_filter . '%';
	$t_where = db_helper_like( 'username' );
}

if( $f_search !== '' ) {
	# break up search terms by spacing or quoting
	preg_match_all( "/-?([^'\"\s]+|\"[^\"]+\"|'[^']+')/", $f_search, $t_matches, PREG_SET_ORDER );

	# organize terms without quoting, paying attention to negation
	$t_search_terms = array();
	foreach( $t_matches as $t_match ) {
		$t_search_terms[trim( $t_match[1], "\'\"" )] = ( $t_match[0][0] == '-' );
	}

	# build a big where-clause and param list for all search terms, including negations
	$t_first = true;
	foreach( $t_search_terms as $t_search_term => $t_negate ) {
		if( $t_first ) {
			$t_where .= ' AND ( ';
			$t_first = false;
		} else {
			$t_where .= ' AND ';
		}

		if( $t_negate ) {
			$t_where .= 'NOT ';
		}

		$c_search = '%' . $t_search_term . '%';
		$t_where .= '( ' . db_helper_like( 'realname' ) .
			' OR ' . db_helper_like( 'username' ) .
			' OR ' . db_helper_like( 'email' );

		$t_where_params[] = $c_search;
		$t_where_params[] = $c_search;
		$t_where_params[] = $c_search;

		$t_where .= ' )';
	}
	if( !$t_first ) {
		$t_where .= ' )';
	}
}

$p_per_page = 50;

$t_offset = ( ( $f_page_number - 1 ) * $p_per_page );

$t_total_user_count = 0;

# Get the user data in $c_sort order
$t_result = '';

if( ON != $c_show_disabled ) {
	$t_where .= ' AND enabled = ' . db_param();
	$t_where_params[] = true;
}

if( OFF != $c_hide_inactive ) {
	$t_where .= ' AND ' . db_helper_compare_time( db_param(), '<', 'last_visit', $t_days_old );
	$t_where_params[] = db_now();
}

$t_query = 'SELECT count(*) as user_count FROM {user} WHERE ' . $t_where;
$t_result = db_query( $t_query, $t_where_params );
$t_row = db_fetch_array( $t_result );
$t_total_user_count = $t_row['user_count'];

$t_page_count = ceil( $t_total_user_count / $p_per_page );
if( $t_page_count < 1 ) {
	$t_page_count = 1;
}

# Make sure $p_page_number isn't past the last page.
if( $f_page_number > $t_page_count ) {
	$f_page_number = $t_page_count;
}

# Make sure $p_page_number isn't before the first page
if( $f_page_number < 1 ) {
	$f_page_number = 1;
}


$t_query = 'SELECT * FROM {user} WHERE ' . $t_where . ' ORDER BY ' . $c_sort . ' ' . $c_dir;
$t_result = db_query( $t_query, $t_where_params, $p_per_page, $t_offset );

$t_users = array();
while( $t_row = db_fetch_array( $t_result ) ) {
	$t_users[] = $t_row;
}

$t_user_count = count( $t_users );
?>
<div class="widget-box widget-color-blue2">
<div class="widget-header widget-header-small">
<h4 class="widget-title lighter">
	<?php print_icon( 'fa-users', 'ace-icon' ); ?>
	<?php echo lang_get('manage_accounts_title') ?>
	<span class="badge"><?php echo $t_total_user_count ?></span>
</h4>
</div>

<div class="widget-body">
<div class="widget-toolbox padding-8 clearfix">
	<div id="manage-user-div" class="form-container">
		<div class="pull-left">
			<?php print_link_button( 'manage_user_create_page.php',
				lang_get( 'create_new_account_link' ),
				'btn-sm' )
			?>
		</div>
		<?php if( $f_filter === 'UNUSED' ) { ?>
		<div class="pull-left">
			<?php print_form_button('manage_user_prune.php',
				lang_get('prune_accounts'),
				[],
				null,
				'btn btn-primary btn-sm btn-white btn-round')
			?>
		</div>
		<?php } ?>
	<div class="pull-right">
	<form id="manage-user-filter" method="post" action="manage_user_page.php" class="form-inline">
		<fieldset>
			<?php # CSRF protection not required here - form does not result in modifications ?>
			<input type="hidden" name="sort" value="<?php echo $c_sort ?>" />
			<input type="hidden" name="dir" value="<?php echo $c_dir ?>" />
			<input type="hidden" name="save" value="1" />
			<input type="hidden" name="filter" value="<?php echo string_attribute( $f_filter ); ?>" />
			<input type="hidden" name="search" value="<?php echo string_attribute( $f_search ); ?>" />
			<label class="inline">
				<input type="checkbox" class="ace" name="hideinactive" value="<?php echo ON ?>"
					<?php check_checked( $c_hide_inactive, ON ); ?>
				/>
				<span class="lbl padding-6"><?php echo lang_get( 'hide_inactive' ) ?></span>
			</label>
			<label class="inline">
				<input type="checkbox" class="ace" name="showdisabled" value="<?php echo ON ?>"
					<?php check_checked( $c_show_disabled, ON ); ?>
				/>
				<span class="lbl padding-6"><?php echo lang_get( 'show_disabled' ) ?></span>
			</label>
			<label for="search">
				<input id="search" type="text" size="45" name="search" class="input-sm"
					   value="<?php echo string_attribute ( $f_search );?>"
					   placeholder="<?php echo lang_get( 'search_user_hint' ) ?>"
				/>
				</label>
			<input type="submit" class="btn btn-primary btn-sm btn-white btn-round"
				   value="<?php echo lang_get( 'filter_button' ) ?>"
			/>
		</fieldset>
	</form>
		</div>
	</div>
</div>

<div class="widget-main no-padding">
	<div class="table-responsive">
		<table class="table table-striped table-bordered table-condensed table-hover">
		<thead>
			<tr>
<?php
	# Print column headers with sort links
	$t_columns = array(
		'username', 'realname', 'email', 'access_level',
		'enabled', 'protected', 'date_created', 'last_visit'
	);
	$t_display_failed_login_count = OFF != config_get( 'max_failed_login_count' );
	if( $t_display_failed_login_count ) {
		$t_columns[] = 'failed_login_count';
	}
	foreach( $t_columns as $t_col ) {
		echo "\t<th>";
		print_manage_user_sort_link( 'manage_user_page.php',
			lang_get( $t_col ),
			$t_col,
			$c_dir, $c_sort, $c_hide_inactive, $f_filter, $f_search, $c_show_disabled );
		print_sort_icon( $c_dir, $c_sort, $t_col );
		echo "</th>\n";
	}
?>
			</tr>
		</thead>

		<tbody>
<?php
	$t_date_format = config_get( 'normal_date_format' );
	$t_duplicate_emails =  config_get_global( 'email_ensure_unique' )
		? user_get_duplicate_emails()
		: [];
	$t_access_level = array();
	foreach( $t_users as $t_user ) {
		/**
		 * @var int $v_id
		 * @var string $v_username
		 * @var string $v_realname
		 * @var string $v_email
		 * @var int $v_date_created
		 * @var int $v_last_visit
		 * @var int $v_access_level
		 * @var bool $v_enabled
		 * @var bool $v_protected
		 */
		extract( $t_user, EXTR_PREFIX_ALL, 'v' );

		$v_date_created  = date( $t_date_format, $v_date_created );
		$v_last_visit    = date( $t_date_format, $v_last_visit );

		if( !isset( $t_access_level[$v_access_level] ) ) {
			$t_access_level[$v_access_level] = get_enum_element( 'access_levels', $v_access_level );
		} ?>
			<tr>
				<td>
<?php
		if( access_has_global_level( $v_access_level ) ) {
			/** @noinspection HtmlUnknownTarget */
			printf( '<a href="%s">%s</a>',
				'manage_user_edit_page.php?user_id=' . $v_id,
				string_display_line( $v_username )
			);
		} else {
			echo string_display_line( $v_username );
		}
?>
				</td>
				<td><?php echo string_display_line( $v_realname ) ?></td>
				<td><?php
					# Display warning icon if emails should be unique and a duplicate exists
					if( array_key_exists( strtolower( $v_email ), $t_duplicate_emails ) ) {
						print_icon( 'fa-exclamation-triangle',
							'ace-icon bigger-125 red padding-right-4',
							lang_get( 'email_not_unique' )
						);
					}
					print_email_link( $v_email, $v_email )
				?></td>
				<td><?php echo $t_access_level[$v_access_level] ?></td>
				<td class="center"><?php echo trans_bool( $v_enabled ) ?></td>
				<td class="center"><?php
					if( $v_protected ) {
						echo ' ' . $t_lock_image;
					} else {
						echo '&#160;';
					} ?>
				</td>
				<td><?php echo $v_date_created ?></td>
				<td><?php echo $v_last_visit ?></td><?php if( $t_display_failed_login_count ) { ?>
				<td><?php echo $v_failed_login_count ?></td><?php } ?>
			</tr>
<?php
	}  # end for
?>
		</tbody>
	</table>
</div>
</div>

<?php
	# Do not display the section's footer if we have only one page of users,
	# otherwise it will be empty as the navigation controls won't be shown.
	if( $t_total_user_count > $p_per_page ) {
?>
<div class="widget-toolbox padding-8 clearfix">
	<div class="btn-toolbar pull-right">
<?php
		# @todo hack - pass in the hide inactive filter via cheating the actual filter value
		print_page_links( 'manage_user_page.php',
			1, $t_page_count, (int)$f_page_number,
			$f_filter
			. "&amp;search=$f_search" . $t_hide_inactive_filter . $t_show_disabled_filter
			. "&amp;sort=$c_sort&amp;dir=$c_dir"
		);
?>
	</div>
</div>
<?php } ?>

</div>
</div>
</div>

<?php
layout_page_end();
