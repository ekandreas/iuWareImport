<?php

/**
 * Class Mentor_iuWare_Import_Tools
 */
class Mentor_iuWare_Import_Tools{

	function __construct() {

		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_action( 'wp_ajax_iuware_import', array( &$this, 'iuware_import' ) );
		add_action( 'wp_ajax_iuware_delete', array( &$this, 'iuware_delete' ) );

	}

	function admin_menu(){

		add_management_page( 'iuWare Import', 'iuWare Import', 'administrator', 'mentor_iuware_import_tools', array( &$this, 'tools' ) );

	}

	function tools(){

		?>

		<div class="wrap">

			<div id="icon-tools" class="icon32"><br></div><h2>iuWare Import</h2>
			<p>Här kan vi skriva lite instruktioner...</p>

			<table class="widefat" cellspacing="0">
				<tbody>
				<tr>
					<td>Paper as Filter:</td>
					<td>
						<input id="filter" name="filter" type="text" value="papernet.se" />
					</td>
				</tr>
				<tr class="alternate">
					<td>User ID:</td>
					<td>
						<?php

						$args = array(
							'orderby'      => 'email',
							'order'        => 'ASC',
							'fields' => 'all_with_meta'
						);
						$users = get_users( $args );

						?>
						<select id="user" name="user">
							<?php
							foreach( $users as $user ){
								echo '<option value="' . $user->ID . '">' . $user->user_email . '</option>';
							}
							?>
						</select>

					</td>
				</tr>
				<tr>
					<td>Start:</td>
					<td>
						<input id="start" name="start" type="text" value="0" />
					</td>
				</tr>
				<tr>
					<td>Filter Date >:</td>
					<td>
						<input id="filter_date" name="filter_date" type="text" value="<?php echo date('Y-m-d'); ?>" />
					</td>
				</tr>
				</tbody>
			</table>

			<br/>

			<input type="submit" id="import" name="import" value="Start" class="button-primary" />
			&nbsp;<input type="button" id="delete" name="delete" value="Delete all posts with SSOID" class="button-secondary" />

			<br/><br/>

			<div id="form"></div>
			<div id="executor"></div>

			<input type="hidden" name="quit" id="quit" value="0" />

		</div>

		<script language="javascript">

			jQuery(document).ready(function($){

				$('#import').click(function(){

					$('#form').load( '<?php echo admin_url( 'admin-ajax.php' ); ?>?action=iuware_import&form=1&start=' + $('#start').val() + '&filter=' + $('#filter').val() + '&filter_date=' + $('#filter_date').val() );
					return false;

				});

				$('#delete').click(function(){

					$('#form').load( '<?php echo admin_url( 'admin-ajax.php' ); ?>?action=iuware_delete&form=1' );
					return false;

				});

			});

		</script>

		<?php

	}

