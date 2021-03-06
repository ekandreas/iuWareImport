<?php

/**
 * Class Mentor_iuWare_Import_Tools
 */
class Mentor_iuWare_Import_Tools{

	function __construct() {

		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
        add_action( 'wp_ajax_nopriv_iuware_import', array( &$this, 'iuware_import' ) );
        add_action( 'wp_ajax_iuware_import', array( &$this, 'iuware_import' ) );
        add_action( 'cron_iuware_import', array( &$this, 'iuware_import' ) );

        add_filter( 'cron_schedules', array(&$this, 'addCronMinutes' ) );

        add_action( 'wp', array( &$this, 'prefix_setup_schedule' ) );

        //wp_clear_scheduled_hook('cron_iuware_import');

        if ( !wp_next_scheduled( 'cron_iuware_import' ) ) {
            wp_schedule_event( time(), 'minute', 'cron_iuware_import' );
        }

    }

    function addCronMinutes($array) {
        $array['minute'] = array(
            'interval' => 60,
            'display' => 'Once a Minute',
        );
        return $array;
    }

    function prefix_setup_schedule(){
        if ( !wp_next_scheduled( 'cron_iuware_import' ) ) {
            wp_schedule_event( time(), 'minute', 'cron_iuware_import' );
        }
    }

	function admin_menu(){

		add_management_page( 'iuWare Import', 'iuWare Import', 'administrator', 'mentor_iuware_import_tools', array( &$this, 'tools' ) );

	}

	function tools(){

        $iuware_stopped = (int)get_option( 'iuware_stopped' );
        $iuware_running = get_option( 'iuware_running' );
        $iuware_ssoid = (int)get_option( 'iuware_ssoid' );
        $iuware_latest = (int)get_option( 'iuware_latest' );
        $iuware_finished = get_option( 'iuware_finished' );
        $iuware_batch = (int)get_option( 'iuware_batch' );
				$iuware_paper = get_option( 'iuware_paper' );

        $saved = "";

        if( isset( $_REQUEST['save'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'iuware_settings' ) ){

            $iuware_stopped = isset($_REQUEST['stopped']) ? 1 : 0;
            $iuware_ssoid = (int)$_REQUEST['ssoid'];
            $iuware_running = isset($_REQUEST['running']) ? '' : $iuware_running;
						$iuware_paper = isset($_REQUEST['paper']) ? $_REQUEST['paper'] : '';

            update_option( 'iuware_running', $iuware_running );
            update_option( 'iuware_stopped', $iuware_stopped );
            update_option( 'iuware_ssoid', $iuware_ssoid );
						update_option( 'iuware_paper', $iuware_paper );
            $saved = "Inställningarna är uppdaterade " . date( "Y-m-d H:i:s" ) . ".<br/>" . print_r($_REQUEST,true);

        }


        if( !$iuware_batch ) $iuware_batch = 50;

        ?>

		<div class="wrap">

			<div id="icon-tools" class="icon32"><br></div><h2>iuWare Import</h2>
			<p>iuWare Import hämtar innehållet från detaljsidor i iuWare-sajterna hos Mentoronline.</p>
            <p>Importen startar från det SSOID du sätter. Varje tidning får sin egen kategori i denna WordPressinstallation.</p>
            <p>Importen går på cronjobb men detta gränssnitt kan också köras manuellt.</p>

            <?php

            if( !empty( $saved ) ){
                echo '<div class="updated">' . $saved . '</div>';
            }

            ?>

            <form method="get">

                <table class="widefat" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Fältnamn</th>
                            <th>Värde</th>
                            <th>Beskrivning</th>
                        </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>Import stoppad</td>
                        <td>
                            <input name="stopped" type="checkbox"<?php checked( 1, $iuware_stopped ); ?> />
                        </td>
                        <td>
                            Är denn ikryssad kommed ingen import att göras.
                        </td>
                    </tr>
                    <tr>
                        <td>Körs redan?</td>
                        <td>
                            <span><?php echo empty( $iuware_running ) ? '' : date_i18n( 'Y-m-d H:i:s', strtotime( $iuware_running ) ); ?></span> <input name="running" type="checkbox" /> Nollställ
                        </td>
                        <td>
                            Gör omstart på jobb genom att kryssa i denna.
                        </td>
                    </tr>
                    <tr>
                        <td>SSOID Start</td>
                        <td>
                            <input name="ssoid" type="text" value="<?php echo $iuware_ssoid; ?>" />
                        </td>
                        <td>
                            Under pågående jobb uppdateras denna. För att återställa, se till att inget jobb körs först!
                        </td>
                    </tr>
										<tr>
											<td>Tidningsfilter</td>
											<td>
												<input name="paper" type="text" value="<?php echo $iuware_paper; ?>" />
											</td>
											<td>
												Filtrera på tidningsnamn?
											</td>
										</tr>
                    <tr>
                        <td>Batch om n poster</td>
                        <td>
                            <span><?php echo $iuware_batch; ?> stycken</span>
                        </td>
                        <td>
                            Hur många poster som importerades i en körning. Antalet justeras så att det är närmast anpassat till en minut.
                        </td>
                    </tr>
                    <tr>
                        <td>Senast körd</td>
                        <td>
                            <span><?php echo date_i18n( 'Y-m-d H:i:s', strtotime( $iuware_finished ) ); ?></span>
                        </td>
                        <td>
                            När i tiden senaste importen kördes.
                        </td>
                    </tr>
                    <tr>
                        <td>Senast tidsåtgång</td>
                        <td>
                            <span><?php echo $iuware_latest; ?> sekunder</span>
                        </td>
                        <td>
                            Hur lång tid det tog senaste gången importen kördes.
                        </td>
                    </tr>
                    <tr>
                        <td>Nästa körning</td>
                        <td>
                            <span><?php $next = wp_next_scheduled( 'cron_iuware_import' ); echo $next ? '' : date_i18n( 'Y-m-d H:i:s', strtotime( $next  ) ); ?></span>
                        </td>
                        <td>
                            Nästa planerade cronjobb.
                        </td>
                    </tr>
                    </tbody>
                    <tfoot>
                    <tr>
                        <th>Fältnamn</th>
                        <th>Värde</th>
                        <th>Beskrivning</th>
                    </tr>
                    </tfoot>
                </table>

                <br/>

                <input type="submit" name="save" value="Spara" class="button-primary" />
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'iuware_settings' ); ?>" />
                <input type="hidden" name="page" value="mentor_iuware_import_tools" />

            </form>

            <br/><br/>

            <a href="<?php echo admin_url('admin-ajax.php'); ?>?action=iuware_import" target="_blank">Kör import i annat fönster</a>

		</div>

		<?php

	}

