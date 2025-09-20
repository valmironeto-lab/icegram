<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV Exporter bootstrap file
 */
class Export_Subscribers {

	/**
	 * Constructor
	 */
	public function __construct() {
		$report       = ig_es_get_request_data( 'report' );
		$status       = ig_es_get_request_data( 'status' );
		$link_id      = ig_es_get_request_data( 'link_id' );
		$campaign_id  = ig_es_get_request_data( 'campaign_id' );
		$export_nonce = ig_es_get_request_data( 'export-nonce' );
	
		if ( wp_verify_nonce( $export_nonce, 'ig-es-subscriber-export-nonce' ) ) {
			if ( $report && $status && ES_Common::ig_es_can_access( 'audience' ) ) {
	
				$selected_list_id = 0;
	
				if ( 'select_list' === $status ) {
					$selected_list_id = ig_es_get_request_data( 'list_id', 0 );
					if ( 0 === $selected_list_id ) {
						$this->show_error_message( __( 'Please select list', 'email-subscribers' ) );
					}
				}
	
				$args = compact( 'status', 'selected_list_id' );
				ES_Contact_Export_Controller::process_list_export( $args );
	
			} elseif ( $report && $link_id && $campaign_id && ES_Common::ig_es_can_access( 'campaigns' ) ) {
	
				$args = compact( 'campaign_id', 'link_id' );
				ES_Contact_Export_Controller::process_campaign_link_export( $args );
			}
		}
	
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'parse_request', array( $this, 'parse_request' ) );
	}
	

	private function show_error_message( $message) {
		ES_Common::show_message($message, 'error');
		exit();
	}
	
	// private function escape_and_trim_data( $data) {
	// 	return trim(str_replace('"', '""', $this->escape_data($data)));
	// }
	
	// private function output_CSV( $csv_content, $file_name) {
	// 	header('Pragma: public');
	// 	header('Expires: 0');
	// 	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	// 	header('Cache-Control: private', false);
	// 	header('Content-Type: application/octet-stream');
	// 	header("Content-Disposition: attachment; filename=$file_name");
	// 	header('Content-Transfer-Encoding: binary');
	
	// 	echo wp_kses_post($csv_content);
	// 	exit();
	// }

	public function prepare_header_footer_row() {

		?>

		<tr class="bg-light-gray">
			<th scope="col"><lable for="No."><?php esc_html_e( 'NO.', 'email-subscribers' ); ?></lable></th>
			<th scope="col"><lable for="Contacts"><?php esc_html_e( 'CONTACTS', 'email-subscribers' ); ?></lable></th>
			<th scope="col"><lable for="Total Contacts"><?php esc_html_e( 'TOTAL CONTACTS', 'email-subscribers' ); ?></lable></th>
			<th scope="col"><lable for="Export"><?php esc_html_e( 'EXPORT', 'email-subscribers' ); ?></lable></th>
		</tr>

		<?php
	}

	public function prepare_body() {

		$list_dropdown_html  = "<select class='form-select w-32 text-sm' name='list_id' id='ig_es_export_list_dropdown'>";
		$list_dropdown_html .= ES_Common::prepare_list_dropdown_options();
		$list_dropdown_html .= '</select>';

		$export_lists = array(

			'all'          => __( 'All contacts', 'email-subscribers' ),
			'subscribed'   => __( 'Subscribed contacts', 'email-subscribers' ),
			'unsubscribed' => __( 'Unsubscribed contacts', 'email-subscribers' ),
			'unconfirmed'  => __( 'Unconfirmed contacts', 'email-subscribers' ),
			'select_list'  => $list_dropdown_html,
		);

		$i = 1;
		$export_nonce = wp_create_nonce( 'ig-es-subscriber-export-nonce' );
		foreach ( $export_lists as $key => $export_list ) {
			$url = "admin.php?page=download_report&report=users&status={$key}&export-nonce={$export_nonce}";
			?>

			<tr class="border-b text-sm font-normal text-gray-700 border-gray-200" id="ig_es_export_<?php echo esc_attr( $key ); ?>">
				<td><?php echo esc_html( $i ); ?></td>
				<td>
					<div class="list-item">
						<div class="item-details">
							<p>
								<?php
								$allowedtags = ig_es_allowed_html_tags_in_esc();
								echo wp_kses( $export_list, $allowedtags );
								?>
							</p>
						</div>
					</div>
				</td>
				<td class="ig_es_total_contacts"><?php echo esc_html( ES_Contact_Export_Controller::count_subscribers( $key ) ); ?></td>
				<td>
					<div class="inline-flex  pl-10"><a href="<?php echo esc_url( $url ); ?>" id="ig_es_export_link_<?php echo esc_attr( $key ); ?>">
						<svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="w-8 h-8 text-indigo-600 hover:text-indigo-500 active:text-indigo-600"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
						</svg>
					</a>
				</div>
			</td>
		</tr>

			<?php
			$i ++;
		}

	}

	public function export_subscribers_page() {
		?>
	
		<div class="max-w-full -mt-3 font-sans">
			<?php ES_Contacts_Table::render_header('export'); ?>
			<div class="overflow-hidden ig-es-list-table">
				<h2 class="mx-4 text-2xl font-medium text-gray-700 sm:leading-7 sm:truncate"> <?php esc_html_e( 'Export Contacts', 'email-subscribers' ); ?></h2>
				<form name="frm_es_subscriberexport" method="post">
					<div class="overflow-x-auto mx-4">
						<div class="table">
							<table class="min-w-full" id="straymanage">
								<thead>
									<?php $this->prepare_header_footer_row(); ?>
								</thead>
								<tbody class="bg-white">
									<?php $this->prepare_body(); ?>
								</tbody>
							</table>
						</div>
					</div>
				</form>
			</div>
		</div>
		<?php
	}


	/**
	 * Count total subscribers
	 *
	 * @param string $status
	 *
	 * @return string|null
	 */
	// public function count_subscribers( $status = 'all' ) {

	// 	global $wpdb;

	// 	switch ( $status ) {
	// 		case 'all':
	// 			return ES()->lists_contacts_db->get_all_contacts_count( 0, false );
	// 		break;

	// 		case 'subscribed':
	// 			return ES()->lists_contacts_db->get_subscribed_contacts_count( 0, false );
	// 		break;

	// 		case 'unsubscribed':
	// 			return ES()->lists_contacts_db->get_unsubscribed_contacts_count( 0, false );
	// 		break;

	// 		case 'confirmed':
	// 			return ES()->lists_contacts_db->get_confirmed_contacts_count( 0, false );
	// 		break;

	// 		case 'unconfirmed':
	// 			return ES()->lists_contacts_db->get_unconfirmed_contacts_count( 0, false );
	// 		break;

	// 		case 'select_list':
	// 		default:
	// 			return '-';
	// 		break;
	// 	}

	// }


	/**
	 * Allow for custom query variables
	 */
	public function query_vars( $query_vars ) {
		$query_vars[] = 'download_report';

		return $query_vars;
	}

	/**
	 * Parse the request
	 */
	public function parse_request( &$wp ) {
		if ( array_key_exists( 'download_report', $wp->query_vars ) ) {
			$this->download_report();
			exit;
		}
	}

	/**
	 * Download report
	 */
	public function download_report() {
		?>

		<div class="wrap">
			<div id="icon-tools" class="icon32"></div>
			<h2>Download Report</h2>
			<p>
				<a href="?page=download_report&report=users"><?php esc_html_e( 'Export the Subscribers', 'email-subscribers' ); ?></a>
			</p>

			<?php
	}

	/**
	 * Generate CSV
	 * first_name, last_name, email, status, list, subscribed_at, unsubscribed_at
	 *
	 * @param string $status
	 * @param string $list_id
	 *
	 * @return string
	 */
	// public function generate_csv( $status = 'all', $list_id = 0 ) {

	// 	global $wpbd;

	// 	// Add filter to increase memory limit
	// 	add_filter( 'ig_es_memory_limit', 'ig_es_increase_memory_limit' );

	// 	wp_raise_memory_limit( 'ig_es' );

	// 	// Remove the added filter function so that it won't be called again if wp_raise_memory_limit called later on.
	// 	remove_filter( 'ig_es_memory_limit', 'ig_es_increase_memory_limit' );

	// 	set_time_limit( IG_SET_TIME_LIMIT );

	// 	$results = array();
	// 	if ( 'all' === $status ) {
	// 		$results = ES()->lists_contacts_db->get_all_contacts();
	// 	} elseif ( 'subscribed' === $status ) {
	// 		$results = ES()->lists_contacts_db->get_all_subscribed_contacts();
	// 	} elseif ( 'unsubscribed' === $status ) {
	// 		$results = ES()->lists_contacts_db->get_all_unsubscribed_contacts();
	// 	} elseif ( 'confirmed' === $status ) {
	// 		$results = ES()->lists_contacts_db->get_all_confirmed_contacts();
	// 	} elseif ( 'unconfirmed' === $status ) {
	// 		$results = ES()->lists_contacts_db->get_all_unconfirmed_contacts();
	// 	} elseif ( 'select_list' === $status ) {
	// 		$list_id = absint( $list_id );
	// 		$results = ES()->lists_contacts_db->get_all_contacts_from_list( $list_id );
	// 	}

	// 	$subscribers = array();

	// 	if ( count( $results ) > 0 ) {
	// 		$contact_list_map = array();
	// 		$contact_ids      = array();
	// 		foreach ( $results as $result ) {

	// 			if ( ! in_array( $result['contact_id'], $contact_ids, true ) ) {
	// 				$contact_ids[] = $result['contact_id'];
	// 			}

	// 			$contact_list_map[ $result['contact_id'] ][] = array(
	// 				'status'     => $result['status'],
	// 				'list_id'    => $result['list_id'],
	// 				'optin_type' => $result['optin_type'],
	// 			);
	// 		}

	// 		$contact_ids_str = implode( ',', $contact_ids );

	// 		$select_columns = array(
	// 			'id',
	// 			'first_name',
	// 			'last_name',
	// 			'email',
	// 			'created_at',
	// 		);

	// 		$custom_fields = ES()->custom_fields_db->get_custom_fields();
	// 		if ( ! empty( $custom_fields ) ) {
	// 			foreach ( $custom_fields as $field ) {
	// 				$select_columns[] = $field['slug'];
	// 			}
	// 		}

	// 		$query = 'SELECT ' . implode( ',', $select_columns ) . " FROM {$wpbd->prefix}ig_contacts WHERE id IN ({$contact_ids_str})";

	// 		$subscribers = $wpbd->get_results( $query, ARRAY_A );
	// 	}

	// 	$csv_output = '';
	// 	if ( count( $subscribers ) > 0 ) {

	// 		$headers = array(
	// 			__( 'First Name', 'email-subscribers' ),
	// 			__( 'Last Name', 'email-subscribers' ),
	// 			__( 'Email', 'email-subscribers' ),
	// 			__( 'List', 'email-subscribers' ),
	// 			__( 'Status', 'email-subscribers' ),
	// 			__( 'Opt-In Type', 'email-subscribers' ),
	// 			__( 'Created On', 'email-subscribers' ),
	// 		);

	// 		if ( ! empty( $custom_fields ) ) {
	// 			foreach ( $custom_fields as $field ) {
	// 				$headers[] = $field['label'];
	// 			}
	// 		}

	// 		$lists_id_name_map = ES()->lists_db->get_list_id_name_map();
	// 		$csv_output       .= implode( ',', $headers );

	// 		foreach ( $subscribers as $key => $subscriber ) {

	// 			$data 				= array();
	// 			$data['first_name'] = trim( str_replace( '"', '""', $this->escape_data( $subscriber['first_name'] ) ) );
	// 			$data['last_name']  = trim( str_replace( '"', '""', $this->escape_data( $subscriber['last_name'] ) ) );
	// 			$data['email']      = trim( str_replace( '"', '""', $this->escape_data( $subscriber['email'] ) ) );

	// 			$contact_id = $subscriber['id'];
	// 			if ( ! empty( $contact_list_map[ $contact_id ] ) ) {
	// 				foreach ( $contact_list_map[ $contact_id ] as $list_details ) {
	// 					$data['list']       = $lists_id_name_map[ $list_details['list_id'] ];
	// 					$data['status']     = ucfirst( $list_details['status'] );
	// 					$data['optin_type'] = ( 1 == $list_details['optin_type'] ) ? 'Single Opt-In' : 'Double Opt-In';
	// 					$data['created_at'] = $subscriber['created_at'];
	// 					if ( ! empty( $custom_fields ) ) {
	// 						foreach ( $custom_fields as $field ) {
	// 							$column_name = $field['slug'];
	// 							$data[ $column_name ] = $subscriber[ $column_name ];
	// 						}
	// 					}
	// 					$csv_output        .= "\n";
	// 					$csv_output        .= '"' . implode( '","', $data ) . '"';
	// 				}
	// 			}
	// 		}
	// 	}

	// 	return $csv_output;
	// }

	/**
	 * Escape a string to be used in a CSV context
	 *
	 * Malicious input can inject formulas into CSV files, opening up the possibility
	 * for phishing attacks and disclosure of sensitive information.
	 *
	 * Additionally, Excel exposes the ability to launch arbitrary commands through
	 * the DDE protocol.
	 *
	 * @see http://www.contextis.com/resources/blog/comma-separated-vulnerabilities/
	 * @see https://hackerone.com/reports/72785
	 *
	 * @since 5.5.3
	 * @param string $data CSV field to escape.
	 * @return string
	 */
	// public function escape_data( $data ) {
	// 	$active_content_triggers = array( '=', '+', '-', '@' );

	// 	if ( in_array( mb_substr( $data, 0, 1 ), $active_content_triggers, true ) ) {
	// 		$data = "'" . $data;
	// 	}

	// 	return $data;
	// }

}