	function iuware_import(){

		include WP_PLUGIN_DIR . '/iuWareImport/vendor/autoload.php';

		include WP_PLUGIN_DIR . '/iuWareImport/Sherlock/Sherlock.php';
		\Sherlock\Sherlock::registerAutoloader();

		$start = 0;
		if ( isset( $_REQUEST['start'] ) && ((int)$_REQUEST['start']) >0 ) $start = (int)$_REQUEST['start'];

		$filter_date = date('Y-m-d');
		if ( isset( $_REQUEST['filter_date'] ) ) $filter_date = $_REQUEST['filter_date'];

		$stop = $start+10;
		if ( isset( $_REQUEST['stop'] ) && ((int)$_REQUEST['stop']) >0 ) $stop = (int)$_REQUEST['stop'];

		$filter = isset( $_REQUEST['filter'] ) ? esc_attr( $_REQUEST['filter'] ) : 'papernet.se';
		$import_index = 'mo-iuware';
		$user = isset( $_REQUEST['user'] ) ? esc_attr( $_REQUEST['user'] ) : '1';

		$sherlock = new \Sherlock\Sherlock();
		$sherlock->addNode("elastic.flowcom.se", "80");

		if( $stop>0 && $start >= $stop ) {
			echo '<script language="javascript">';
			echo 'jQuery("#ajaxloader").hide();';
			echo 'jQuery("#stopper").hide();';
			echo 'jQuery("#quit").val("1");';
			echo '</script>';
		}

		if( isset( $_REQUEST['form'] ) ){
			?>
			<textarea id="log" name="log" rows="15" cols="100">
			</textarea>
			<br/>
			<input type="submit" name="stopper" id="stopper" value="Stop" class="button-primary" />
			&nbsp;
			<img id="ajaxloader" src="<?php echo WP_PLUGIN_URL; ?>/iuWareImport/img/ajax-loader.gif" />
			<?php
			$code = "jQuery('#executor').load( '" . admin_url( 'admin-ajax.php' ) . "?action=iuware_import&user='+jQuery('#user').val()+'&filter='+jQuery('#filter').val() + '&start=' +jQuery('#start').val() + '&filter_date=' +jQuery('#filter_date').val() );";
			echo '<script language="javascript">';
			echo 'setTimeout( "' . $code . '", 100 );';
			echo 'jQuery("#stopper").click(function(){';
			echo 'jQuery("#ajaxloader").hide();';
			echo 'jQuery("#quit").val("1");';
			echo 'return false;';
			echo '});';
			echo '</script>';

		}
		else{

			$result = "";

			$index_name = "mo-iuware";

			//Build a new search request
			$request = $sherlock->search();

			$json='
			{
			"from" : ' . $start . ',
			"size" : 10,

			"sort" : [
							{
								"date" : {
									"order" : "asc"
								}
							}
							],

			"query": {
					"filtered": {
						"query": {
							"term": { "paper" : "' . $filter . '" }
						},
						"filter": {
							"range": {
								"date": { "gt": "' . $filter_date . 'T00:00:00" }
							}
						}
					}
				}

			}
			';

			error_log($json);

			$rawTermQuery = \Sherlock\Sherlock::queryBuilder()->Raw($json);

			//$termQuery = \Sherlock\Sherlock::queryBuilder()->Term()->field("paper")
			//		->term($filter);

			//Set the index, type and from/to parameters of the request.
			$request->index($index_name)
					->type("article")
					->query($rawTermQuery);

			//Finally, set the query and execute
			$response = $request->execute();

			$updated = 0;
			$inserted = 0;
			$date = '';

			if( $response->total ){
				foreach( $response->hits as $hit ){
					if( $hit['source']['paper']==$filter && !( empty( $hit['source']['body']) && empty( $hit['source']['preamble']) ) ){

						$args = array(
							'meta_key'        => 'ssoid',
							'meta_value'      => $hit['source']['ssoid'],
						);
						$posts = get_posts( $args );

						if( sizeof( $posts ) ){

							$post = array(
								'ID' => $posts[0]->ID,
								'post_content'   => $hit['source']['body'],
								'post_date'      => $hit['source']['date'],
								'post_excerpt'   => $hit['source']['preamble'],
								'post_status'    => 'publish',
								'post_title'     => $hit['source']['headline'],
								'post_type'      => 'post',
								'post_author'	=> $user
							);

							$post_id = wp_update_post( $post );

							update_post_meta( $post_id, 'iuware_source', $hit['source']['paper'] );

							$updated++;
							//$result .= $hit['source']['headline'] . ", updated.\n";

							$date = $hit['source']['date'];

						}
						else{

							$post = array(
								'post_content'   => $hit['source']['body'],
								'post_date'      => $hit['source']['date'],
								'post_excerpt'   => $hit['source']['preamble'],
								'post_status'    => 'publish',
								'post_title'     => $hit['source']['headline'],
								'post_type'      => 'post',
								'post_author'	=> $user
							);

							$post_id = wp_insert_post( $post );

							update_post_meta( $post_id, 'ssoid', $hit['source']['ssoid'] );
							update_post_meta( $post_id, 'iuware_source', $hit['source']['paper'] );

							$inserted++;
							//$result .= $hit['source']['headline'] . ", imported.\n";

							$date = $hit['source']['date'];

						}
					}
				}
			}
			else{
				echo '<script language="javascript">';
				echo 'jQuery("#ajaxloader").hide();';
				echo 'jQuery("#stopper").hide();';
				echo 'jQuery("#quit").val("1");';
				echo '</script>';
				return;
			}

			$result .= $start . '-' . $stop . ': ' . $inserted . ' is inserted and ' . $updated . ' is updated. [' . $date . ']';

			$start += 10;
			$stop += 10;

			$result = str_replace( '"', "'", $result );

			$code = "jQuery('#executor').load( '" . admin_url( 'admin-ajax.php' ) . "?action=iuware_import&start=" . $start . "&user=" . $user . "&filter=" . $filter . "&stop=" . $stop . "&filter_date=" . $filter_date . "' );";
			echo '<script language="javascript">';
			echo 'jQuery("#log").val( "' . $result . '\n" + jQuery("#log").val() );';
			echo 'if ( jQuery("#log").val().length>3000 ) jQuery("#log").val( jQuery("#log").val().substring(0,2999) );';
			echo 'if( jQuery("#quit").val() == "0" ) setTimeout( "' . $code . '", 100 );';
			echo '</script>';

		}

		exit;

	}