    function microtime_float(){
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

	function iuware_import(){

        $iuware_stopped = (int)get_option( 'iuware_stopped' );
        $iuware_running = get_option( 'iuware_running' );
        $iuware_ssoid = (int)get_option( 'iuware_ssoid' );
        $iuware_batch = (int)get_option( 'iuware_batch' );
				$iuware_paper = get_option( 'iuware_paper' );

        $term_index = array();

        if( $iuware_stopped ) return;
        if( !empty( $iuware_running ) ){
            // check if this is a long time ago, then kick it again...
            $running = strtotime( $iuware_running );
            $diff = ( time() - $running ) / 60;
            //echo "TIMEDIFF=" . $diff;
            if( $diff < 5 ) return;
        }

        if( !$iuware_batch ) $iuware_batch = 50;

        update_option( 'iuware_running', date( 'Y-m-d H:i:s' ) );

        // first create root term...
        $term_iuWare = $term_index['iuWare'];
        if( !$term_iuWare ){
            $term_iuWare = term_exists('iuWare', 'category');
            $term_iuWare = $term_iuWare['term_id'];
            if ( !$term_iuWare ) {
                wp_insert_term(
                    'iuWare',
                    'category');
                $term_iuWare = term_exists('iuWare', 'category');
                $term_iuWare = $term_iuWare['term_id'];
            }
            $term_index['iuWare'] = $term_iuWare;
        }


        $step = $iuware_batch;
        $pageid = 3868;
        $domain = "http://www.plastnet.se";

        $time_start = $this->microtime_float();

        for( $ssoid = $iuware_ssoid; $ssoid<$iuware_ssoid+$step; $ssoid++ ){

            $date = "1999-01-01";
            $post_is_saved = false;

            $the_body = wp_remote_retrieve_body( wp_remote_get( $domain . "/iuware.aspx?pageid=" . $pageid . "&ssoid=" . $ssoid ) );

            $matches = array();
            preg_match_all('/<div class=\"container_centermain_article\">(.*?)<div>/s', $the_body, $matches);

            if( isset( $matches[1][0] ) ){

                $content = $matches[1][0];

                $images = array();

                preg_match_all('/<p class=\"paper\">\((.*?)\)<\/p>/s', $content, $matches);
                $paper = isset($matches[1][0]) ? $this->decode( $matches[1][0] ) : '';

								if( !empty( $iuware_paper ) && $paper != $iuware_paper ) {
									echo $ssoid . ". Not paper = $iuware_paper<br/>";
									continue;
								}

                preg_match_all('/<h1>(.*?)<\/h1>/s', $content, $matches);
                $headline = $this->decode( $matches[1][0] );

                preg_match_all('/<p class=\"date\">(.*?)<\/p>/s', $content, $matches);
                $date = $this->decode( $matches[1][0] );
                $date = date( 'Y-m-d', strtotime( $date ) );

                preg_match_all('/<p class=\"preamble\">(.*?)<\/p>/s', $content, $matches);
                $preamble = $this->decode( $matches[1][0] );
                preg_match_all( '/src="([^"]*)"/', $preamble, $matches);
                if ( strpos( $matches[1][0], 'iuware_files' ) )
                {
                    $images[] = $domain . $matches[1][0];
                }
                $preamble = strip_tags( $preamble, '<br><p><hr><i>' );

                preg_match_all( '/<p class=\"body\">(.*?)<\/p>/s', $content, $matches);
                $body = $this->decode( $matches[1][0] );
                preg_match_all( '/src="([^"]*)"/', $body, $matches);
                if ( strpos( $matches[1][0], 'iuware_files' ) )
                {
                    $images[] = $domain . $matches[1][0];
                }
                $body = strip_tags( $body, '<br><p><hr><i><img>' );

                if( $headline == $preamble && $preamble == $body ){
                    echo $ssoid . ". No article<br/>";
                }
                else if( $date < '1999-02-01' ){
                    echo $ssoid . ". No date in article<br/>";
                }
                else if( empty( $headline ) ){
                    echo $ssoid . ". No headline in article<br/>";
                }
                else{

                    $post_id = 0;

                    $post_is_saved = true;

                    $term_paper = $term_index[$paper];

                    if( !$term_paper ) {

                        $term_paper = term_exists( $paper, 'category', $term_iuWare );
                        $term_paper = $term_paper['term_id'];
                        if ( !$term_paper ) {
                            wp_insert_term(
                                $paper,
                                'category',
                                array(
                                    'parent' => $term_iuWare
                                )
                            );
                            $term_paper = term_exists( $paper, 'category' );
                            $term_paper = $term_paper['term_id'];
                        }
                        $term_index[$paper] = $term_paper;

                    }

                    $args = array(
                        'meta_key'        => 'SSOID',
                        'meta_value'      => $ssoid,
                    );
                    $posts = get_posts( $args );

                    $post = array(
                        'post_content'   => $body,
                        'post_date'      => $date,
                        'post_excerpt'   => $preamble,
                        'post_status'    => 'publish',
                        'post_title'     => $headline,
                        'post_type'      => 'post',
                        'post_author'	=> 1
                    );

                    if( sizeof( $posts ) ){
                        $post['ID'] = $posts[0]->ID;
                        $post_id = wp_update_post( $post );
                        echo $ssoid . ". Updated, " . $headline . "<br/>";

                    }
                    else{
                        $post_id = wp_insert_post( $post );
                        echo $ssoid . ". Inserted, " . $headline . "<br/>";
                    }

                    update_post_meta( $post_id, 'IUWARE_SOURCE', $paper );
                    update_post_meta( $post_id, 'SSOID', $ssoid );

                    wp_set_post_terms( $post_id, array($term_iuWare, $term_paper), 'category' );

                    //images
                    $thumb_set = false;
                    if( sizeof( $images ) ){
                        foreach( $images as $image ){
                            if( $this->url_exists( $image ) ){
                                $attach_id = $this->upload_image( $image, $post_id );
                                if( !$thumb_set ){
                                    set_post_thumbnail( $post_id, $attach_id );
                                }
                            }
                        }
                    }

                }
            }

            if( !$post_is_saved ){
                if( strtotime( $date ) < strtotime( '-1 week' ) ){
                    //update_option( 'iuware_ssoid', $ssoid );
                }
            }
            else{
                update_option( 'iuware_ssoid', $ssoid );
            }

        }

        $time_end = $this->microtime_float();
        $time = $time_end - $time_start;
        $iuware_latest = (int)$time;
        update_option( 'iuware_latest', $iuware_latest );

        if( $iuware_latest < 50 ){
            $iuware_batch++;
        }
        else{
            $iuware_batch--;
        }
        update_option( 'iuware_batch', $iuware_batch );

        update_option( 'iuware_finished', date( 'Y-m-d H:i:s' ) );

        update_option( 'iuware_ssoid', $ssoid );

        delete_option( 'iuware_running', '' );

        if ( !wp_next_scheduled( 'cron_iuware_import' ) ) {
            wp_schedule_event( time(), 'minute', 'cron_iuware_import' );
        }

        exit;

	}

    function url_exists($url) {
        if(@file_get_contents($url,0,NULL,0,1)){return 1;}else{ return 0;}
    }

    function upload_image( $url, $post_id ){

        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents( $url );
        $filename = basename( $url );
        if(wp_mkdir_p($upload_dir['path']))
            $file = $upload_dir['path'] . '/' . $filename;
        else
            $file = $upload_dir['basedir'] . '/' . $filename;
        file_put_contents($file, $image_data);

        $wp_filetype = wp_check_filetype( $filename, null );
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        return $attach_id;

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


}

new Mentor_iuWare_Import_Tools();

?>