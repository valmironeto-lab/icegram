<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ES_Contacts_Table extends ES_List_Table {
	/**
	 * Contact lists status array
	 *
	 * @since 4.0.0
	 * @var array
	 */
	public $contact_lists_statuses = array();

	/**
	 * Number of contacts per page
	 *
	 * @since 4.2.1
	 *
	 * @var string
	 */
	public static $option_per_page = 'es_contacts_per_page';

	/**
	 * Array of list ids
	 *
	 * @since 4.0.0
	 * @var array
	 */
	public $list_ids = array();

	/**
	 * List name mapped to id
	 *
	 * @since 4.0.0
	 * @var array
	 */
	public $lists_id_name_map = array();

	/**
	 * Last opened at
	 *
	 * @since 4.6.5
	 * @var array
	 */
	public $items_data = array();

	/**
	 * Contacts database object
	 *
	 * @var object|ES_DB_Contacts
	 */
	public $db;

	/**
	 * ES_Contacts_Table constructor.
	 *
	 * @since 4.0.0
	 */
	public function __construct() {

		parent::__construct(
			array(
				'singular' => __( 'Contact', 'email-subscribers' ),
				'plural'   => __( 'Contacts', 'email-subscribers' ),
				'ajax'     => false,
				'screen'   => 'es_subscribers',
			)
		);

		$this->db = new ES_DB_Contacts();

		add_filter( 'ig_es_audience_tab_main_navigation', array( $this, 'get_audience_main_tabs' ), 10, 2 );

		// @since 4.3.1
		add_action( 'ig_es_form_deleted', array( $this, 'set_default_form_id' ), 10, 1 );
	}

	/**
	 * Add Screen Option
	 *
	 * @since 4.2.1
	 */
	public static function screen_options() {

		// Don't show screen option on Import/ Export subscribers page.
		//$action = ig_es_get_request_data( 'action' );

		if ( empty( $action ) ) {

			$option = 'per_page';
			$args   = array(
				'label'   => __( 'Number of contacts per page', 'email-subscribers' ),
				'default' => 100,
				'option'  => self::$option_per_page,
			);

			add_screen_option( $option, $args );
		}

	}

	/**
	 * Get the content of Audience main tab
	 *
	 * @param $active_tab
	 * @param array      $audience_main_tabs
	 *
	 * @return array
	 *
	 * @since 4.0.0
	 */
	public function get_audience_main_tabs( $active_tab, $audience_main_tabs = array() ) {

		$audience_tab_main_navigation = array(
			'new_contact'  => array(
				'label'            => __( 'Add New Contact', 'email-subscribers' ),
				'indicator_option' => '',
				'indicator_label'  => '',
				'indicator_type'   => '',
				'action'           => 'new',
				'url'              => add_query_arg( 'action', 'new', 'admin.php?page=es_subscribers' ),
			),

			'import'       => array(
				'label'            => __( 'Import Contacts', 'email-subscribers' ),
				'indicator_option' => '',
				'indicator_label'  => '',
				'indicator_type'   => '',
				'action'           => 'import',
				'url'              => add_query_arg( 'action', 'import', 'admin.php?page=es_subscribers' ),
			),

			'export'       => array(
				'label'            => __( 'Export Contacts', 'email-subscribers' ),
				'indicator_option' => '',
				'indicator_label'  => '',
				'indicator_type'   => '',
				'action'           => 'export',
				'url'              => add_query_arg( 'action', 'export', 'admin.php?page=es_subscribers' ),
			),
			// Start-IG-Code.
			/*
			'sync'         => array(
				'label'            => __( 'Sync', 'email-subscribers' ),
				'indicator_option' => 'ig_es_show_sync_tab',
				'indicator_label'  => __( 'New', 'email-subscribers' ),
				'indicator_type'   => 'new',
				'action'           => 'sync',
				'url'              => add_query_arg( 'action', 'sync', 'admin.php?page=es_subscribers' ),
			),
			*/
			// End-IG-Code.
			'manage_lists' => array(
				'label'            => __( 'Manage Lists', 'email-subscribers' ),
				'indicator_option' => '',
				'indicator_label'  => '',
				'indicator_type'   => '',
				'action'           => 'manage-lists',
				'is_imp'           => true,
				'url'              => add_query_arg( 'action', 'manage-lists', 'admin.php?page=es_lists' ),
			),
		);

		$audience_main_tabs = $audience_main_tabs + $audience_tab_main_navigation;

		if ( ! empty( $active_tab ) && isset( $audience_main_tabs[ $active_tab ] ) ) {
			unset( $audience_main_tabs[ $active_tab ] );
		}

		return $audience_main_tabs;
	}

	/**
	 * Render Audience View
	 *
	 * @since 4.2.1
	 */
	public function render() {
		?>
		<div class="font-sans">

		<?php
		$bulk_action = ig_es_get_request_data( 'bulk_action' );
		if ( 'bulk_list_update' === $bulk_action ) {
			$bulk_message      = __( 'Contact(s) moved to list successfully!', 'email-subscribers' );
			$bulk_message_type = 'success';
		} elseif ( 'bulk_status_update' === $bulk_action ) {
			$bulk_message      = __( 'Contact(s) status changed successfully!', 'email-subscribers' );
			$bulk_message_type = 'success';
		} elseif ( 'bulk_send_confirmation_email' === $bulk_action ) {
			$bulk_message      = __( 'Confirmation emails queued successfully and will be sent shortly.', 'email-subscribers' );
			$bulk_message_type = 'success';
		} elseif ( 'bulk_list_add' === $bulk_action ) {
			$bulk_message      = __( 'Contact(s) added to list successfully!', 'email-subscribers' );
			$bulk_message_type = 'success';
		} elseif ( 'bulk_delete' === $bulk_action ) {
			$bulk_message      = __( 'Contact(s) deleted successfully!', 'email-subscribers' );
			$bulk_message_type = 'success';
		}

		if ( ! empty( $bulk_message ) ) {
			ES_Common::show_message( $bulk_message, $bulk_message_type );
		}
		?>

		<?php

		$action = ig_es_get_request_data( 'action' );
		if ( 'import' === $action ) {
			$this->load_import();
		} elseif ( 'export' === $action ) {
			$this->load_export();
		} elseif ( 'new' === $action || 'edit' === $action ) {
			$contact_id = absint( ig_es_get_request_data( 'subscriber' ) );
			$this->save_contact( $contact_id );
		} elseif ( 'sync' === $action ) {
			update_option( 'ig_es_show_sync_tab', 'no' ); // yes/no
			$this->load_sync();
		} else {
			//Display sticky header
			$this->render_header();

			//Display Subscriber activities and top 5 subscriber countries
			$this->get_contacts_reports();
			?>
			<div id="poststuff" class="es-audience-view es-items-lists">
				<div id="post-body" class="metabox-holder column-1">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="get">
								<input type="hidden" name="page" value="es_subscribers"/>
								<?php
								// Display search field and other available filter fields.
								$this->prepare_items();

								// Display Advanced Filter block
								do_action('ig_es_render_advanced_filter');

								?>
							</form>
							<form method ='post'>
								<?php
								// Add hidden list dropdown and status dropdown fields. They will be displayed accordling to the chosen bulk action using JS.
								$this->prepare_lists_dropdown();
								$this->prepare_statuses_dropdown();

								// Display bulk action fields, pagination and list items.
								$this->display();
								?>
							</form>
						</div>
					</div>
					<br class="clear">
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Load Export Feature
	 *
	 * @since 4.0.0
	 */
	public function load_export() {
		$export = new Export_Subscribers();
		$export->export_subscribers_page();
	}

	/**
	 * Load import
	 *
	 * @since 4.0.0
	 */
	public function load_import() {
		$import = new ES_Import_Subscribers();
		$import->import_subscribers_page();
	}

	/**
	 * Load Sync
	 *
	 * @since 4.0.0
	 */
	public function load_sync() {
		$sync = ES_Handle_Sync_Wp_User::get_instance();
		$sync->prepare_sync_user();
	}


	/**
	 * Get Audience dashboard header
	 */
	public static function render_header( $active_tab = '') {
		$audience_tab_main_navigation = array();
		$active_tab                   = $active_tab;
		$audience_tab_main_navigation = apply_filters( 'ig_es_audience_tab_main_navigation', $active_tab, $audience_tab_main_navigation );
		?>
		<div class="sticky top-0 z-10">
			<header>
				<nav aria-label="Global" class="pb-5 w-full pt-2">
					<div class="brand-logo">
						<span>
							<img src="<?php echo ES_PLUGIN_URL . 'lite/admin/images/new/brand-logo/IG LOGO 192X192.svg'; ?>" alt="brand logo" />
							<div class="divide"></div>
							<h1>Audience</h1>
						</span>
					</div>

					<div class="cta">
						<?php ES_Common::prepare_main_header_navigation( $audience_tab_main_navigation ); ?>
					</div>
				</nav>
			</header>
		</div>
		<?php
	}

	/**
	 * Get Contacts Reports
	 *
	 * @since 4.3.1
	 */
	public function get_contacts_reports() {
		$args                            = array( 'days' => 60 );
		$es_total_contact                = ES_Reports_Data::get_total_contacts();
		$es_total_subscribed_contacts    = ES_Reports_Data::get_total_subscribed_contacts( $args );
		$es_total_unsubscribed_contacts  = ES_Reports_Data::get_total_unsubscribed_contacts( $args);
		$es_total_unconfirmed_contacts   = ES_Reports_Data::get_total_unconfirmed_contacts( $args );
		$es_total_contacts_opened_emails = ES_Reports_Data::get_total_contacts_opened_emails( $args );

		$source         = 'es_subscribers';
		$override_cache = true;
		$reports_data   = ES_Reports_Data::get_dashboard_reports_data( $source, $override_cache, $args );
		$allowed_html_tags = ig_es_allowed_html_tags_in_esc();
		?>
		<div class="mb-8 overview">
			<table class="contacts-report-table overflow-hidden bg-white rounded-lg">
				<tr>
					<td class="es-w-55 subscriber-right-border-width px-4">
						<!-- Total Subscriber -->
						<div class="flex pb-2">
							<div class="lg:pl-2 pt-1">
								<svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="w-8 h-8 text-gray-400 mt-1">
									<path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
								</svg>
							</div>
							<div class="pt-1">
								<span class="total-contact-count-text font-medium text-gray-400 pl-3"><?php echo esc_html__( 'Total contacts', 'email-subscribers' ); ?></span>
								<span class="total-contact-count-text font-bold leading-none text-indigo-600 pl-3"><?php echo esc_html( number_format( $es_total_contact ) ); ?></span>
							</div>
						</div>

						<!-- Subscriber Activity -->
						<div id="es-dashboard-stats" class="es-w-100 pr-4 py-4">
							<p class="pb-5 text-lg font-medium leading-6 text-gray-400">
								<span class="leading-7">
									<?php
										/* translators: %s. Number of days */
										echo sprintf( esc_html__( 'Subscribers activities in last %d days', 'email-subscribers' ), esc_html( $args['days'] ) );
									?>
								</span>
								<span class="float-right">
									<select id="filter_by_list" class="w-32 text-sm">
										<?php
										$lists_dropdown = ES_Common::prepare_list_dropdown_options( '', __( 'All lists', 'email-subscribers' ) );
										echo wp_kses( $lists_dropdown, $allowed_html_tags );
										?>
									</select>
								</span>
							</p>
							<?php
							ES_Admin::get_view(
								'dashboard/subscribers-stats',
								array(
									'reports_data' => $reports_data,
									'days'         => $args['days'],
								)
							);
							?>
						</div>
					</td>

					<td class="es-w-45 px-4">
						<!-- Top 5 Countries -->
						<div class="es-w-100 px-4 pt-2">
							<?php
							$countries_count = 5;
							?>
							<p class="text-lg font-medium leading-7 text-gray-400">
								<?php
								/* Translators: %s. Country count */
								echo sprintf( esc_html__( 'Top %s countries', 'email-subscribers' ), esc_html( $countries_count ) );
								
								if ( ! ES()->is_pro() ) {
									$utm_args = array(
										'utm_medium' => 'dashboard-top-countries',
										'url'		 => 'https://www.icegram.com/documentation/what-analytics-does-email-subscribers-track/'
									);
							
									$pricing_url = ES_Common::get_utm_tracking_url( $utm_args );
									?>
									<a  target="_blank" href="<?php echo esc_url( $pricing_url ); ?>">
										<span class="premium-icon inline-block max"></span>
									</a>
									<?php
								}
								?>
							</p>
							<?php
								do_action( 'ig_es_show_top_countries_stats', $countries_count );
							?>
						</div>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Save contact
	 *
	 * @param int $id
	 *
	 * @since 4.0.0
	 */
	public function save_contact( $id = 0 ) {
		
		$is_new = ( $id === 0 );
		$title = $is_new ? __( ' Add New Contact', 'email-subscribers' ) : __( ' Edit Contact', 'email-subscribers' );
		$title_action = $is_new
			? '<a href="admin.php?page=es_lists&action=manage-lists" class="ig-es-imp-button px-3 py-1">' . __( 'Manage Lists', 'email-subscribers' ) . '</a>'
			: '<a href="admin.php?page=es_subscribers&action=new" class="ig-es-title-button mx-2"> ' . __( 'Add New', 'email-subscribers' ) . '</a>';
	
		$data = $is_new ? array() : ES_Contact_Controller::get_existing_contact_data($id);
	
		if ( ig_es_get_request_data( 'submitted' ) === 'submitted' ) {
			$contact_nonce = ig_es_get_request_data( 'ig_es_contact_nonce' );
			// Verify nonce.
			if ( wp_verify_nonce( $contact_nonce, 'ig-es-contact-nonce' ) ) {

			$contact_data = ig_es_get_data( $_POST, 'contact_data', array(), true );
			$contact_data['id'] = $id;
			
			$save_result = ES_Contact_Controller::process_contact_save( $contact_data );

				if ( ! empty( $save_result['errors'] ) ) {
					foreach ( $save_result['errors'] as $error ) {
						ES_Common::show_message( $error, 'error' );
					}
				} else {
					$message = $save_result['is_new']
					? sprintf(
						__( 'Contact added successfully. %1$sEdit contact%2$s.', 'email-subscribers' ),
						'<a href="' . esc_url( add_query_arg( array( 'subscriber' => $save_result['id'], 'action' => 'edit' ), menu_page_url( 'es_subscribers', false ) ) ) . '" class="text-indigo-600">',
						'</a>'
					)
					: __( 'Contact updated successfully!', 'email-subscribers' );
	
					ES_Common::show_message( $message, 'success' );
	
					// Reset if new
					if ( $save_result['is_new'] ) {
						$data = array();
					} else {
						$data = ES_Contact_Controller::get_existing_contact_data( $save_result['id'] );
					}
				}
			}
		}
	
		?>
		<div class="gap-5">
			<?php $this->render_header('new_contact'); ?>
			<div class="bg-white shadow-md rounded-lg">
				<h2 class="pt-4 px-3 ml-8 text-2xl font-medium text-gray-700">
					<?php echo esc_html( $title ); ?>
				</h2>
				<?php echo wp_kses_post( $this->prepare_contact_form( $data, $is_new ) ); ?>
			</div>
		</div>
		<?php
	}
	

	/**
	 * Retrieve subscribers data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public function get_subscribers( $per_page = 5, $page_number = 1, $do_count_only = false ) {
		
		// Advanced filters for Audience Section
		$advanced_filter = ig_es_get_request_data('advanced_filter');
		$advanced_filter = ( !empty($advanced_filter) ) ? $advanced_filter['conditions'] : '';

		$args = array(
			'order_by'      => sanitize_sql_orderby( ig_es_get_request_data( 'orderby', 'created_at' ) ),
			'order'         => ig_es_get_request_data( 'order'),
			'search'        => ig_es_get_request_data( 's' ),
			'per_page'      => absint( $per_page ),
			'page_number'   => absint( $page_number ),
			'do_count_only' => $do_count_only,
			'filter_by_list_id' => ig_es_get_request_data( 'filter_by_list_id'),
			'filter_by_status' => ig_es_get_request_data( 'filter_by_status'),
			'advanced_filter' => $advanced_filter,
			 );
			return ES_Contacts_Controller::get_subscribers($args);

		
	}


	public function prepare_contact_form( $data = array(), $is_new = false ) {
		$id                 = ! empty( $data['id'] ) ? $data['id'] : '';
		$created            = ! empty( $data['created'] ) ? $data['created'] : '';
		$guid               = ! empty( $data['guid'] ) ? $data['guid'] : '';
		$action             = ! empty( $data['action'] ) ? $data['action'] : '#';
		$first_name         = ! empty( $data['first_name'] ) ? $data['first_name'] : '';
		$last_name          = ! empty( $data['last_name'] ) ? $data['last_name'] : '';
		$email              = ! empty( $data['email'] ) ? $data['email'] : '';
		$send_welcome_email = ! empty( $data['send_welcome_email'] ) ? true : false;

		$lists_id_name_map = ES()->lists_db->get_list_id_name_map();

		if ( count( $lists_id_name_map ) ) {
			// $list_html = ES_Shortcode::prepare_lists_multi_select( $lists_id_name_map, array_keys( $lists_id_name_map ), 4, $selected_list_ids, $id, 'contact_data[lists][]' );
			$list_html = $this->prepare_lists_html( $id );
		} else {
			$list_html = "<tr><td><span class='text-sm leading-5 font-normal text-gray-500'>" . __( 'No list found', 'email-subscribers' ) . '</span></td></tr>';
		}

		?>
		<form id="es-admin-contact-form" method="post" action="<?php echo esc_attr( $action ); ?>" class="ml-8 mr-4 text-left py-5">
			<?php wp_nonce_field( 'ig-es-contact-nonce', 'ig_es_contact_nonce' ); ?>
			<div class="flex flex-row border-b border-gray-100">
				<div class="flex es-w-15">
					<div class="ml-3 py-4">
						<label for="firstname"><span class="block ml-4 pt-1 pr-4 text-sm font-medium text-gray-600 pb-2"><?php esc_html_e( 'First name', 'email-subscribers' ); ?></span></label>

					</div>
				</div>
				<div class="flex">
					<div class="ml-16 mb-4 h-10 mr-4 mt-3">
						<div class="h-10 relative">
							<div class="absolute left-0 pl-3 flex items-center pointer-events-none">
								<span class="text-gray-400">
									<span class="my-2 dashicons dashicons-admin-users"></span>
								</span>
							</div>
							<input id="ig-es-contact-first-name" class="ig-es-contact-first-name form-input block border-gray-400 w-full pl-10 pr-12 shadow-sm  focus:bg-gray-100 sm:text-sm sm:leading-5" placeholder="<?php esc_html_e( 'Enter first name', 'email-subscribers' ); ?>" name="contact_data[first_name]"
								   value="<?php echo esc_attr( $first_name ); ?>"/>
						</div>
					</div>
				</div>
			</div>

			<div class="flex flex-row border-b border-gray-100">
				<div class="flex es-w-15">
					<div class="ml-3 py-4">
						<label for="lastname"><span class="block ml-4 pt-1 pr-4 text-sm font-medium text-gray-600 pb-2"><?php esc_html_e( 'Last name', 'email-subscribers' ); ?></span></label>
					</div>
				</div>
				<div class="flex">
					<div class="ml-16 my-4 h-10 mt-3">
						<div class="h-10 relative">
							<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
								<span class="inset-y-0 text-gray-400 sm:text-sm sm:leading-5">
									<span class="my-2 mr-10 dashicons dashicons-admin-users"></span>
								</span>
							</div>
							<input id="ig-es-contact-last-name" class="ig-es-contact-last-name form-input block border-gray-400 w-full pl-10 pr-12 shadow-sm  focus:bg-gray-100 sm:text-sm sm:leading-5" placeholder="<?php esc_html_e( 'Enter last name', 'email-subscribers' ); ?>" name="contact_data[last_name]" value="<?php echo esc_attr( $last_name ); ?>"/>
						</div>
					</div>
				</div>
			</div>

			<div class="flex flex-row border-b border-gray-100">
				<div class="flex es-w-15">
					<div class="ml-3 py-4 pr-8">
						<label for="email"><span class="block ml-4 pt-1 pr-4 text-sm font-medium text-gray-600 pb-2"><?php esc_html_e( 'Email', 'email-subscribers' ); ?></span></label>
					</div>
				</div>
				<div class="flex">
					<div class="ml-16 mr-4 h-10 mt-3">
						<div class="h-10 relative">
							<div class="absolute inset-y-0 left-0 pl-3 pt-2 flex items-center pointer-events-none">
								<svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
									<path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
									<path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
								</svg>
							</div>
							<input id="email" class="form-input block border-gray-400 w-full pl-10 pr-12 shadow-sm  focus:bg-gray-100 sm:text-sm sm:leading-5" type="email" name="contact_data[email]" value="<?php echo esc_attr( $email ); ?>" placeholder="<?php esc_html_e( 'Enter email', 'email-subscribers' ); ?>"/>
						</div>
					</div>
				</div>
			</div>

			<?php if ( $is_new ) { ?>
				<div class="flex flex-row border-b border-gray-100">
					<div class="flex es-w-15">
						<div class="ml-3 py-4">
							<label for="send_email"><span class="block ml-4 pt-1 pr-4 text-sm font-medium text-gray-600 pb-2"><?php esc_html_e( 'Send welcome email?', 'email-subscribers' ); ?></span>
							</label>
						</div>
					</div>
					<div class="flex">
						<div class="ml-16 mr-4 h-10 mt-3">
							<label for="send_email" class=" inline-flex items-center cursor-pointer">
								<span class="relative">
									<input id="send_email" type="checkbox" class="sr-only peer absolute es-check-toggle opacity-0 w-0 h-0"
											name="contact_data[send_welcome_email]"
									<?php
									if ( $send_welcome_email ) {
										echo "checked='checked'";
									}
									?>
										/>
										<div class="w-11 h-6 bg-gray-200 rounded-full peer  dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
								</span>
							</label>
						</div>
					</div>
				</div>
			<?php } ?>

			<?php do_action( 'es_show_additional_contacts_data', $data ); ?>
			<div class="flex flex-row">
				<div class="flex es-w-15">
					<div class="ml-3 py-4">
						<label for="status">
							<span class="block ml-4 pt-1 pr-4 text-sm font-medium text-gray-600 pb-2"> <?php esc_html_e( 'List(s)', 'email-subscribers' ); ?></span></label>
							<p class="italic text-xs text-gray-400 mt-2 ml-4 leading-snug pb-8"><?php esc_html_e( 'Contacts will be added into selected list(s)', 'email-subscribers' ); ?></p>
					</div>
				</div>
				<div class="flex">
					<div class="ml-16 mt-3 mr-4">
						<div class=" relative">
							<?php
							$allowedtags = ig_es_allowed_html_tags_in_esc();
							echo wp_kses( $list_html, $allowedtags );
							?>
						</div>
					</div>
				</div>
			</div>

			<div class="flex">
				<?php
				$submit_button_text = $is_new ? __( 'Add Contact', 'email-subscribers' ) : __( 'Save Changes', 'email-subscribers' );
				?>
				<div class="ml-3 mb-3 pt-6">
					<input type="hidden" name="contact_data[created_at]" value="<?php echo esc_attr( $created ); ?>"/>
					<input type="hidden" name="contact_data[guid]" value="<?php echo esc_attr( $guid ); ?>"/>
					<input type="hidden" name="submitted" value="submitted"/>
					<button type="submit" name="submit" class="primary mr-2"> <?php echo esc_attr( $submit_button_text ); ?> </button>
					<a href="admin.php?page=es_subscribers"><button type="button" class="secondary"><?php esc_html_e( 'Cancel', 'email-subscribers' ); ?></button></a>
				</div>
			</div>
		</form>
		</div>
		<?php

	}


	/**
	 * No contacts available
	 *
	 * @since 4.0.0
	 */
	public function no_items() {
		esc_html_e( 'No contacts avaliable.', 'email-subscribers' );
	}


	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array  $item
	 * @param string $column_name
	 *
	 * @return mixed
	 *
	 * @since 4.0.0
	 */
	public function column_default( $item, $column_name ) {
		$item = apply_filters( 'es_subscribers_col_data', $item, $column_name, $this );
		switch ( $column_name ) {
			case 'lists':
				return $this->get_lists_to_show( $item['id'] );
			case 'created_at':
				return ig_es_format_date_time( $item[ $column_name ] );

			case 'first_name':
			case 'email':
			default:
				$column_data = isset( $item[ $column_name ] ) ? $item[ $column_name ] : '-';
				return apply_filters( 'ig_es_contact_column_data', $column_data, $column_name, $item, $this );
		}
	}

	/**
	 * Prepare lists html to set status
	 *
	 * @param int $contact_id
	 * @param int $columns
	 *
	 * @return string
	 *
	 * @since 4.3.6
	 */
	public function prepare_lists_html( $contact_id = 0, $columns = 2 ) {
		$lists = ES()->lists_db->get_id_name_map();
		$lists_html = '';
		$allowedtags = ig_es_allowed_html_tags_in_esc();
		if ( count( $lists ) > 0 ) {
			$list_contact_status_map = array();
			if ( ! empty( $contact_id ) ) {
				$list_contact_status_map = ES()->lists_contacts_db->get_list_contact_status_map( $contact_id );
			}
			$lists_html = "<table class='ig-es-form-list-html'><tr>";
			$i = 0;
			foreach ( $lists as $list_id => $list_name ) {
				if ( 0 != $i && 0 === ( $i % $columns ) ) {
					$lists_html .= "</tr><tr class='mt-3'>";
				}
	
				$selected = ! empty( $list_contact_status_map[ $list_id ] ) ? $list_contact_status_map[ $list_id ] : '';
	
				$status_dropdown_html  = '<select class="h-8 form-select w-40 mt-2 mr-4 shadow-sm border-gray-400 ig-es-statuses-dropdown shadow-sm  sm:text-sm sm:leading-5" name="contact_data[lists][' . esc_attr( $list_id ) . ']" >';
				$status_dropdown_html .= ES_Common::prepare_statuses_dropdown_options( esc_attr( $selected ) );
				$status_dropdown_html .= '</select>';
	
				$status_span = '';
				if ( ! empty( $list_contact_status_map[ $list_id ] ) ) {
					$class_name = esc_attr( $list_contact_status_map[ $list_id ] );
					$title_attr = esc_attr( ucwords( $list_contact_status_map[ $list_id ] ) );
					$status_span = '<span class="border-gray-400 focus:bg-gray-100 es_list_contact_status ' . $class_name . '" title="' . $title_attr . '">';
				}
	
				$list_title  = $list_name;
				$list_name   = strlen( $list_name ) > 15 ? substr( $list_name, 0, 15 ) . '...' : $list_name;
	
				$lists_html .= "<td class='pr-2 pt-2 text-sm leading-5 font-normal text-gray-500'>" .
					$status_span .
					"<span title='" . esc_attr( $list_title ) . "'>" . esc_html( $list_name ) . '</span></td><td>' .
					wp_kses( $status_dropdown_html, $allowedtags ) .
					'</td>';
				$i ++;
			}
	
			$lists_html .= '</tr></table>';
		}
	
		return $lists_html;
	}	

	/**
	 * Show lists with it's status
	 *
	 * @param $contact_id
	 *
	 * @return string
	 *
	 * @since 4.0.0
	 */
	public function get_lists_to_show( $contact_id ) {

		$list_str = '';

		if ( isset( $this->contact_lists_statuses[ $contact_id ] ) ) {

			$lists = $this->contact_lists_statuses[ $contact_id ];

			if ( count( $lists ) > 0 ) {
				// Show only 4 lists
				// $contact_lists_to_display = array_slice( $lists, 0, 4 );
				foreach ( $lists as $list_id => $status ) {

					if ( ! empty( $this->lists_id_name_map[ $list_id ] ) ) {

						$list_str .= '<span class="es_list_contact_status ' . strtolower( $status ) . '" title="' . ucwords( $status ) . '">' . $this->lists_id_name_map[ $list_id ] . '</span>';
					}
				}
			}
		}

		return $list_str;
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 *
	 * @since 4.0.0
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" class="checkbox" name="subscribers[]" value="%s"/>',
			$item['id']
		);
	}

	/**
	 * Method for subscriber column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 *
	 * @since 4.8.3
	 */
	public function column_subscriber( $item ) {
		$delete_nonce = wp_create_nonce( 'ig_es_delete_subscriber' );

		$contact_id = $item['id'];
		$name       = ES_Common::prepare_name_from_first_name_last_name( $item['first_name'], $item['last_name'] );

		$title = '';
		if ( ! empty( $name ) ) {
			$title = '<strong>' . $name . '</strong><br/>';
		}

		$title .= $item['email'];
		$title  = apply_filters( 'ig_es_contact_column_subscriber', $title, $item );
		$page   = ig_es_get_request_data( 'page' );

		$actions = array(
			'edit'   => '<a href="?page=' . esc_attr( $page ) . '&action=edit&subscriber=' . absint( $item['id'] ) . '&_wpnonce=' . $delete_nonce . '" class="text-indigo-600">' . esc_html__( 'Edit', 'email-subscribers' ) . '</a>',

			'delete' => '<a href="?page=' . esc_attr( $page ) . '&action=delete&subscriber=' . absint( $item['id'] ) . '&_wpnonce=' . $delete_nonce . '" onclick="return checkDelete()">' . esc_html__( 'Delete', 'email-subscribers' ) . '</a>',
		);

		if ( isset( $this->contact_lists_statuses[ $contact_id ] ) ) {
			$lists_statuses = $this->contact_lists_statuses[ $contact_id ];

			if ( ! empty( $lists_statuses ) ) {

				$has_unconfirmed_status = false;
				foreach ( $lists_statuses as $list_status ) {
					if ( 'unconfirmed' === $list_status ) {
						$has_unconfirmed_status = true;
						break;
					}
				}

				// Show resend confirmation email option only when contact has unconfirmed status in atleast one list.
				if ( $has_unconfirmed_status ) {
					$actions['resend'] = '<a href="?page=' . esc_attr( $page ) . '&action=resend&subscriber=' . absint( $item['id'] ) . '&_wpnonce=' . $delete_nonce . '" class="text-indigo-600">' . esc_html__( 'Resend Confirmation', 'email-subscribers' ) . '</a>';
				}
			}
		}

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Associative array of columns
	 *
	 * @return array
	 *
	 * @since 4.0.0
	 */
	public function get_columns() {
		$lists_tooltip_text   = ES_Common::get_tooltip_html('Contact\'s current lists');

		$columns = array(
			'cb'   		 => '<input type="checkbox" class="checkbox"/>',
			'subscriber' => __( 'Contact', 'email-subscribers' ),
			/* translators: %s List tooltip text. */
			'lists' 	 => sprintf( __( 'List(s) %s', 'email-subscribers' ), $lists_tooltip_text),
			'created_at' => __( 'Created', 'email-subscribers' )
		);

		return $columns;
	}

	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 *
	 * @since 4.0.0
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'name'       => array( 'first_name', true ),
			'email'      => array( 'email', false ),
			// 'status' => array( 'status', false ),
			'created_at' => array( 'created_at', false ),
		);

		return $sortable_columns;
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 *
	 * @since 4.0.0
	 */
	public function get_bulk_actions() {
		$actions = array(
			'bulk_delete'        => __( 'Delete', 'email-subscribers' ),
			'bulk_list_update'   => __( 'Move to list', 'email-subscribers' ),
			'bulk_list_add'      => __( 'Add to list', 'email-subscribers' ),
			'bulk_status_update' => __( 'Change status', 'email-subscribers' ),
		);

		$bulk_actions = apply_filters( 'ig_es_contacts_bulk_action', $actions );

		return $bulk_actions;
	}

	/**
	 * Prepare search box
	 *
	 * @param string $text
	 * @param string $input_id
	 *
	 * @since 4.0.0
	 * @since 4.3.4 Added esc_attr()
	 */
	public function search_box( $text = '', $input_id = '' ) {

		?>
		<p class="search-box box-ma10">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_attr( $text ); ?>:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" placeholder="Search"/>
			<button type="submit" id="search-submit" class="secondary"><?php echo esc_html__( 'Search Contacts', 'email-subscribers'); ?></button>
		</p>

		<?php if ( ES()->is_pro() ) { ?>
		<p class="search-box search-group-box box-ma10">
			<span class="flex items-center pt-1">
				<span class="relative">
					<button id="advanced_filter" type="button" class="ig-es-switch js-toggle-collapse" value="no" data-ig-es-switch="inactive" ></button>
				</span>
				<span>
					<label class="mx-2" >
						<?php echo esc_html__( 'Advanced Filter', 'email-subscribers'); ?>
					</label>
				</span>
			</span>
		</p>
		<?php } ?>

		<p class="search-box search-group-box box-ma10">
			<?php $filter_by_status = ig_es_get_request_data( 'filter_by_status' ); ?>
			<select name="filter_by_status" class="w-32 text-sm">
				<?php
				$allowedtags = ig_es_allowed_html_tags_in_esc();
				add_filter( 'safe_style_css', 'ig_es_allowed_css_style' );
				$status_types = ES_Common::prepare_statuses_dropdown_options( $filter_by_status, __( 'All Statuses', 'email-subscribers' ), 'audience_listing_page' );
				echo wp_kses( $status_types, $allowedtags );
				?>
			</select>
		</p>
		<p class="search-box search-group-box box-ma10">
			<?php $filter_by_list_id = ig_es_get_request_data( 'filter_by_list_id' ); ?>
			<select name="filter_by_list_id" class="w-32 text-sm">
				<?php
				$lists_dropdown = ES_Common::prepare_list_dropdown_options( $filter_by_list_id, __( 'All Lists', 'email-subscribers' ) );
				echo wp_kses( $lists_dropdown, $allowedtags );
				?>
			</select>
		</p>
		<?php
	}

	/**
	 * Get Contact id
	 *
	 * @param $contact
	 *
	 * @return mixed
	 *
	 * @since 4.0.0
	 */
	public function get_contact_id( $contact ) {
		return $contact['id'];
	}

	/**
	 * Handles data query and filter, sorting, and pagination.
	 *
	 * @since 4.0.0
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		/** Process bulk action */
		$this->process_bulk_action();

		$this->search_box( ig_es_get_request_data( 's' ), 'subscriber-search-input' );

		$per_page     = $this->get_items_per_page( self::$option_per_page, 200 );
		$current_page = $this->get_pagenum();
		$total_items  = $this->get_subscribers( 0, 0, true );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items, // WE have to calculate the total number of items
				'per_page'    => $per_page, // WE have to determine how many items to show on a page
			)
		);

		$contacts = $this->get_subscribers( $per_page, $current_page );

		$this->items = $contacts;

		if ( count( $contacts ) > 0 ) {

			$contact_ids = array_map( array( $this, 'get_contact_id' ), $contacts );

			$contact_lists_statuses = ES()->lists_contacts_db->get_list_status_by_contact_ids( $contact_ids );

			$this->contact_lists_statuses = $contact_lists_statuses;

			$this->lists_id_name_map = ES()->lists_db->get_list_id_name_map();

			$this->items_data = apply_filters( 'ig_es_subscribers_add_col_data', array(), $contact_ids );

		}
	}

	/**
	 * Prepare list dropdown
	 *
	 * @since 4.0.0
	 */
	public function prepare_lists_dropdown() {
		$data  = '<label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label><select name="list_id" id="list_id" class="groupsselect w-32 text-sm" style="display: none">';
		$data .= ES_Common::prepare_list_dropdown_options();
		$data .= '</select>';

		$allowedtags = ig_es_allowed_html_tags_in_esc();
		echo wp_kses( $data, $allowedtags );
	}

	/**
	 * Edit Status
	 *
	 * @since 4.0.0
	 */
	public function prepare_statuses_dropdown() {
		$data  = '<label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label><select name="status_select" id="status_select" class="statusesselect w-32 text-sm" style="display:none;">';
		$data .= ES_Common::prepare_statuses_dropdown_options();
		$data .= '</select>';

		$allowedtags = ig_es_allowed_html_tags_in_esc();
		echo wp_kses( $data, $allowedtags );

	}





	/**
	 * Process Bulk Action
	 *
	 * @since 4.0.0
	 */
	public function process_bulk_action( $return_response = false ) {

		$current_action = $this->current_action();
		$response       = array(
			'status' => 'error',
		);
		// Detect when a bulk action is being triggered...
		if ( 'delete' === $current_action ) {

			// In our file that handles the request, verify the nonce.
			$nonce = esc_attr( ig_es_get_request_data( '_wpnonce' ) );

			if ( ! wp_verify_nonce( $nonce, 'ig_es_delete_subscriber' ) ) {
				die( esc_html__( 'You do not have a permission to delete contact(s)', 'email-subscribers' ) );
			} else {
				$subscriber_id = absint( ig_es_get_request_data( 'subscriber' ) );
				$deleted       = ES()->contacts_db->delete_contacts_by_ids( array( $subscriber_id ) );
				if ( $deleted ) {
					$message = __( 'Contact(s) deleted successfully!', 'email-subscribers' );
					ES_Common::show_message( $message, 'success' );
				}

				return;
			}
		}



		if ( 'resend' === $current_action ) {
			// In our file that handles the request, verify the nonce.
			$nonce = esc_attr( ig_es_get_request_data( '_wpnonce' ) );

			if ( ! wp_verify_nonce( $nonce, 'ig_es_delete_subscriber' ) ) {
				die( esc_html__( 'You do not have a permission to resend email confirmation', 'email-subscribers' ) );
			} else {
				$id         = absint( ig_es_get_request_data( 'subscriber' ) );
				$resend     = ig_es_get_request_data( 'resend', false );
				$subscriber = ES()->contacts_db->get_by_id( $id );

				$email      = $subscriber['email'];
				$merge_tags = array(
					'contact_id' => $subscriber['id'],
				);

				if ( $resend ) {
					$message = __( 'Confirmation email sent successfully!', 'email-subscribers' );
					ES_Common::show_message( $message, 'success' );
					return;
				} else {
					$response = ES()->mailer->send_double_optin_email( $email, $merge_tags );
					$url      = add_query_arg( 'resend', true );
					// redirect to resend link and avoid resending email
					?>
					<meta http-equiv="refresh" content="0; url=<?php echo esc_url( $url ); ?>"/>
					<?php
				}

				return;

			}
		}


		$action  = ig_es_get_request_data( 'action' );
		$action2 = ig_es_get_request_data( 'action2' );

		$actions = array( 'bulk_delete', 'bulk_status_update', 'bulk_list_update', 'bulk_list_add', 'bulk_send_confirmation_email' );
		if ( in_array( $action, $actions, true ) || in_array( $action2, $actions, true ) ) {

			check_admin_referer( 'bulk-' . $this->_args['plural'] );

			$subscriber_ids = ig_es_get_request_data( 'subscribers' );

			if ( empty( $subscriber_ids ) ) {
				$message = __( 'Please select subscribers to update.', 'email-subscribers' );
				if ( ! $return_response ) {
					ES_Common::show_message( $message, 'error' );
				}
				$response['status']  = 'error';
				$response['message'] = $message;
				$response['errortype'] = false;
				return $response;
			}



			// If the delete bulk action is triggered
			if ( ( 'bulk_delete' === $action ) || ( 'bulk_delete' === $action2 ) ) {

				$deleted = ES()->contacts_db->delete_contacts_by_ids( $subscriber_ids );

				if ( $deleted ) {
					$message = __( 'Contact(s) deleted successfully!', 'email-subscribers' );
					if ( ! $return_response ) {
						ES_Common::show_message( $message, 'success' );
					}
					$response['status']  = 'success';
					$response['message'] = $message;
					$response['errortype'] = false;
				}

				return $response;
			}


			if ( ( 'bulk_status_update' === $action ) || ( 'bulk_status_update' === $action2 ) ) {
				$status = ig_es_get_request_data( 'status_select' );

				if ( empty( $status ) ) {
					$message = __( 'Please select status.', 'email-subscribers' );

					if ( ! $return_response ) {
						ES_Common::show_message( $message, 'error' );
					}
					$response['status']  = 'error';
					$response['message'] = $message;
					$response['errortype'] = false;
					return $response;
				}

				// loop over the array of record IDs and delete them
				$edited = ES()->lists_contacts_db->edit_subscriber_status( $subscriber_ids, $status );

				if ( in_array( $status, array( 'unsubscribed', 'unconfirmed' ), true ) ) {
					do_action( 'ig_es_admin_contact_unsubscribe', $subscriber_ids );
				}

				if ( $edited ) {
					$message = __( 'Contact(s) status changed successfully!', 'email-subscribers' );
					if ( ! $return_response ) {
						ES_Common::show_message( $message, 'success' );
					}
					$response['status']  = 'success';
					$response['message'] = $message;
					$response['errortype'] = false;
					return $response;
				}

				return;
			}


			if ( ( 'bulk_list_update' === $action ) || ( 'bulk_list_update' === $action2 ) ) {

				$list_id = ig_es_get_request_data( 'list_id' );
				if ( empty( $list_id ) ) {
					$message = __( 'Please select list.', 'email-subscribers' );
					if ( ! $return_response ) {
						ES_Common::show_message( $message, 'error' );
					}
					$response['status']  = 'error';
					$response['message'] = $message;
					$response['errortype'] = false;
					return $response;
				}

				$edited = ES()->lists_contacts_db->move_contacts_to_list( $subscriber_ids, $list_id );

				if ( $edited ) {
					$message = __( 'Contact(s) moved to list successfully!', 'email-subscribers' );
					if ( ! $return_response ) {
						ES_Common::show_message( $message, 'success' );
					}
					$response['status']  = 'success';
					$response['message'] = $message;
					$response['errortype'] = false;
					return $response;
				}

				return;
			}

			if ( ( 'bulk_list_add' === $action ) || ( 'bulk_list_add' === $action2 ) ) {

				$list_id = ig_es_get_request_data( 'list_id' );

				if ( empty( $list_id ) ) {
					$message = __( 'Please select list.', 'email-subscribers' );
					if ( ! $return_response ) {
						ES_Common::show_message( $message, 'error' );
					}
					$response['status']  = 'error';
					$response['message'] = $message;
					$response['errortype'] = false;
					return $response;
				}
// phpcs:disable
				$edited = ES()->lists_contacts_db->add_contacts_to_list( $subscriber_ids, $list_id );
// phpcs:enable
				if ( $edited ) {
					$message = __( 'Contact(s) added to list successfully!', 'email-subscribers' );
					if ( ! $return_response ) {
						ES_Common::show_message( $message, 'success' );
					}
					$response['status']  = 'success';
					$response['message'] = $message;
					$response['errortype'] = false;
					return $response;
				}
			}

			if ( 'bulk_send_confirmation_email' === $current_action ) {
				$response = Email_Subscribers_Pro::handle_bulk_send_confirmation_email_action( $subscriber_ids, $return_response );
				return $response;
			}
		}
	}

	/**
	 * Set form_id = 0 as Form is already deleted
	 *
	 * @param $form_id
	 *
	 * @since 4.3.1
	 */
	public function set_default_form_id( $form_id ) {

		global $wpdb;
	// phpcs:disable
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}ig_contacts SET form_id = %d WHERE form_id = %d", 0, $form_id ) );
	// phpcs:enable
	}
}