	function decode( $input ){

		$result = $input;

		$result = str_replace( '&nbsp;', ' ', $result );
		$result = str_replace( '&#229;', 'å', $result );
		$result = str_replace( '&#228;', 'ä', $result );
		$result = str_replace( '&#246;', 'ö', $result );

		$result = str_replace( '&#196;', 'Ä', $result );
		$result = str_replace( '&#197;', 'Å', $result );
		$result = str_replace( '&#214;', 'Ö', $result );

		return $result;

	}

	function iuware_delete(){

		if( isset( $_REQUEST['form'] ) ){
			?>
			<textarea id="log" name="log" rows="15" cols="100">
			</textarea>
			<br/>
			<input type="submit" name="stopper" id="stopper" value="Stop" class="button-primary" />
			&nbsp;
			<img id="ajaxloader" src="<?php echo WP_PLUGIN_URL; ?>/iuWareImport/img/ajax-loader.gif" />
			<?php
			$code = "jQuery('#executor').load( '" . admin_url( 'admin-ajax.php' ) . "?action=iuware_delete' );";
			echo '<script language="javascript">';
			echo 'setTimeout( "' . $code . '", 100 );';
			echo 'jQuery("#stopper").click(function(){';
			echo 'jQuery("#ajaxloader").hide();';
			echo 'jQuery("#quit").val("1");';
			echo 'return false;';
			echo '});';
			echo '</script>';

		}
		else{

			$result = "";

			$no_deleted = 0;

			$args = array(
				'meta_key' => 'ssoid',
				'numberposts' => 100,
				'meta_query' => array( 'meta_key' => 'ssoid', 'meta_value' => '0', 'meta_compare' => '>' )
			);
			$posts = get_posts( $args );

			foreach( $posts as $post ){
				$del = wp_delete_post( $post->ID, true );
				if( $del ) $no_deleted++;
			}

			$result .= $no_deleted . ' is deleted.';

			if( !$no_deleted ){
				echo '<script language="javascript">';
				echo 'jQuery("#ajaxloader").hide();';
				echo 'jQuery("#stopper").hide();';
				echo 'jQuery("#quit").val("1");';
				echo '</script>';
			}

			$result = str_replace( '"', "'", $result );

			$code = "jQuery('#executor').load( '" . admin_url( 'admin-ajax.php' ) . "?action=iuware_delete' );";
			echo '<script language="javascript">';
			echo 'jQuery("#log").val( "' . $result . '\n" + jQuery("#log").val() );';
			echo 'if ( jQuery("#log").val().length>3000 ) jQuery("#log").val( jQuery("#log").val().substring(0,2999) );';
			echo 'if( jQuery("#quit").val() == "0" ) setTimeout( "' . $code . '", 100 );';
			echo '</script>';

		}

		exit;

	}



}

new Mentor_iuWare_Import_Tools();

?>